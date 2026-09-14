#!/usr/bin/env bash

set -Eeuo pipefail

# One-command, data-preserving deployment for hpcomvietnam.vn.
#
# Runtime layout on the aaPanel host:
#   backend  /www/wwwroot/admin.hpcomvietnam.vn  (Laravel + PHP-FPM)
#   frontend /www/wwwroot/hpcomvietnam.vn        (Nuxt output + PM2)
#
# The script deliberately deploys immutable commits from the two Git
# repositories. It never replaces either .env file, never deletes storage or
# public upload directories, and creates a database/code/output backup before
# the first production mutation.
#
# Default invocation:
#   curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-hpcom.sh | DEPLOY_DETACH=1 bash
#
# Optional pinned commits:
#   ... | DEPLOY_DETACH=1 bash -s -- BACKEND_SHA FRONTEND_SHA
#
# Migration is enabled by default after the database backup. Seeders are not
# run by default. Use RUN_MIGRATIONS=0 for a code-only deploy, or explicitly
# set RUN_SEEDERS=1 and SEEDERS="SeederA SeederB" when a reviewed data change
# is required.

requested_backend_sha="${1:-}"
requested_frontend_sha="${2:-}"
DEPLOY_SCRIPT_URL="${DEPLOY_SCRIPT_URL:-https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-hpcom.sh}"

# aaPanel's browser terminal can lose its websocket while a build is running.
# Keep the one-command workflow, but let the actual run continue in a new
# session and write progress to a durable log.
if [[ "${DEPLOY_DETACH:-0}" == "1" && "${DEPLOY_DAEMONIZED:-0}" != "1" ]]; then
    deploy_log="${DEPLOY_LOG:-/tmp/hpcom-production-deploy-$(date -u +%Y%m%d-%H%M%S).log}"
    printf -v url_arg '%q' "$DEPLOY_SCRIPT_URL"
    printf -v backend_sha_arg '%q' "$requested_backend_sha"
    printf -v frontend_sha_arg '%q' "$requested_frontend_sha"
    child_command="curl --retry 5 --retry-delay 5 --connect-timeout 20 --max-time 120 -fsSL ${url_arg} | DEPLOY_DAEMONIZED=1 DEPLOY_DETACH=0 bash -s -- ${backend_sha_arg} ${frontend_sha_arg}"

    if command -v setsid >/dev/null 2>&1; then
        setsid nohup bash -lc "$child_command" >"$deploy_log" 2>&1 < /dev/null &
    else
        nohup bash -lc "$child_command" >"$deploy_log" 2>&1 < /dev/null &
    fi

    echo "DEPLOY_PID=$!"
    echo "DEPLOY_LOG=$deploy_log"
    # Consume the remaining curl stream before returning. This prevents the
    # upstream curl from failing with exit 23 after the child is detached.
    while IFS= read -r; do :; done
    exit 0
fi

BACKEND_SOURCE_REPO="${BACKEND_SOURCE_REPO:-/www/docker/laptopplus.vn}"
FRONTEND_SOURCE_REPO="${FRONTEND_SOURCE_REPO:-/www/wwwroot/pcfrontend}"
BACKEND_DIR="${BACKEND_DIR:-/www/wwwroot/admin.hpcomvietnam.vn}"
FRONTEND_DIR="${FRONTEND_DIR:-/www/wwwroot/hpcomvietnam.vn}"
PM2_APP="${PM2_APP:-hpcom}"
API_ORIGIN="${API_ORIGIN:-https://admin.hpcomvietnam.vn}"
PUBLIC_ORIGIN="${PUBLIC_ORIGIN:-https://hpcomvietnam.vn}"
PUBLIC_RELEASE_PATH="${PUBLIC_RELEASE_PATH:-/release.json}"
FRONTEND_HEALTH_PATH="${FRONTEND_HEALTH_PATH:-/}"
BACKUP_ROOT="${BACKUP_ROOT:-/www/backups/hpcomvietnam.vn}"
DEPLOY_LOCK="${DEPLOY_LOCK:-/tmp/hpcom-production-deploy.lock}"
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NODE_BIN="${NODE_BIN:-/www/server/nodejs/v22.12.0/bin/node}"
NPM_BIN="${NPM_BIN:-/www/server/nodejs/v22.12.0/bin/npm}"
PM2_BIN="${PM2_BIN:-pm2}"
RSYNC_BIN="${RSYNC_BIN:-rsync}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
RUN_SEEDERS="${RUN_SEEDERS:-0}"
SEEDERS="${SEEDERS:-}"
SKIP_PUBLIC_CHECK="${SKIP_PUBLIC_CHECK:-0}"
PHP_FPM_RELOAD_COMMAND="${PHP_FPM_RELOAD_COMMAND:-}"

