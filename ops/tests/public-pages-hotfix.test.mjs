import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

const source = readFileSync(new URL('../deploy-laptopplus-public-pages-hotfix.sh', import.meta.url), 'utf8')

test('hotfix is pinned to the two deployed release bases, not origin/main', () => {
  assert.ok(source.includes("BACKEND_BASE='8b0b58b3ac4edb3cdc2e14a75cc1b774c2f9b49a'"))
  assert.ok(source.includes("FRONTEND_BASE='0929f0a1975b5bfbafa115e1d431e0195f4a78a5'"))
  assert.ok(source.includes('check_scope "$STACK_DIR"'))
  assert.ok(source.includes('check_scope "$FRONTEND_REPO"'))
  assert.ok(!source.includes('origin/main'))
})

test('no database write, migrate, seed, cache purge, destructive checkout or image prune command', () => {
  for (const forbidden of [/artisan\s+(?:migrate|db:seed|cache:clear|optimize:clear)/, /git[^\n]+(?:reset --hard|checkout --)/, /docker[^\n]+prune/, /Page::[^\n]+(?:update|delete|create)\(/]) {
    assert.doesNotMatch(source, forbidden)
  }
  assert.ok(source.includes("echo 'CMS_RECORDS=UNCHANGED'"))
  assert.ok(source.includes('pages-before.json'))
  assert.ok(source.includes('pages-after.json'))
})

test('build both images before switching, back up both tags and roll back with health checks', () => {
  assert.ok(source.indexOf('step build-public-pages-storefront') < source.indexOf('step switch-reviewed-image-tags'))
  assert.ok(source.includes('cp -p "$STACK_ENV" "$BACKUP_DIR/stack.env"'))
  assert.ok(source.includes('cp -p "$BACKUP_DIR/stack.env" "$STACK_ENV"'))
  assert.ok(source.includes('&& wait_healthy laptopplus-backend-php && wait_healthy laptopplus-backend-nginx && wait_healthy laptopplus-frontend'))
})

test('saved-file detachment survives SSH disconnect and audit stops before build/switch', () => {
  assert.ok(source.includes('setsid nohup bash "$SCRIPT_PATH"'))
  assert.ok(source.includes("trap '' HUP"))
  assert.ok(source.indexOf('AUDIT_STATUS=PASS') < source.indexOf('step prepare-immutable-build-contexts'))
})

test('success requires real SSR policy body/canonical, matching public release and hidden 404', () => {
  assert.ok(source.indexOf('validate_ssr_page "$CONTEXT/page.html"') < source.indexOf('SUCCEEDED=1'))
  assert.ok(source.includes('static-page-body'))
  assert.ok(source.includes('https://laptopplus.vn/chinh-sach-gia'))
  assert.ok(source.includes('Storefront is serving a different release'))
  assert.ok(source.includes('HIDDEN_PAGE=404_VERIFIED'))
})
