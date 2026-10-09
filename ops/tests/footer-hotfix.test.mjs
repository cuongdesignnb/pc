import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const script = readFileSync(new URL('../deploy-laptopplus-footer-hotfix.sh', import.meta.url), 'utf8');

test('uses exact deployed image base and checks runtime revisions/IDs', () => {
    assert.match(script, /BACKEND_BASE='cf2345ffe8365640170b6db6c367b8e11d956508'/);
    assert.match(script, /FRONTEND_SHA='d69248761223c2b5d1934734d0975dc5f4af9319'/);
    assert.match(script, /org\.opencontainers\.image\.revision/);
    assert.match(script, /Retagged \$service image/);
    assert.match(script, /merge-base --is-ancestor/);
    assert.match(script, /diff --name-only/);
});
test('only copies two PHP files and generates authoritative autoload offline without scripts', () => {
    const copies = script.split('\n').filter(line => line.includes("'COPY --chmod"));
    assert.equal(copies.length, 2);
    assert.match(copies[0], /MenuController\.php/);
    assert.match(copies[1], /FooterPolicyLinks\.php/);
    assert.match(script, /--network=none --pull=false/);
    assert.match(script, /COMPOSER_DISABLE_NETWORK=1 composer dump-autoload --no-dev --classmap-authoritative --no-scripts --no-plugins/);
    assert.match(script, /class_exists\("App\\\\Services\\\\Menus\\\\FooterPolicyLinks"\)/);
    assert.doesNotMatch(script, /composer install|npm (?:ci|run build)|artisan (?:migrate|db:seed|cache:clear|optimize:clear)/);
});
test('activation and rollback recreate backend services, never the frontend/database/cache', () => {
    const calls = script.split('\n').filter(line => line.includes('up -d'));
    assert.equal(calls.length, 2);
    assert.ok(calls.every(line => !/frontend|mysql|redis|meilisearch/.test(line)));
    assert.ok(calls.every(line => line.includes('--no-build --no-deps --force-recreate')));
    assert.match(script, /cp -p "\$BACKUP_DIR\/stack\.env" "\$STACK_ENV" && activate_backend/);
    assert.match(script, /FRONTEND_BEFORE/);
    assert.doesNotMatch(script, /FRONTEND_IMAGE_TAG=.*tag|docker (?:image )?prune/);
});
test('SSH detach has separate session, closed stdin and shared deployment lock', () => {
    assert.match(script, /setsid nohup bash "\$SCRIPT_PATH"/);
    assert.match(script, /2>&1 <\/dev\/null/);
    assert.match(script, /exec 9>\/tmp\/laptopplus-production-deploy\.lock/);
    assert.match(script, /flock -n 9/);
});
test('checks a link inside footer SSR, not HTTP status alone, and fingerprints data without exposing settings', () => {
    assert.match(script, /\/\/footer\/\/a\[@href=/);
    assert.match(script, /for path in '\/' '\/chinh-sach-gia'/);
    assert.match(script, /\["pages", "menus", "menu_items", "settings"\]/);
    assert.match(script, /hash\("sha256", \$rows->toJson/);
    assert.match(script, /cmp -s .*records-before\.json.*records-after\.json/);
    assert.match(script, /HIDDEN_PAGE=404_VERIFIED/);
    assert.match(script, /FOOTER_CACHE_WAIT attempt=\$attempt\/25/);
    assert.match(script, /wait_footer_ssr "\$endpoint\$path"/);
});
test('audit mode exits before image builds and all destructive cleanup is scoped to mktemp', () => {
    assert.ok(script.indexOf('DEPLOY_AUDIT_ONLY') < script.indexOf('docker build'));
    assert.match(script, /PRODUCTION_MUTATION=NONE/);
    assert.match(script, /CONTEXT="\$\(mktemp -d \/tmp\/laptopplus-footer\.XXXXXX\)"/);
    assert.match(script, /realpath "\$CONTEXT"/);
    assert.match(script, /rm -rf -- "\$CONTEXT"/);
    assert.match(script, /MIGRATION=NOT_RUN/);
    assert.match(script, /SEEDERS=NOT_RUN/);
});