API_ORIGIN="${API_ORIGIN%/}"
PUBLIC_ORIGIN="${PUBLIC_ORIGIN%/}"
PUBLIC_RELEASE_PATH="/${PUBLIC_RELEASE_PATH#/}"
FRONTEND_HEALTH_PATH="/${FRONTEND_HEALTH_PATH#/}"

CURRENT_STEP=initialization
ERROR_REPORTED=0
DEPLOY_SUCCEEDED=0
ROLLBACK_DONE=0
BACKEND_SYNC_STARTED=0
FRONTEND_OUTPUT_SWAPPED=0
FRONTEND_OUTPUT_OLD_MOVED=0
MIGRATION_STATUS=NOT_RUN
DATABASE_CHANGED=NO
BACKEND_STAGE_DIR=
FRONTEND_STAGE_DIR=
BACKUP_DIR=
BACKEND_BEFORE_DIR=
FRONTEND_OUTPUT_BACKUP=
FRONTEND_OUTPUT_FAILED=
MYSQL_CNF=
LOCK_FD=9

BACKEND_EXCLUDES=(
    --exclude=.env
    --exclude='.env.*'
    --exclude=.git/
    --exclude=storage/
    --exclude=public/storage
    --exclude=.htaccess
    --exclude=.user.ini
    --exclude=.well-known/
    --exclude=public/.well-known/
    --exclude=node_modules/
    --exclude=bootstrap/cache/
)

