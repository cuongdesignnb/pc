#!/usr/bin/env bash

set -Eeuo pipefail

# Backport public CMS pages to the audited running release, not current main.
# No migrations, seeders, database restores, settings changes or cache purges.
BACKEND_SHA="${1:-}"
FRONTEND_SHA="${2:-}"
BACKEND_BASE='8b0b58b3ac4edb3cdc2e14a75cc1b774c2f9b49a'
FRONTEND_BASE='0929f0a1975b5bfbafa115e1d431e0195f4a78a5'
BACKEND_BRANCH='codex/laptopplus-public-pages-backend'
FRONTEND_BRANCH='codex/laptopplus-public-pages-frontend'
PUBLIC_ORIGIN='https://laptopplus.vn'
API_ORIGIN='https://admin.laptopplus.vn'
SCRIPT_URL="https://raw.githubusercontent.com/cuongdesignnb/pc/${BACKEND_SHA}/ops/deploy-laptopplus-public-pages-hotfix.sh"

if [[ ! "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ || ! "$FRONTEND_SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'DEPLOY_ERROR=Pass full backend and frontend commit SHAs' >&2
    exit 1
fi
if [[ "${DEPLOY_SCRIPT_URL:-$SCRIPT_URL}" != "$SCRIPT_URL" ]]; then
    echo 'DEPLOY_ERROR=The helper URL must be pinned to the backend hotfix commit' >&2
    exit 1
fi
SCRIPT_PATH="${BASH_SOURCE[0]}"
[[ "$SCRIPT_PATH" == /* ]] || SCRIPT_PATH="$PWD/$SCRIPT_PATH"
[[ -f "$SCRIPT_PATH" ]] || { echo 'DEPLOY_ERROR=Download the helper to a file first' >&2; exit 1; }

if [[ "${DEPLOY_DETACH:-0}" == 1 && "${DEPLOY_DAEMONIZED:-0}" != 1 ]]; then
    LOG="${DEPLOY_LOG:-/tmp/laptopplus-public-pages-$(date -u +%Y%m%d-%H%M%S).log}"
    umask 077
    command -v setsid >/dev/null 2>&1 || { echo 'DEPLOY_ERROR=setsid is required' >&2; exit 1; }
    DEPLOY_DAEMONIZED=1 DEPLOY_DETACH=0 setsid nohup bash "$SCRIPT_PATH" \
        "$BACKEND_SHA" "$FRONTEND_SHA" >"$LOG" 2>&1 </dev/null &
    echo "DEPLOY_PID=$!"
    echo "DEPLOY_LOG=$LOG"
    exit 0
fi

STACK_DIR='/www/docker/laptopplus.vn'
FRONTEND_REPO='/www/wwwroot/pcfrontend'
BUILD_CONFIG="$STACK_DIR/deploy/production"
FRONTEND_DOCKERFILE='/www/docker/laptopplus-frontend/Dockerfile.production'
STACK_ENV="$BUILD_CONFIG/stack.env"
COMPOSE_FILE="$STACK_DIR/docker-compose.production.yml"
LOCK='/tmp/laptopplus-production-deploy.lock'
CONTEXT=''
BACKUP_DIR=''
ENV_UPDATED=0
SUCCEEDED=0
COMPOSE=(docker compose --env-file "$STACK_ENV" -f "$COMPOSE_FILE")
umask 077

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
on_exit() {
    local rc=$?
    trap - EXIT
    if [[ "$rc" != 0 ]]; then
        echo "DEPLOY_ERROR=process_exit_$rc" >&2
        if [[ "$ENV_UPDATED" == 1 ]]; then
            echo 'ROLLBACK=START' >&2
            if cp -p "$BACKUP_DIR/stack.env" "$STACK_ENV" \
                && "${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-php backend-nginx queue scheduler frontend \
                && wait_healthy laptopplus-backend-php && wait_healthy laptopplus-backend-nginx && wait_healthy laptopplus-frontend; then
                echo 'ROLLBACK=COMPLETE' >&2
            else
                echo "ROLLBACK=INCOMPLETE BACKUP_DIR=$BACKUP_DIR" >&2
            fi
        fi
        echo 'DEPLOY_STATUS=FAILED' >&2
    elif [[ "$SUCCEEDED" == 1 ]]; then
        echo 'DEPLOY_STATUS=SUCCESS'
    fi
    # This target is always a private mktemp child of the exact /tmp prefix.
    if [[ "$CONTEXT" == /tmp/laptopplus-public-pages.* && -d "$CONTEXT" ]]; then
        rm -rf -- "$CONTEXT"
    fi
    exit "$rc"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap '' HUP

for tool in git docker curl tar awk sed grep sort cp mv cmp mktemp flock find date sleep realpath; do
    command -v "$tool" >/dev/null 2>&1 || fail "Missing command: $tool"
done
[[ "$(realpath "$STACK_DIR")" == "$STACK_DIR" ]] || fail 'Unexpected stack directory'
for file in "$BUILD_CONFIG/backend.Dockerfile" "$BUILD_CONFIG/backend-nginx.conf" "$FRONTEND_DOCKERFILE" "$STACK_ENV" "$COMPOSE_FILE"; do
    [[ -f "$file" ]] || fail "Missing file: $file"
done
exec 9>"$LOCK"
flock -n 9 || fail 'Another LaptopPlus production deploy is running'

check_repo() {
    local directory="$1" repo="$2" origin
    origin="$(git -C "$directory" remote get-url origin)"
    case "$origin" in
        "https://github.com/cuongdesignnb/$repo"|"https://github.com/cuongdesignnb/$repo.git"|"git@github.com:cuongdesignnb/$repo.git"|"ssh://git@github.com/cuongdesignnb/$repo.git") ;;
        *) fail "Unexpected repository in $directory" ;;
    esac
}
check_repo "$STACK_DIR" pc
check_repo "$FRONTEND_REPO" pcfrontend
read_tag() {
    [[ "$(grep -c "^$1=" "$STACK_ENV" || true)" == 1 ]] || fail "Expected exactly one $1"
    sed -n "s/^$1=//p" "$STACK_ENV"
}
OLD_BACKEND="$(read_tag APP_IMAGE_TAG)"
OLD_FRONTEND="$(read_tag FRONTEND_IMAGE_TAG)"
[[ "$OLD_BACKEND" == "${BACKEND_BASE:0:12}" ]] || fail 'Backend is no longer the audited 8b0b58b3ac4e release; audit before deployment'
[[ "$OLD_FRONTEND" == "${FRONTEND_BASE:0:7}" ]] || fail 'Storefront is no longer the audited 0929f0a release; audit before deployment'
for entry in 'backend-php:laptopplus-backend' 'backend-nginx:laptopplus-backend-nginx' 'frontend:laptopplus-frontend'; do
    service="${entry%%:*}"; image="${entry#*:}"; tag="$OLD_BACKEND"
    [[ "$service" == frontend ]] && tag="$OLD_FRONTEND"
    actual="$(docker inspect "laptopplus-$service" --format '{{.Config.Image}}')"
    [[ "$actual" == "$image:$tag" ]] || fail "Running $service image differs from stack.env"
done

fetch_branch() {
    local directory="$1" branch="$2" sha="$3" attempt
    for attempt in {1..3}; do
        if git -C "$directory" -c http.connectTimeout=20 -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=30 \
            fetch --no-tags origin "$branch"; then
            [[ "$(git -C "$directory" rev-parse FETCH_HEAD)" == "$sha" ]] || fail "Branch $branch tip differs from requested SHA"
            return 0
        fi
        echo "FETCH_RETRY branch=$branch attempt=$attempt/3"
        sleep 3
    done
    fail "Could not fetch $branch"
}
step audit-pinned-source
fetch_branch "$STACK_DIR" "$BACKEND_BRANCH" "$BACKEND_SHA"
fetch_branch "$FRONTEND_REPO" "$FRONTEND_BRANCH" "$FRONTEND_SHA"
git -C "$STACK_DIR" merge-base --is-ancestor "$BACKEND_BASE" "$BACKEND_SHA" || fail 'Backend is not based on the running release'
git -C "$FRONTEND_REPO" merge-base --is-ancestor "$FRONTEND_BASE" "$FRONTEND_SHA" || fail 'Frontend is not based on the running release'

backend_files=$'backend/app/Http/Controllers/Admin/PageController.php\nbackend/app/Http/Controllers/Api/PageController.php\nbackend/app/Services/Seo/SitemapExportService.php\nbackend/app/Services/Seo/SlugRedirectService.php\nbackend/resources/js/Pages/Admin/Pages/Index.vue\nbackend/routes/api.php\nbackend/tests/Feature/PublicPagesTest.php\nbackend/tests/ui/admin-pages-index.test.mjs\nops/deploy-laptopplus-public-pages-hotfix.sh\nops/tests/public-pages-hotfix.test.mjs'
frontend_files=$'app/components/category/CategoryListingPage.vue\napp/components/category/CategoryToolbar.vue\napp/components/content/StaticContentPage.vue\napp/pages/[category]/index.vue\napp/types/public-page.ts\napp/utils/publicPages.ts\nscripts/run-seo-regression.mjs\nscripts/start-seo-fixture-api.mjs\ntests/seo/fixtures/api-fixtures.mjs\ntests/seo/public-pages.spec.ts'
check_scope() {
    local directory="$1" base="$2" target="$3" expected="$4" actual
    actual="$(git -C "$directory" diff --name-only "$base" "$target" | LC_ALL=C sort)"
    [[ "$actual" == "$(printf '%s\n' "$expected" | LC_ALL=C sort)" ]] || fail 'Changed files do not match the reviewed hotfix scope'
}
check_scope "$STACK_DIR" "$BACKEND_BASE" "$BACKEND_SHA" "$backend_files"
check_scope "$FRONTEND_REPO" "$FRONTEND_BASE" "$FRONTEND_SHA" "$frontend_files"
echo 'HOTFIX_SCOPE=VERIFIED'
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"
echo 'MIGRATION=NOT_RUN'
echo 'SEEDERS=NOT_RUN'
if [[ "${DEPLOY_AUDIT_ONLY:-0}" == 1 ]]; then
    echo 'AUDIT_STATUS=PASS'
    echo 'PRODUCTION_MUTATION=NONE'
    exit 0
fi

CONTEXT="$(mktemp -d /tmp/laptopplus-public-pages.XXXXXX)"
mkdir -p "$CONTEXT/backend" "$CONTEXT/frontend"
NEW_BACKEND="${BACKEND_SHA:0:12}"
NEW_FRONTEND="${FRONTEND_SHA:0:12}"
step prepare-immutable-build-contexts
git -C "$STACK_DIR" archive --format=tar "$BACKEND_SHA" | tar -xf - -C "$CONTEXT/backend"
git -C "$FRONTEND_REPO" archive --format=tar "$FRONTEND_SHA" | tar -xf - -C "$CONTEXT/frontend"
find "$CONTEXT" -type f -name '.env*' ! -name '.env.example' -delete
mkdir -p "$CONTEXT/backend/deploy/production"
# Copy only build configuration, never stack.env/credentials into an image.
cp "$BUILD_CONFIG/backend.Dockerfile" "$CONTEXT/backend/deploy/production/backend.Dockerfile"
cp "$BUILD_CONFIG/backend-nginx.conf" "$CONTEXT/backend/deploy/production/backend-nginx.conf"
# Nuxt's existing production config reads these PUBLIC values at build time.
# Keep the running site's runtime appName; do not copy a tracked .env.server.
APP_NAME="$(docker exec laptopplus-frontend node -e 'process.stdout.write(process.env.NUXT_PUBLIC_APP_NAME || "")')"
[[ "$APP_NAME" != *$'\n'* && "$APP_NAME" != *$'\r'* ]] || fail 'Runtime public appName contains a line break'
printf 'NUXT_PUBLIC_SITE_URL=%s\nNUXT_PUBLIC_APP_NAME=%s\n' "$PUBLIC_ORIGIN" "$APP_NAME" >"$CONTEXT/frontend/.env.server"
printf '{"frontend_sha":"%s","frontend_tag":"%s"}\n' "$FRONTEND_SHA" "$NEW_FRONTEND" >"$CONTEXT/frontend/public/release.json"

step build-backend-and-admin-assets
docker build --progress=plain --target php --label "org.opencontainers.image.revision=$BACKEND_SHA" \
    -f "$CONTEXT/backend/deploy/production/backend.Dockerfile" -t "laptopplus-backend:$NEW_BACKEND" "$CONTEXT/backend"
docker build --progress=plain --target nginx --label "org.opencontainers.image.revision=$BACKEND_SHA" \
    -f "$CONTEXT/backend/deploy/production/backend.Dockerfile" -t "laptopplus-backend-nginx:$NEW_BACKEND" "$CONTEXT/backend"
step build-public-pages-storefront
docker build --progress=plain --label "org.opencontainers.image.revision=$FRONTEND_SHA" \
    --build-arg NUXT_PUBLIC_API_BASE=/api/v1 --build-arg NUXT_API_PROXY_TARGET=http://backend-nginx \
    -f "$FRONTEND_DOCKERFILE" -t "laptopplus-frontend:$NEW_FRONTEND" "$CONTEXT/frontend"

BACKUP_DIR="/www/backups/laptopplus.vn/public-pages-${NEW_BACKEND}-${NEW_FRONTEND}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -p "$STACK_ENV" "$BACKUP_DIR/stack.env"
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend \
    --format '{{.Name}} IMAGE={{.Config.Image}} ID={{.Image}}' >"$BACKUP_DIR/previous-images.txt"
echo "BACKUP_DIR=$BACKUP_DIR"
page_snapshot() {
    docker exec -i laptopplus-backend-php php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo App\Models\Page::query()->orderBy("id")->get()->map(fn ($page) => [
            "id" => $page->id, "slug" => $page->slug, "active" => $page->is_active,
            "hash" => hash("sha256", json_encode($page->getAttributes(), JSON_UNESCAPED_UNICODE)),
        ])->toJson(JSON_UNESCAPED_UNICODE);'
}
page_snapshot >"$BACKUP_DIR/pages-before.json"
# The sample verified in admin is ID 3 / chinh-sach-gia / Hiện. Do not publish it here.
docker exec -i laptopplus-backend-php php -r '
    $pages = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    foreach ($pages as $page) if ($page["id"] === 3 && $page["slug"] === "chinh-sach-gia" && $page["active"] === true) exit(0);
    fwrite(STDERR, "Audited published policy has changed; verify before deploying.\n"); exit(1);' <"$BACKUP_DIR/pages-before.json"

step switch-reviewed-image-tags
temporary_env="$(mktemp "${STACK_ENV}.tmp.XXXXXX")"
cp -p "$STACK_ENV" "$temporary_env"
awk -v backend="$NEW_BACKEND" -v frontend="$NEW_FRONTEND" '
    /^APP_IMAGE_TAG=/ { print "APP_IMAGE_TAG=" backend; next }
    /^FRONTEND_IMAGE_TAG=/ { print "FRONTEND_IMAGE_TAG=" frontend; next }
    { print }
' "$STACK_ENV" >"$temporary_env"
ENV_UPDATED=1
mv -f -- "$temporary_env" "$STACK_ENV"
"${COMPOSE[@]}" config --quiet
"${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-php
wait_healthy laptopplus-backend-php || fail 'Backend PHP is not healthy'
"${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-nginx queue scheduler
wait_healthy laptopplus-backend-nginx || fail 'Backend Nginx is not healthy'
"${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate frontend
wait_healthy laptopplus-frontend || fail 'Storefront is not healthy'

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
validate_api_page() {
    docker exec -i laptopplus-backend-php php -r '
        $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $page = $data["page"] ?? [];
        if (($page["id"] ?? null) !== 3 || ($page["canonical_path"] ?? null) !== "/chinh-sach-gia" || !is_string($page["body"] ?? null) || trim($page["title"] ?? "") === "") exit(1);
        echo "PUBLIC_PAGE_API=PASS id=3\n";' <"$1"
}
validate_ssr_page() {
    docker exec -i laptopplus-backend-php php -r '
        libxml_use_internal_errors(true);
        $dom = new DOMDocument(); $dom->loadHTML(stream_get_contents(STDIN));
        $xpath = new DOMXPath($dom);
        $h1 = $xpath->query("//h1");
        $title = $xpath->query("//head/title");
        $canonical = $xpath->query("//link[@rel=\"canonical\"]/@href");
        $body = $xpath->query("//div[contains(concat(\" \",normalize-space(@class),\" \"),\" static-page-body \")]");
        if ($h1->length !== 1 || trim($h1->item(0)->textContent) !== trim(base64_decode($argv[2])) || $title->length !== 1 || trim($title->item(0)->textContent) === "" || $body->length !== 1 || $canonical->length !== 1 || $canonical->item(0)->nodeValue !== "https://laptopplus.vn/chinh-sach-gia") exit(1);
        $expected = new DOMDocument();
        $expected->loadHTML("<?xml encoding=\"UTF-8\"><div id=\"expected-body\">".base64_decode($argv[1])."</div>");
        $normalize = fn ($value) => preg_replace("/\\s+/u", " ", trim($value));
        if ($normalize($body->item(0)->textContent) !== $normalize($expected->getElementById("expected-body")->textContent)) exit(1);
        echo "PUBLIC_PAGE_SSR=PASS slug=chinh-sach-gia title=matches_API body=matches_API\n";' "$EXPECTED_BODY" "$EXPECTED_TITLE" <"$1"
}

step smoke-real-policy-and-release
http_check 'http://127.0.0.1:8901/healthz' "$CONTEXT/health" 200 || fail 'Backend health endpoint failed'
http_check "http://127.0.0.1:8901/api/v1/pages/chinh-sach-gia?probe=$NEW_BACKEND" "$CONTEXT/api-local.json" 200 || fail 'Local public Pages API failed'
validate_api_page "$CONTEXT/api-local.json" || fail 'Invalid local page data'
http_check "$API_ORIGIN/api/v1/pages/chinh-sach-gia?probe=$NEW_BACKEND" "$CONTEXT/api-public.json" 200 || fail 'Public Pages API failed'
validate_api_page "$CONTEXT/api-public.json" || fail 'Invalid public page data'
EXPECTED_BODY="$(docker exec -i laptopplus-backend-php php -r '
    $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo base64_encode($data["page"]["body"]);' <"$CONTEXT/api-public.json")"
EXPECTED_TITLE="$(docker exec -i laptopplus-backend-php php -r '
    $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo base64_encode($data["page"]["title"]);' <"$CONTEXT/api-public.json")"
for endpoint in 'http://127.0.0.1:8902' "$PUBLIC_ORIGIN"; do
    # Check the real URL, not just a cache-busting probe that hides stale 404s.
    http_check "$endpoint/chinh-sach-gia" "$CONTEXT/page.html" 200 || fail 'Published storefront policy failed'
    validate_ssr_page "$CONTEXT/page.html" || fail 'Policy SSR body/H1/canonical failed'
    http_check "$endpoint/release.json?probe=$NEW_FRONTEND" "$CONTEXT/release.json" 200 || fail 'Frontend release endpoint failed'
    docker exec -i laptopplus-backend-php php -r '
        $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        exit(($data["frontend_sha"] ?? "") === $argv[1] ? 0 : 1);' "$FRONTEND_SHA" <"$CONTEXT/release.json" || fail 'Storefront is serving a different release'
done
page_snapshot >"$BACKUP_DIR/pages-after.json"
cmp -s "$BACKUP_DIR/pages-before.json" "$BACKUP_DIR/pages-after.json" || fail 'CMS records changed during deployment; inspect before accepting'
hidden_slug="$(docker exec -i laptopplus-backend-php php -r '
    foreach (json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR) as $page) {
        if (!$page["active"] && preg_match("/^[a-z0-9]+(?:-[a-z0-9]+)*$/", $page["slug"])) { echo $page["slug"]; break; }
    }' <"$BACKUP_DIR/pages-after.json")"
if [[ -n "$hidden_slug" ]]; then
    http_check "$API_ORIGIN/api/v1/pages/$hidden_slug?probe=$NEW_BACKEND" "$CONTEXT/hidden.json" 404 || fail 'A hidden policy is being exposed by the API'
    http_check "$PUBLIC_ORIGIN/$hidden_slug?probe=$NEW_FRONTEND" "$CONTEXT/hidden.html" 404 || fail 'A hidden policy is being exposed by the storefront'
    echo 'HIDDEN_PAGE=404_VERIFIED'
fi
wait_healthy laptopplus-queue || fail 'Queue is not healthy'
wait_healthy laptopplus-scheduler || fail 'Scheduler is not healthy'
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend \
    --format '{{.Name}} IMAGE={{.Config.Image}} STATUS={{.State.Status}} HEALTH={{if .State.Health}}{{.State.Health.Status}}{{end}}'
ENV_UPDATED=0
SUCCEEDED=1
echo 'CMS_RECORDS=UNCHANGED'
echo 'DATABASE_CHANGED=NO'
echo 'MIGRATION=NOT_RUN'
echo 'SEEDERS=NOT_RUN'
echo 'LAPTOPPLUS_PUBLIC_PAGES_HOTFIX_COMPLETE=YES'
