#!/usr/bin/env bash

set -Eeuo pipefail

# Layer two reviewed PHP files onto the exact audited image. Do not deploy main,
# install dependencies, rebuild the storefront, migrate, seed or publish pages.
BACKEND_SHA="${1:-}"
BACKEND_BASE='cf2345ffe8365640170b6db6c367b8e11d956508'
FRONTEND_SHA='d69248761223c2b5d1934734d0975dc5f4af9319'
BRANCH='codex/laptopplus-footer-policies'
STACK_DIR='/www/docker/laptopplus.vn'
STACK_ENV="$STACK_DIR/deploy/production/stack.env"
COMPOSE_FILE="$STACK_DIR/docker-compose.production.yml"
SCRIPT_URL="https://raw.githubusercontent.com/cuongdesignnb/pc/${BACKEND_SHA}/ops/deploy-laptopplus-footer-hotfix.sh"
PUBLIC_ORIGIN='https://laptopplus.vn'
API_ORIGIN='https://admin.laptopplus.vn'

[[ "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] || { echo 'DEPLOY_ERROR=Pass a full backend SHA' >&2; exit 1; }
[[ "${DEPLOY_SCRIPT_URL:-$SCRIPT_URL}" == "$SCRIPT_URL" ]] || { echo 'DEPLOY_ERROR=Helper URL must be pinned' >&2; exit 1; }
SCRIPT_PATH="${BASH_SOURCE[0]}"
[[ "$SCRIPT_PATH" == /* ]] || SCRIPT_PATH="$PWD/$SCRIPT_PATH"
[[ -f "$SCRIPT_PATH" ]] || { echo 'DEPLOY_ERROR=Download the helper to a file first' >&2; exit 1; }
umask 077
if [[ "${DEPLOY_DETACH:-0}" == 1 && "${DEPLOY_DAEMONIZED:-0}" != 1 ]]; then
    LOG="${DEPLOY_LOG:-/tmp/laptopplus-footer-$(date -u +%Y%m%d-%H%M%S).log}"
    command -v setsid >/dev/null || { echo 'DEPLOY_ERROR=setsid is required' >&2; exit 1; }
    DEPLOY_DAEMONIZED=1 DEPLOY_DETACH=0 setsid nohup bash "$SCRIPT_PATH" "$BACKEND_SHA" >"$LOG" 2>&1 </dev/null &
    echo "DEPLOY_PID=$!"
    echo "DEPLOY_LOG=$LOG"
    exit 0
fi

CONTEXT=''; BACKUP_DIR=''; ENV_UPDATED=0; SUCCEEDED=0
COMPOSE=(docker compose --env-file "$STACK_ENV" -f "$COMPOSE_FILE")
step() { printf '\n[%s] STEP=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"; }
fail() { echo "DEPLOY_ERROR=$*" >&2; exit 1; }
wait_healthy() {
    local container="$1" state attempt
    for attempt in {1..60}; do
        state="$(docker inspect "$container" --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}' 2>/dev/null || true)"
        [[ "$state" == 'running|healthy' ]] && return 0
        [[ "$state" == exited* || "$state" == dead* ]] && return 1
        echo "HEALTH_WAIT container=$container attempt=$attempt/60 state=$state"
        sleep 2
    done
    return 1
}
activate_backend() {
    "${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-php || return 1
    wait_healthy laptopplus-backend-php || return 1
    "${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-nginx queue scheduler || return 1
    wait_healthy laptopplus-backend-nginx || return 1
    wait_healthy laptopplus-queue || return 1
    wait_healthy laptopplus-scheduler
}
on_exit() {
    local rc=$?
    trap - EXIT
    if [[ "$rc" != 0 ]]; then
        echo "DEPLOY_ERROR=process_exit_$rc" >&2
        if [[ "$ENV_UPDATED" == 1 ]]; then
            echo 'ROLLBACK=START' >&2
            if cp -p "$BACKUP_DIR/stack.env" "$STACK_ENV" && activate_backend; then
                echo 'ROLLBACK=COMPLETE' >&2
            else
                echo "ROLLBACK=INCOMPLETE BACKUP_DIR=$BACKUP_DIR" >&2
            fi
        fi
        echo 'DEPLOY_STATUS=FAILED' >&2
    elif [[ "$SUCCEEDED" == 1 ]]; then
        echo 'DEPLOY_STATUS=SUCCESS'
    fi
    # Only remove the validated private build context, never a workspace.
    if [[ "$CONTEXT" == /tmp/laptopplus-footer.* && -d "$CONTEXT" && "$(realpath "$CONTEXT")" == "$CONTEXT" ]]; then
        rm -rf -- "$CONTEXT"
    fi
    exit "$rc"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap '' HUP
for tool in git docker curl tar awk sed grep sort cp mv cmp mktemp flock realpath date sleep; do
    command -v "$tool" >/dev/null || fail "Missing command: $tool"
done
[[ "$(realpath "$STACK_DIR")" == "$STACK_DIR" && -f "$STACK_ENV" && -f "$COMPOSE_FILE" ]] || fail 'Unexpected stack/config path'
exec 9>/tmp/laptopplus-production-deploy.lock
flock -n 9 || fail 'Another LaptopPlus production deploy is running'
origin="$(git -C "$STACK_DIR" remote get-url origin)"
case "$origin" in
    'https://github.com/cuongdesignnb/pc'|'https://github.com/cuongdesignnb/pc.git'|'git@github.com:cuongdesignnb/pc.git'|'ssh://git@github.com/cuongdesignnb/pc.git') ;;
    *) fail 'Unexpected backend repository' ;;
esac
read_tag() {
    [[ "$(grep -c "^$1=" "$STACK_ENV" || true)" == 1 ]] || fail "Expected one $1"
    sed -n "s/^$1=//p" "$STACK_ENV"
}
[[ "$(read_tag APP_IMAGE_TAG)" == "${BACKEND_BASE:0:12}" ]] || fail 'Backend release changed; audit before deployment'
[[ "$(read_tag FRONTEND_IMAGE_TAG)" == "${FRONTEND_SHA:0:12}" ]] || fail 'Storefront release changed; audit before deployment'
for entry in 'backend-php:laptopplus-backend' 'backend-nginx:laptopplus-backend-nginx' 'frontend:laptopplus-frontend'; do
    service="${entry%%:*}"; repository="${entry#*:}"; revision="$BACKEND_BASE"
    [[ "$service" == frontend ]] && revision="$FRONTEND_SHA"
    image="$repository:${revision:0:12}"
    [[ "$(docker inspect "laptopplus-$service" --format '{{.Config.Image}}')" == "$image" ]] || fail "Unexpected $service image"
    [[ "$(docker inspect "laptopplus-$service" --format '{{.Image}}')" == "$(docker image inspect "$image" --format '{{.Id}}')" ]] || fail "Retagged $service image"
    [[ "$(docker image inspect "$image" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" == "$revision" ]] || fail "Unverified $service revision"
done
FRONTEND_BEFORE="$(docker inspect laptopplus-frontend --format '{{.Id}}|{{.Image}}')"
PHP_BASE_ID="$(docker image inspect "laptopplus-backend:${BACKEND_BASE:0:12}" --format '{{.Id}}')"
NGINX_BASE_ID="$(docker image inspect "laptopplus-backend-nginx:${BACKEND_BASE:0:12}" --format '{{.Id}}')"
step audit-pinned-footer-source
fetched=0
for attempt in {1..3}; do
    if git -C "$STACK_DIR" -c http.connectTimeout=20 -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=30 fetch --no-tags origin "$BRANCH"; then fetched=1; break; fi
    echo "FETCH_RETRY=$attempt/3"; sleep 3
done
[[ "$fetched" == 1 && "$(git -C "$STACK_DIR" rev-parse FETCH_HEAD)" == "$BACKEND_SHA" ]] || fail 'Requested commit is not the pinned branch tip'
git -C "$STACK_DIR" merge-base --is-ancestor "$BACKEND_BASE" "$BACKEND_SHA" || fail 'Wrong hotfix base'
expected=$'backend/app/Http/Controllers/Api/MenuController.php\nbackend/app/Services/Menus/FooterPolicyLinks.php\nbackend/tests/Feature/FooterPoliciesTest.php\nops/deploy-laptopplus-footer-hotfix.sh\nops/tests/footer-hotfix.test.mjs'
actual="$(git -C "$STACK_DIR" diff --name-only "$BACKEND_BASE" "$BACKEND_SHA" | LC_ALL=C sort)"
[[ "$actual" == "$(printf '%s\n' "$expected" | LC_ALL=C sort)" ]] || fail 'Diff is outside the reviewed footer-only scope'
echo 'HOTFIX_SCOPE=VERIFIED'
echo 'MIGRATION=NOT_RUN'
echo 'SEEDERS=NOT_RUN'
if [[ "${DEPLOY_AUDIT_ONLY:-0}" == 1 ]]; then
    echo 'AUDIT_STATUS=PASS'
    echo 'PRODUCTION_MUTATION=NONE'
    exit 0
fi

NEW_TAG="${BACKEND_SHA:0:12}"
CONTEXT="$(mktemp -d /tmp/laptopplus-footer.XXXXXX)"
git -C "$STACK_DIR" archive --format=tar "$BACKEND_SHA" \
    backend/app/Http/Controllers/Api/MenuController.php backend/app/Services/Menus/FooterPolicyLinks.php | tar -xf - -C "$CONTEXT"
printf '%s\n' \
    "FROM laptopplus-backend:${BACKEND_BASE:0:12}" \
    'COPY --chmod=0644 backend/app/Http/Controllers/Api/MenuController.php /var/www/html/app/Http/Controllers/Api/MenuController.php' \
    'COPY --chmod=0644 backend/app/Services/Menus/FooterPolicyLinks.php /var/www/html/app/Services/Menus/FooterPolicyLinks.php' \
    'RUN chmod 0755 app/Services/Menus' \
    'RUN php -l app/Http/Controllers/Api/MenuController.php && php -l app/Services/Menus/FooterPolicyLinks.php' \
    'RUN COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISABLE_NETWORK=1 composer dump-autoload --no-dev --classmap-authoritative --no-scripts --no-plugins --no-interaction' \
    'RUN php -r '\''require "vendor/autoload.php"; if (!class_exists("App\\Services\\Menus\\FooterPolicyLinks")) exit(1);'\''' \
    >"$CONTEXT/php.Dockerfile"
printf 'FROM laptopplus-backend-nginx:%s\n' "${BACKEND_BASE:0:12}" >"$CONTEXT/nginx.Dockerfile"
step build-two-small-offline-image-layers
docker build --network=none --pull=false --progress=plain --label "org.opencontainers.image.revision=$BACKEND_SHA" \
    --label "com.laptopplus.hotfix.base=$BACKEND_BASE" -f "$CONTEXT/php.Dockerfile" -t "laptopplus-backend:$NEW_TAG" "$CONTEXT"
docker build --network=none --pull=false --progress=plain --label "org.opencontainers.image.revision=$BACKEND_SHA" \
    --label "com.laptopplus.hotfix.base=$BACKEND_BASE" -f "$CONTEXT/nginx.Dockerfile" -t "laptopplus-backend-nginx:$NEW_TAG" "$CONTEXT"
# COPY --chmod applies to a newly created parent directory too. Root-only
# build lint is insufficient: prove the new class is readable by actual FPM.
docker run --rm --network none --user www-data --entrypoint php "laptopplus-backend:$NEW_TAG" -r '
    require "vendor/autoload.php";
    if (!class_exists("App\\Services\\Menus\\FooterPolicyLinks")) exit(1);
    echo "BACKEND_RUNTIME_READABILITY=PASS user=www-data\n";' || fail 'New PHP image is not readable by the FPM worker'
[[ "$(docker image inspect "laptopplus-backend:${BACKEND_BASE:0:12}" --format '{{.Id}}')" == "$PHP_BASE_ID" ]] || fail 'PHP base image changed'
[[ "$(docker image inspect "laptopplus-backend-nginx:${BACKEND_BASE:0:12}" --format '{{.Id}}')" == "$NGINX_BASE_ID" ]] || fail 'Nginx base image changed'

BACKUP_DIR="/www/backups/laptopplus.vn/footer-${NEW_TAG}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -p "$STACK_ENV" "$BACKUP_DIR/stack.env"
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend --format '{{.Name}} IMAGE={{.Config.Image}} ID={{.Image}}' >"$BACKUP_DIR/previous-images.txt"
echo "BACKUP_DIR=$BACKUP_DIR"
records_snapshot() {
    docker exec -i laptopplus-backend-php php -r '
        require "vendor/autoload.php"; $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $result = [];
        foreach (["pages", "menus", "menu_items", "settings"] as $table) {
            $rows = Illuminate\Support\Facades\DB::table($table)->orderBy("id")->get();
            $result[$table] = ["count" => $rows->count(), "sha256" => hash("sha256", $rows->toJson(JSON_UNESCAPED_UNICODE))];
        }
        echo json_encode($result, JSON_THROW_ON_ERROR);'
}
records_snapshot >"$BACKUP_DIR/records-before.json"
step switch-backend-only
temporary_env="$(mktemp "${STACK_ENV}.tmp.XXXXXX")"
cp -p "$STACK_ENV" "$temporary_env"
awk -v tag="$NEW_TAG" '/^APP_IMAGE_TAG=/ {print "APP_IMAGE_TAG=" tag; next} {print}' "$STACK_ENV" >"$temporary_env"
ENV_UPDATED=1
mv -f -- "$temporary_env" "$STACK_ENV"
"${COMPOSE[@]}" config --quiet
activate_backend || fail 'Backend activation/health failed'

http_check() {
    local url="$1" output="$2" expected="$3" status attempt
    for attempt in {1..3}; do
        status="$(curl -sS --connect-timeout 10 --max-time 30 -H 'Cache-Control: no-cache' -o "$output" -w '%{http_code}' "$url")" || status=000
        echo "HTTP_CHECK=$status EXPECTED=$expected URL=$url attempt=$attempt/3"
        [[ "$status" == "$expected" ]] && return 0
        sleep 3
    done
    return 1
}
validate_menu() {
    docker exec -i laptopplus-backend-php php -r '
        $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $walk = function ($items) use (&$walk) {
            foreach ($items as $item) {
                if (($item["url"] ?? null) === "/chinh-sach-gia" && trim($item["title"] ?? "") !== "") return true;
                if ($walk($item["children"] ?? [])) return true;
            }
            return false;
        };
        if (!is_array($data["items"] ?? null) || !$walk($data["items"])) exit(1);
        echo "FOOTER_API=PASS policy=/chinh-sach-gia\n";' <"$1"
}
validate_footer_ssr() {
    docker exec -i laptopplus-backend-php php -r '
        libxml_use_internal_errors(true);
        $dom = new DOMDocument(); $dom->loadHTML(stream_get_contents(STDIN));
        $xpath = new DOMXPath($dom);
        $links = $xpath->query("//footer//a[@href=\"/chinh-sach-gia\"]");
        if ($links->length !== 1 || trim($links->item(0)->textContent) === "") exit(1);
        echo "FOOTER_POLICY_SSR=PASS policy=/chinh-sach-gia\n";' <"$1"
}
wait_footer_ssr() {
    local url="$1" output="$2" attempt
    # The unchanged Nuxt homepage has a 300-second ISR TTL. Wait for normal
    # expiry on the real URL; do not purge cache or restart the storefront.
    for attempt in {1..25}; do
        if http_check "$url" "$output" 200 && validate_footer_ssr "$output"; then return 0; fi
        echo "FOOTER_CACHE_WAIT attempt=$attempt/25 url=$url interval=15s"
        sleep 15
    done
    return 1
}
step smoke-footer-on-real-homepage-and-policy
for endpoint in 'http://127.0.0.1:8901' "$API_ORIGIN"; do
    http_check "$endpoint/api/v1/menus/footer?probe=$NEW_TAG" "$CONTEXT/footer.json" 200 || fail 'Footer API failed'
    validate_menu "$CONTEXT/footer.json" || fail 'Published price policy is missing from footer API'
done
for endpoint in 'http://127.0.0.1:8902' "$PUBLIC_ORIGIN"; do
    for path in '/' '/chinh-sach-gia'; do
        wait_footer_ssr "$endpoint$path" "$CONTEXT/page.html" || fail 'Policy link is missing/duplicated in actual footer SSR after normal cache expiry'
    done
    http_check "$endpoint/release.json?probe=$NEW_TAG" "$CONTEXT/release.json" 200 || fail 'Frontend release check failed'
    docker exec -i laptopplus-backend-php php -r '
        $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        exit(($data["frontend_sha"] ?? null) === $argv[1] ? 0 : 1);' "$FRONTEND_SHA" <"$CONTEXT/release.json" || fail 'Frontend revision changed'
done
records_snapshot >"$BACKUP_DIR/records-after.json"
cmp -s "$BACKUP_DIR/records-before.json" "$BACKUP_DIR/records-after.json" || fail 'CMS/menu/settings changed; inspect concurrent edits before accepting'
[[ "$(docker inspect laptopplus-frontend --format '{{.Id}}|{{.Image}}')" == "$FRONTEND_BEFORE" ]] || fail 'Frontend container was changed'
hidden_slug="$(docker exec laptopplus-backend-php php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (App\Models\Page::query()->where("is_active", false)->orderBy("id")->get() as $page) {
        if (preg_match("/^[a-z0-9]+(?:-[a-z0-9]+)*$/", $page->slug)) { echo $page->slug; break; }
    }')"
if [[ -n "$hidden_slug" ]]; then
    http_check "$API_ORIGIN/api/v1/pages/$hidden_slug" "$CONTEXT/hidden.json" 404 || fail 'Hidden policy exposed by API'
    http_check "$PUBLIC_ORIGIN/$hidden_slug" "$CONTEXT/hidden.html" 404 || fail 'Hidden policy exposed by storefront'
    echo 'HIDDEN_PAGE=404_VERIFIED'
fi
ENV_UPDATED=0; SUCCEEDED=1
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"
echo 'FRONTEND_RECREATED=NO'
echo 'CMS_MENU_SETTINGS=UNCHANGED'
echo 'DATABASE_CHANGED=NO'
echo 'MIGRATION=NOT_RUN'
echo 'SEEDERS=NOT_RUN'
echo 'LAPTOPPLUS_FOOTER_HOTFIX_COMPLETE=YES'