fail() {
    ERROR_REPORTED=1
    echo "DEPLOY_ERROR=$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

require_executable() {
    test -x "$1" || fail "Missing executable: $1"
}

require_file() {
    test -f "$1" || fail "Required file not found: $1"
}

require_directory() {
    test -d "$1" || fail "Required directory not found: $1"
}

fetch_main_with_retry() {
    local repository="$1"
    local attempt

    for attempt in 1 2 3 4 5; do
        if git -C "$repository" \
            -c http.connectTimeout=20 \
            -c http.lowSpeedLimit=1000 \
            -c http.lowSpeedTime=30 \
            fetch --no-tags --prune origin main; then
            return 0
        fi

        if [ "$attempt" -lt 5 ]; then
            echo "FETCH_RETRY=$attempt/5 repository=$repository" >&2
            sleep 5
        fi
    done

    return 1
}

step() {
    printf '\n[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
}

cleanup() {
    [ -z "$BACKEND_STAGE_DIR" ] || rm -rf -- "$BACKEND_STAGE_DIR" || true
    [ -z "$FRONTEND_STAGE_DIR" ] || rm -rf -- "$FRONTEND_STAGE_DIR" || true
    [ -z "$MYSQL_CNF" ] || rm -f -- "$MYSQL_CNF" || true
}

reload_php_fpm() {
    if [ -n "$PHP_FPM_RELOAD_COMMAND" ]; then
        echo "PHP_FPM_RELOAD=custom"
        bash -lc "$PHP_FPM_RELOAD_COMMAND"
        return 0
    fi

    # aaPanel commonly exposes the PHP-FPM init script under this name. Only
    # touch a service when it is present; otherwise the public smoke check is
    # the gate and no guessed service is restarted.
    local init_script
    for init_script in /etc/init.d/php-fpm-83 /etc/init.d/php83-php-fpm; do
        if [ -x "$init_script" ]; then
            echo "PHP_FPM_RELOAD=$init_script"
            "$init_script" reload 2>/dev/null || "$init_script" restart
            return 0
        fi
    done

    if command -v systemctl >/dev/null 2>&1; then
        local unit
        for unit in php8.3-fpm php-fpm-83 php83-php-fpm; do
            if systemctl is-active --quiet "$unit" 2>/dev/null; then
                echo "PHP_FPM_RELOAD=$unit"
                systemctl reload "$unit"
                return 0
            fi
        done
    fi

    echo "PHP_FPM_RELOAD=NOT_FOUND"
}

rollback() {
    [ "$ROLLBACK_DONE" -eq 0 ] || return 0
    [ "$BACKEND_SYNC_STARTED" -eq 1 ] || [ "$FRONTEND_OUTPUT_SWAPPED" -eq 1 ] || [ "$FRONTEND_OUTPUT_OLD_MOVED" -eq 1 ] || return 0

    ROLLBACK_DONE=1
    echo "ROLLBACK=START" >&2

    # Restore application code only. All data/config paths are excluded from
    # both the backup and restore operations, so rollback cannot overwrite
    # .env, storage, uploads, aaPanel files, or public/.well-known.
    if [ "$BACKEND_SYNC_STARTED" -eq 1 ] && [ -n "$BACKEND_BEFORE_DIR" ] && [ -d "$BACKEND_BEFORE_DIR" ]; then
        "$RSYNC_BIN" -a --delete "${BACKEND_EXCLUDES[@]}" \
            "$BACKEND_BEFORE_DIR/" "$BACKEND_DIR/" || true
        echo "ROLLBACK_BACKEND=RESTORED" >&2
    fi

    if [ "$FRONTEND_OUTPUT_OLD_MOVED" -eq 1 ] && [ -n "$FRONTEND_OUTPUT_BACKUP" ] && [ -e "$FRONTEND_OUTPUT_BACKUP" ]; then
        if [ -e "$FRONTEND_DIR/.output" ]; then
            FRONTEND_OUTPUT_FAILED="$BACKUP_DIR/frontend-output-failed"
            mv -- "$FRONTEND_DIR/.output" "$FRONTEND_OUTPUT_FAILED" || true
        fi
        mv -- "$FRONTEND_OUTPUT_BACKUP" "$FRONTEND_DIR/.output" || true
        echo "ROLLBACK_FRONTEND_OUTPUT=RESTORED" >&2
        "$PM2_BIN" reload "$PM2_APP" --update-env >/dev/null 2>&1 || true
    fi

    # A database dump is intentionally retained rather than automatically
    # restored. Restoring a dump could destroy orders or settings created by a
    # concurrent process. The log always exposes the exact recovery directory.
    if [ "$DATABASE_CHANGED" != "NO" ]; then
        echo "ROLLBACK_DATABASE=NOT_AUTOMATIC; BACKUP_DIR=$BACKUP_DIR" >&2
    fi
    echo "ROLLBACK=COMPLETE" >&2
}

on_exit() {
    local exit_code=$?
    if [ "$exit_code" -ne 0 ]; then
        [ "$ERROR_REPORTED" -eq 1 ] || \
            echo "DEPLOY_ERROR=step=$CURRENT_STEP exit_code=$exit_code" >&2
        rollback || true
        echo "DEPLOY_STATUS=FAILED" >&2
    elif [ "$DEPLOY_SUCCEEDED" -eq 1 ]; then
        echo "DEPLOY_STATUS=SUCCESS"
    fi
    cleanup
    exit "$exit_code"
}

trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# aaPanel may send SIGHUP when its browser terminal reconnects.
trap '' HUP

CURRENT_STEP=preflight
for command in git curl rsync mysqldump tar awk sed grep mktemp flock sha256sum date sleep bash; do
    require_command "$command"
done
require_executable "$PHP_BIN"
require_executable "$NODE_BIN"
require_executable "$NPM_BIN"
require_command "$COMPOSER_BIN"
require_command "$PM2_BIN"
"$PM2_BIN" describe "$PM2_APP" >/dev/null 2>&1 \
    || fail "PM2 app is not registered: $PM2_APP"

[[ "$RUN_MIGRATIONS" == 0 || "$RUN_MIGRATIONS" == 1 ]] \
    || fail "RUN_MIGRATIONS must be 0 or 1"
[[ "$RUN_SEEDERS" == 0 || "$RUN_SEEDERS" == 1 ]] \
    || fail "RUN_SEEDERS must be 0 or 1"
if [ "$RUN_SEEDERS" = "1" ]; then
    test -n "$SEEDERS" || fail 'RUN_SEEDERS=1 requires SEEDERS="SeederA SeederB"'
fi

require_directory "$BACKEND_SOURCE_REPO"
require_directory "$FRONTEND_SOURCE_REPO"
require_directory "$BACKEND_DIR"
require_directory "$FRONTEND_DIR"
require_file "$BACKEND_DIR/.env"
require_file "$FRONTEND_DIR/.env"
require_file "$FRONTEND_DIR/.output/server/index.mjs"

test "$(git -C "$BACKEND_SOURCE_REPO" rev-parse --is-inside-work-tree)" = true \
    || fail "Backend source path is not a Git worktree: $BACKEND_SOURCE_REPO"
test "$(git -C "$FRONTEND_SOURCE_REPO" rev-parse --is-inside-work-tree)" = true \
    || fail "Frontend source path is not a Git worktree: $FRONTEND_SOURCE_REPO"

backend_remote="$(git -C "$BACKEND_SOURCE_REPO" remote get-url origin 2>/dev/null || true)"
frontend_remote="$(git -C "$FRONTEND_SOURCE_REPO" remote get-url origin 2>/dev/null || true)"
[[ "$backend_remote" =~ github\.com/cuongdesignnb/pc(\.git)?$ ]] \
    || fail "Unexpected backend origin: $backend_remote"
[[ "$frontend_remote" =~ github\.com/cuongdesignnb/pcfrontend(\.git)?$ ]] \
    || fail "Unexpected frontend origin: $frontend_remote"

# The PHP/NPM paths may be overridden for a different aaPanel installation,
# but never silently fall back to an incompatible runtime.
test -x "$PHP_BIN" || fail "PHP binary is not executable: $PHP_BIN"
test -x "$NODE_BIN" || fail "Node binary is not executable: $NODE_BIN"
test -x "$NPM_BIN" || fail "NPM binary is not executable: $NPM_BIN"
php_version="$($PHP_BIN -r 'echo PHP_VERSION;')"
node_version="$($NODE_BIN --version)"
php_major="${php_version%%.*}"
php_minor="${php_version#*.}"
php_minor="${php_minor%%.*}"
(( php_major > 8 || (php_major == 8 && php_minor >= 2) )) \
    || fail "PHP >= 8.2 is required; found $php_version"
echo "RUNTIME_PHP=$php_version"
echo "RUNTIME_NODE=$node_version"

exec 9>"$DEPLOY_LOCK"
flock -n 9 || fail "Another HPCom deploy is already running: $DEPLOY_LOCK"

CURRENT_STEP=fetch_sources
step "Fetching backend and frontend main"
fetch_main_with_retry "$BACKEND_SOURCE_REPO" \
    || fail "Could not fetch backend main"
fetch_main_with_retry "$FRONTEND_SOURCE_REPO" \
    || fail "Could not fetch frontend main"

BACKEND_SHA="${requested_backend_sha:-$(git -C "$BACKEND_SOURCE_REPO" rev-parse origin/main)}"
FRONTEND_SHA="${requested_frontend_sha:-$(git -C "$FRONTEND_SOURCE_REPO" rev-parse origin/main)}"
[[ "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Backend SHA must be 40 hexadecimal characters"
[[ "$FRONTEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Frontend SHA must be 40 hexadecimal characters"
test "$(git -C "$BACKEND_SOURCE_REPO" rev-parse origin/main)" = "$BACKEND_SHA" \
    || fail "Backend SHA is not origin/main"
test "$(git -C "$FRONTEND_SOURCE_REPO" rev-parse origin/main)" = "$FRONTEND_SHA" \
    || fail "Frontend SHA is not origin/main"

BACKEND_TAG="${BACKEND_SHA:0:7}"
FRONTEND_TAG="${FRONTEND_SHA:0:7}"
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"

BACKEND_STAGE_DIR="$(mktemp -d "/tmp/hpcom-backend-${BACKEND_TAG}-XXXXXX")"
FRONTEND_STAGE_DIR="$(mktemp -d "/tmp/hpcom-frontend-${FRONTEND_TAG}-XXXXXX")"

CURRENT_STEP=prepare_sources
step "Preparing immutable source trees"
git -C "$BACKEND_SOURCE_REPO" archive --format=tar "$BACKEND_SHA" backend \
    | tar -xf - -C "$BACKEND_STAGE_DIR"
git -C "$FRONTEND_SOURCE_REPO" archive --format=tar "$FRONTEND_SHA" \
    | tar -xf - -C "$FRONTEND_STAGE_DIR"
require_directory "$BACKEND_STAGE_DIR/backend"
require_file "$BACKEND_STAGE_DIR/backend/composer.json"
require_file "$BACKEND_STAGE_DIR/backend/composer.lock"
require_file "$BACKEND_STAGE_DIR/backend/locations.json"
require_file "$FRONTEND_STAGE_DIR/package.json"
require_file "$FRONTEND_STAGE_DIR/package-lock.json"

# Do not allow a stale tracked .env.server from the source repository to win
# over the HPCom runtime values during the Nuxt build.
rm -f -- "$FRONTEND_STAGE_DIR/.env" "$FRONTEND_STAGE_DIR/.env.server"
mkdir -p "$FRONTEND_STAGE_DIR/public"
cat > "$FRONTEND_STAGE_DIR/public/release.json" <<EOF
{
  "site": "hpcomvietnam.vn",
  "backend_sha": "$BACKEND_SHA",
  "frontend_sha": "$FRONTEND_SHA",
  "backend_tag": "$BACKEND_TAG",
  "frontend_tag": "$FRONTEND_TAG"
}
EOF

CURRENT_STEP=frontend_build
step "Building frontend from $FRONTEND_SHA"
(
    cd "$FRONTEND_STAGE_DIR"
    export NODE_ENV=production
    export NUXT_PUBLIC_API_BASE="$API_ORIGIN/api/v1"
    export NUXT_API_PROXY_TARGET="$API_ORIGIN"
    export NUXT_PUBLIC_SITE_URL="$PUBLIC_ORIGIN"
    "$NPM_BIN" ci --no-audit --no-fund
    "$NPM_BIN" run build
)
require_file "$FRONTEND_STAGE_DIR/.output/server/index.mjs"

CURRENT_STEP=backup
BACKUP_DIR="$BACKUP_ROOT/${BACKEND_TAG}-${FRONTEND_TAG}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
BACKEND_BEFORE_DIR="$BACKUP_DIR/backend-before"
FRONTEND_OUTPUT_BACKUP="$BACKUP_DIR/frontend-output-before"
FRONTEND_OUTPUT_FAILED="$BACKUP_DIR/frontend-output-failed"
echo "BACKUP_DIR=$BACKUP_DIR"

step "Backing up database, code, output and runtime fingerprints"

# Save status/diff metadata without copying .env values into the evidence.
git -C "$BACKEND_DIR" status --short > "$BACKUP_DIR/backend-working-tree.status" 2>/dev/null || true
git -C "$FRONTEND_DIR" status --short > "$BACKUP_DIR/frontend-working-tree.status" 2>/dev/null || true
git -C "$FRONTEND_DIR" diff --binary > "$BACKUP_DIR/frontend-working-tree.patch" 2>/dev/null || true
sha256sum "$BACKEND_DIR/.env" "$FRONTEND_DIR/.env" > "$BACKUP_DIR/runtime-env.sha256"

# Keep a restorable copy of application code. Exclusions are deliberately
# repeated on restore so user data/configuration is never touched.
mkdir -p "$BACKEND_BEFORE_DIR"
"$RSYNC_BIN" -a --delete "${BACKEND_EXCLUDES[@]}" \
    "$BACKEND_DIR/" "$BACKEND_BEFORE_DIR/"

env_value() {
    "$PHP_BIN" -r '
        require $argv[1] . "/vendor/autoload.php";
        $dotenv = Dotenv\Dotenv::createImmutable($argv[1], ".env");
        $values = $dotenv->safeLoad();
        echo (string)($values[$argv[2]] ?? "");
    ' "$BACKEND_DIR" "$1"
}

DB_CONNECTION="$(env_value DB_CONNECTION)"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"
DB_PORT="${DB_PORT:-3306}"
[[ "$DB_CONNECTION" == mysql || "$DB_CONNECTION" == mariadb ]] \
    || fail "HPCom database connection must be mysql/mariadb; found ${DB_CONNECTION:-missing}"
test -n "$DB_HOST" || fail "DB_HOST is missing from the active backend .env"
test -n "$DB_DATABASE" || fail "DB_DATABASE is missing from the active backend .env"
test -n "$DB_USERNAME" || fail "DB_USERNAME is missing from the active backend .env"

MYSQL_CNF="$(mktemp "$BACKUP_DIR/mysql-client.XXXXXX.cnf")"
chmod 600 "$MYSQL_CNF"
{
    printf '[client]\n'
    printf 'host=%s\n' "$DB_HOST"
    printf 'port=%s\n' "$DB_PORT"
    printf 'user=%s\n' "$DB_USERNAME"
    printf 'password=%s\n' "$DB_PASSWORD"
} > "$MYSQL_CNF"
"$MYSQLDUMP_BIN" --defaults-extra-file="$MYSQL_CNF" \
    --single-transaction --routines --triggers --hex-blob \
    "$DB_DATABASE" > "$BACKUP_DIR/database.sql"
test -s "$BACKUP_DIR/database.sql" || fail "Database backup is empty"
rm -f -- "$MYSQL_CNF"
MYSQL_CNF=

cat > "$BACKUP_DIR/manifest.env" <<EOF
BACKEND_SHA=$BACKEND_SHA
FRONTEND_SHA=$FRONTEND_SHA
BACKEND_DIR=$BACKEND_DIR
FRONTEND_DIR=$FRONTEND_DIR
API_ORIGIN=$API_ORIGIN
PUBLIC_ORIGIN=$PUBLIC_ORIGIN
PHP_VERSION=$php_version
NODE_VERSION=$node_version
RUN_MIGRATIONS=$RUN_MIGRATIONS
RUN_SEEDERS=$RUN_SEEDERS
EOF

CURRENT_STEP=backend_sync
step "Syncing backend source while preserving config and data"
BACKEND_SYNC_STARTED=1
"$RSYNC_BIN" -a "${BACKEND_EXCLUDES[@]}" \
    "$BACKEND_STAGE_DIR/backend/" "$BACKEND_DIR/"

CURRENT_STEP=backend_dependencies
step "Installing backend dependencies with active HPCom .env"
(
    cd "$BACKEND_DIR"
    "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)

CURRENT_STEP=backend_cache
step "Clearing only Laravel runtime caches"
(
    cd "$BACKEND_DIR"
    "$PHP_BIN" artisan config:clear --no-ansi
    "$PHP_BIN" artisan route:clear --no-ansi
    "$PHP_BIN" artisan view:clear --no-ansi
)

CURRENT_STEP=backend_reload
step "Reloading PHP-FPM when aaPanel exposes a known service"
reload_php_fpm

check_http() {
    local label="$1"
    local url="$2"
    local status
    status="$(curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30 \
        -sS -o /dev/null -w '%{http_code}' "$url" 2>/dev/null)" || status=000
    printf '%s URL=%s HTTP=%s\n' "$label" "$url" "$status"
    [[ "$status" == 2?? ]]
}

read_province_code() {
    "$PHP_BIN" -r '
        $raw = stream_get_contents(STDIN);
        $data = json_decode(ltrim((string)$raw, "\xEF\xBB\xBF"), true);
        $items = is_array($data) ? ($data["data"] ?? $data) : [];
        $first = is_array($items) ? reset($items) : [];
        if (is_array($first)) {
            echo (string)($first["code"] ?? $first["id"] ?? "");
        }
    '
}

check_locations_api() {
    local body province_code
    body="$(curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30 \
        -fsS "$API_ORIGIN/api/v1/locations/provinces")" || return 1
    province_code="$(printf '%s' "$body" | read_province_code)"
    test -n "$province_code" || return 1
    printf 'LOCATIONS_PROVINCES=OK FIRST_CODE=%s\n' "$province_code"
    check_http locations_wards "$API_ORIGIN/api/v1/locations/provinces/$province_code/wards"
}

CURRENT_STEP=backend_smoke_before_migration
step "Checking HPCom backend before migration"
check_http admin_login "$API_ORIGIN/admin/login" \
    || fail "Admin login endpoint failed"
check_http locations_provinces "$API_ORIGIN/api/v1/locations/provinces" \
    || fail "Locations provinces endpoint failed"
check_locations_api \
    || fail "Locations API returned invalid province/ward data"
check_http header_menu "$API_ORIGIN/api/v1/menus/header" \
    || fail "Header menu endpoint failed"

if [ "$RUN_MIGRATIONS" = "1" ]; then
    CURRENT_STEP=migrate
    step "Running Laravel migrations after backup"
    MIGRATION_STATUS=STARTED
    DATABASE_CHANGED=POSSIBLY_CHANGED
    (
        cd "$BACKEND_DIR"
        "$PHP_BIN" artisan migrate --force --no-ansi
    )
    MIGRATION_STATUS=RUN
    DATABASE_CHANGED=YES
elif [ "$RUN_MIGRATIONS" = "0" ]; then
    MIGRATION_STATUS=NOT_RUN
    DATABASE_CHANGED=NO
fi

if [ "$RUN_SEEDERS" = "1" ]; then
    CURRENT_STEP=seed
    for seeder in $SEEDERS; do
        step "Running reviewed seeder $seeder"
        (
            cd "$BACKEND_DIR"
            "$PHP_BIN" artisan db:seed --class="$seeder" --force --no-ansi
        )
    done
fi

CURRENT_STEP=frontend_ready
step "Frontend output is ready from $FRONTEND_SHA"
(
    cd "$FRONTEND_STAGE_DIR"
    export NODE_ENV=production
    export NUXT_PUBLIC_API_BASE="$API_ORIGIN/api/v1"
    export NUXT_API_PROXY_TARGET="$API_ORIGIN"
    export NUXT_PUBLIC_SITE_URL="$PUBLIC_ORIGIN"
    # npm ci/build already ran above. This step is intentionally a no-op
    # marker for the deploy log and keeps the source/build boundary explicit.
    test -f .output/server/index.mjs
)

CURRENT_STEP=frontend_swap
step "Installing frontend output and reloading PM2 app $PM2_APP"
mv -- "$FRONTEND_DIR/.output" "$FRONTEND_OUTPUT_BACKUP"
FRONTEND_OUTPUT_OLD_MOVED=1
mv -- "$FRONTEND_STAGE_DIR/.output" "$FRONTEND_DIR/.output"
FRONTEND_OUTPUT_SWAPPED=1
export NODE_ENV=production
export NUXT_PUBLIC_API_BASE="$API_ORIGIN/api/v1"
export NUXT_API_PROXY_TARGET="$API_ORIGIN"
export NUXT_PUBLIC_SITE_URL="$PUBLIC_ORIGIN"
"$PM2_BIN" reload "$PM2_APP" --update-env

CURRENT_STEP=production_smoke
step "Running HPCom production smoke checks"
check_http public_frontend "$PUBLIC_ORIGIN$FRONTEND_HEALTH_PATH" \
    || fail "Public frontend endpoint failed"
check_http public_admin_login "$API_ORIGIN/admin/login" \
    || fail "Public admin endpoint failed"
check_http public_locations "$API_ORIGIN/api/v1/locations/provinces" \
    || fail "Public locations endpoint failed"

check_release() {
    local url="$1"
    local separator='?'
    local body
    case "$url" in
        *\?*) separator='&' ;;
    esac
    body="$(curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30 \
        -fsS "$url${separator}deploy_sha=$FRONTEND_SHA")" || return 1
    printf 'PUBLIC_RELEASE=%s\n' "$body"
    printf '%s' "$body" | grep -Fq '"frontend_sha"' \
        && printf '%s' "$body" | grep -Fq "$FRONTEND_SHA"
}

if [ "$SKIP_PUBLIC_CHECK" = "1" ]; then
    echo "PUBLIC_RELEASE_CHECK=SKIPPED"
else
    check_release "$PUBLIC_ORIGIN$PUBLIC_RELEASE_PATH" \
        || fail "Public frontend release marker does not match $FRONTEND_SHA"
fi

CURRENT_STEP=finalize
FRONTEND_OUTPUT_OLD_MOVED=0
DEPLOY_SUCCEEDED=1
echo "BACKUP_DIR=$BACKUP_DIR"
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"
echo "BACKEND_SOURCE_REPO=$BACKEND_SOURCE_REPO"
echo "FRONTEND_SOURCE_REPO=$FRONTEND_SOURCE_REPO"
echo "BACKEND_DIR=$BACKEND_DIR"
echo "FRONTEND_DIR=$FRONTEND_DIR"
echo "PUBLIC_ORIGIN=$PUBLIC_ORIGIN"
echo "API_ORIGIN=$API_ORIGIN"
echo "MIGRATION=$MIGRATION_STATUS"
echo "SEEDERS=$([ "$RUN_SEEDERS" = "1" ] && echo "$SEEDERS" || echo NOT_RUN)"
echo "DATABASE_CHANGED=$DATABASE_CHANGED"
echo "HPCOM_DEPLOY_COMPLETE=YES"
