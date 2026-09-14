#!/usr/bin/env bash

set -Eeuo pipefail
# Application source, Composer dependencies and Nuxt output must be readable by
# the unprivileged PHP-FPM/PM2 runtime users. Sensitive deploy artifacts are
# protected separately by their 0700 backup directory and explicit 0600 modes.
umask 022

# One-command, data-preserving deployment for hpcomvietnam.vn.
#
# Runtime layout on the aaPanel host:
#   backend  /www/wwwroot/admin.hpcomvietnam.vn  (Laravel + PHP-FPM)
#   frontend /www/wwwroot/hpcomvietnam.vn        (Nuxt output + PM2)
#   sources  /www/deploy/hpcom-source             (dedicated Git mirrors)
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

SOURCE_ROOT="${SOURCE_ROOT:-/www/deploy/hpcom-source}"
BACKEND_SOURCE_URL="${BACKEND_SOURCE_URL:-https://github.com/cuongdesignnb/pc.git}"
FRONTEND_SOURCE_URL="${FRONTEND_SOURCE_URL:-https://github.com/cuongdesignnb/pcfrontend.git}"
BACKEND_SOURCE_REPO="${BACKEND_SOURCE_REPO:-$SOURCE_ROOT/pc}"
FRONTEND_SOURCE_REPO="${FRONTEND_SOURCE_REPO:-$SOURCE_ROOT/pcfrontend}"
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
PHP_FPM_USER="${PHP_FPM_USER:-}"
API_LOCAL_IP="${API_LOCAL_IP:-127.0.0.1}"

API_ORIGIN="${API_ORIGIN%/}"
PUBLIC_ORIGIN="${PUBLIC_ORIGIN%/}"
PUBLIC_RELEASE_PATH="/${PUBLIC_RELEASE_PATH#/}"
FRONTEND_HEALTH_PATH="/${FRONTEND_HEALTH_PATH#/}"

# Resolve the API vhost directly to this server for origin health checks. This
# prevents a CDN, public DNS record, or stale proxy cache from hiding the state
# of the code that was just activated by PHP-FPM.
api_scheme="${API_ORIGIN%%://*}"
api_authority="${API_ORIGIN#*://}"
api_authority="${api_authority%%/*}"
api_host="${api_authority%%:*}"
if [[ "$api_authority" == *:* ]]; then
    api_port="${api_authority##*:}"
elif [ "$api_scheme" = "https" ]; then
    api_port=443
else
    api_port=80
fi
API_LOCAL_RESOLVE="${API_LOCAL_RESOLVE:-${api_host}:${api_port}:${API_LOCAL_IP}}"

# npm and PM2 entrypoints on aaPanel commonly use /usr/bin/env node. Put the
# selected Node runtime first so an unrelated system Node cannot be chosen.
NODE_BIN_DIR="${NODE_BIN%/*}"
export PATH="$NODE_BIN_DIR:$PATH"

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

validate_locations_file() {
    local label="$1"
    local file="$2"

    "$PHP_BIN" -r '
        $label = $argv[1];
        $file = $argv[2];
        $raw = file_get_contents($file);
        if ($raw === false) {
            fwrite(STDERR, "LOCATION_FILE=FAIL label={$label} reason=unreadable\n");
            exit(1);
        }

        $data = json_decode(ltrim($raw, "\xEF\xBB\xBF"), true);
        $provinces = is_array($data) ? ($data["provinces"] ?? $data) : [];
        $provinces = is_array($provinces) ? array_values($provinces) : [];
        $first = $provinces[0] ?? null;
        $code = is_array($first) ? trim((string) ($first["code"] ?? "")) : "";
        $wards = is_array($first) && is_array($first["wards"] ?? null)
            ? array_values($first["wards"])
            : [];

        if (json_last_error() !== JSON_ERROR_NONE || $code === "" || $wards === []) {
            fwrite(STDERR, sprintf(
                "LOCATION_FILE=FAIL label=%s bytes=%d json_error=%s provinces=%d first_code=%s first_wards=%d\n",
                $label,
                strlen($raw),
                json_last_error_msg(),
                count($provinces),
                $code === "" ? "missing" : $code,
                count($wards)
            ));
            exit(1);
        }

        printf(
            "LOCATION_FILE=OK label=%s bytes=%d sha256=%s provinces=%d first_code=%s first_wards=%d\n",
            $label,
            strlen($raw),
            hash("sha256", $raw),
            count($provinces),
            $code,
            count($wards)
        );
    ' "$label" "$file"
}

ensure_backend_runtime_readability() {
    local -a readable_directories=()
    local path

    for path in app bootstrap config database resources routes vendor; do
        if [ -d "$BACKEND_DIR/$path" ]; then
            readable_directories+=("$BACKEND_DIR/$path")
        fi
    done

    if [ "${#readable_directories[@]}" -gt 0 ]; then
        chmod -R a+rX "${readable_directories[@]}"
    fi
    for path in artisan composer.json composer.lock locations.json public/index.php; do
        if [ -f "$BACKEND_DIR/$path" ]; then
            chmod a+r "$BACKEND_DIR/$path"
        fi
    done

    (
        cd "$BACKEND_DIR"
        runuser -u "$PHP_FPM_USER" -- "$PHP_BIN" -r '
            $root = getcwd();
            $required = [
                "vendor/composer/platform_check.php",
                "vendor/autoload.php",
                "bootstrap/app.php",
                "locations.json",
            ];
            foreach ($required as $relative) {
                if (! is_readable($root . DIRECTORY_SEPARATOR . $relative)) {
                    fwrite(STDERR, "BACKEND_RUNTIME_READABILITY=FAIL file={$relative}\n");
                    exit(1);
                }
            }
            require "vendor/autoload.php";
            echo "BACKEND_RUNTIME_AUTOLOAD=OK\n";
        '
    )
    printf 'BACKEND_RUNTIME_READABILITY=OK user=%s\n' "$PHP_FPM_USER"
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

prepare_source_repository() {
    local repository="$1"
    local remote_url="$2"

    if [ ! -e "$repository/.git" ]; then
        [ ! -e "$repository" ] \
            || fail "Source path exists but is not a Git repository: $repository"
        mkdir -p "$SOURCE_ROOT"
        step "Cloning dedicated source repository $remote_url"
        git clone --no-tags --branch main "$remote_url" "$repository" \
            || fail "Could not clone source repository: $remote_url"
    fi
}

step() {
    printf '\n[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
}

cleanup() {
    [ -z "$BACKEND_STAGE_DIR" ] || rm -rf -- "$BACKEND_STAGE_DIR" || true
    [ -z "$FRONTEND_STAGE_DIR" ] || rm -rf -- "$FRONTEND_STAGE_DIR" || true
    [ -z "$MYSQL_CNF" ] || rm -f -- "$MYSQL_CNF" || true
}

assert_php_fpm_does_not_hold_deploy_lock() {
    local pid process_name

    if ! command -v fuser >/dev/null 2>&1; then
        echo "DEPLOY_LOCK_INHERITANCE_CHECK=SKIPPED reason=fuser_unavailable"
        return 0
    fi

    for pid in $(fuser "$DEPLOY_LOCK" 2>/dev/null || true); do
        process_name="$(ps -p "$pid" -o comm= 2>/dev/null || true)"
        if [[ "$process_name" == *php-fpm* ]]; then
            echo "DEPLOY_LOCK_INHERITANCE_CHECK=FAIL pid=$pid process=$process_name" >&2
            return 1
        fi
    done

    echo "DEPLOY_LOCK_INHERITANCE_CHECK=PASS"
}

restart_php_fpm() {
    if [ -n "$PHP_FPM_RELOAD_COMMAND" ]; then
        echo "PHP_FPM_RESTART=custom"
        # FD 9 owns the deployment flock. Close only the child copy before
        # starting a daemon; the parent shell keeps holding the lock for the
        # remainder of the deployment.
        bash -lc "$PHP_FPM_RELOAD_COMMAND" 9>&-
        assert_php_fpm_does_not_hold_deploy_lock
        return 0
    fi

    # aaPanel commonly exposes the PHP-FPM init script under this name. A hard
    # restart is intentional: a reload can leave OPcache and Laravel's cached
    # provider manifest serving the previous source tree after a sync or
    # rollback.
    local init_script
    for init_script in /etc/init.d/php-fpm-83 /etc/init.d/php83-php-fpm; do
        if [ -x "$init_script" ]; then
            echo "PHP_FPM_RESTART=$init_script"
            "$init_script" restart 9>&-
            assert_php_fpm_does_not_hold_deploy_lock
            return 0
        fi
    done

    if command -v systemctl >/dev/null 2>&1; then
        local unit
        for unit in php8.3-fpm php-fpm-83 php83-php-fpm; do
            if systemctl is-active --quiet "$unit" 2>/dev/null; then
                echo "PHP_FPM_RESTART=$unit"
                systemctl restart "$unit" 9>&-
                assert_php_fpm_does_not_hold_deploy_lock
                return 0
            fi
        done
    fi

    echo "PHP_FPM_RESTART=NOT_FOUND"
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
        # Recycle PHP-FPM after restoring code. Without this, a failed deploy
        # can leave the web process running a mixed source/provider state.
        restart_php_fpm || true
        echo "ROLLBACK_BACKEND_FPM=RESTARTED" >&2
    fi

    if [ "$FRONTEND_OUTPUT_OLD_MOVED" -eq 1 ] && [ -n "$FRONTEND_OUTPUT_BACKUP" ] && [ -e "$FRONTEND_OUTPUT_BACKUP" ]; then
        if [ -e "$FRONTEND_DIR/.output" ]; then
            FRONTEND_OUTPUT_FAILED="$BACKUP_DIR/frontend-output-failed"
            mv -- "$FRONTEND_DIR/.output" "$FRONTEND_OUTPUT_FAILED" || true
        fi
        mv -- "$FRONTEND_OUTPUT_BACKUP" "$FRONTEND_DIR/.output" || true
        echo "ROLLBACK_FRONTEND_OUTPUT=RESTORED" >&2
        "$PM2_BIN" reload "$PM2_APP" --update-env 9>&- >/dev/null 2>&1 || true
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
for command in git curl rsync mysqldump tar awk sed grep mktemp flock sha256sum date sleep bash cmp chmod ps stat runuser id; do
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

prepare_source_repository "$BACKEND_SOURCE_REPO" "$BACKEND_SOURCE_URL"
prepare_source_repository "$FRONTEND_SOURCE_REPO" "$FRONTEND_SOURCE_URL"
require_directory "$BACKEND_SOURCE_REPO"
require_directory "$FRONTEND_SOURCE_REPO"
require_directory "$BACKEND_DIR"
require_directory "$FRONTEND_DIR"
require_file "$BACKEND_DIR/.env"
require_file "$FRONTEND_DIR/.env"
require_file "$FRONTEND_DIR/.output/server/index.mjs"

if [ -z "$PHP_FPM_USER" ]; then
    PHP_FPM_USER="$(ps -eo user=,comm= \
        | awk '$2 ~ /^php-fpm/ && $1 != "root" && user == "" { user = $1 } END { print user }')"
fi
if [ -z "$PHP_FPM_USER" ] && [ -d "$BACKEND_DIR/storage" ]; then
    PHP_FPM_USER="$(stat -c '%U' "$BACKEND_DIR/storage")"
fi
test -n "$PHP_FPM_USER" || fail "Could not determine the PHP-FPM runtime user"
test "$PHP_FPM_USER" != root || fail "PHP-FPM runtime user must not be root"
id "$PHP_FPM_USER" >/dev/null 2>&1 \
    || fail "PHP-FPM runtime user does not exist: $PHP_FPM_USER"
echo "PHP_FPM_USER=$PHP_FPM_USER"

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
validate_locations_file staged "$BACKEND_STAGE_DIR/backend/locations.json" \
    || fail "Staged locations.json is invalid"

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
    --single-transaction --routines --triggers --hex-blob --no-tablespaces \
    "$DB_DATABASE" > "$BACKUP_DIR/database.sql"
test -s "$BACKUP_DIR/database.sql" || fail "Database backup is empty"
rm -f -- "$MYSQL_CNF"
MYSQL_CNF=

cat > "$BACKUP_DIR/manifest.env" <<EOF
BACKEND_SHA=$BACKEND_SHA
FRONTEND_SHA=$FRONTEND_SHA
SOURCE_ROOT=$SOURCE_ROOT
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
cmp -s "$BACKEND_STAGE_DIR/backend/locations.json" "$BACKEND_DIR/locations.json" \
    || fail "Active locations.json does not match the staged release"
validate_locations_file active "$BACKEND_DIR/locations.json" \
    || fail "Active locations.json is invalid after sync"

CURRENT_STEP=backend_dependencies
step "Installing backend dependencies with active HPCom .env"
(
    cd "$BACKEND_DIR"
    "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)

CURRENT_STEP=backend_permissions
step "Verifying backend files as PHP-FPM user $PHP_FPM_USER"
ensure_backend_runtime_readability \
    || fail "Backend dependencies are not readable by PHP-FPM user $PHP_FPM_USER"

check_location_directory_cli() {
    (
        cd "$BACKEND_DIR"
        runuser -u "$PHP_FPM_USER" -- "$PHP_BIN" -r '
            require "vendor/autoload.php";
            $app = require "bootstrap/app.php";
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            Illuminate\Support\Facades\Cache::forget("locations_payload");
            Illuminate\Support\Facades\Cache::forget("locations_provinces");

            $directory = $app->make(App\Services\Locations\LocationDirectory::class);
            $provinces = $directory->provinces();
            $first = is_array($provinces) ? ($provinces[0] ?? null) : null;
            $provinceCode = is_array($first)
                ? trim((string) ($first["code"] ?? ""))
                : "";
            $wards = $provinceCode !== "" ? $directory->wards($provinceCode) : null;
            $firstWard = is_array($wards) ? ($wards[0] ?? null) : null;
            $wardCode = is_array($firstWard)
                ? trim((string) ($firstWard["code"] ?? ""))
                : "";

            if ($provinceCode === "" || $wardCode === "") {
                fwrite(STDERR, sprintf(
                    "LOCATION_DIRECTORY_CLI=FAIL provinces=%d province_code=%s wards=%d ward_code=%s\n",
                    is_array($provinces) ? count($provinces) : -1,
                    $provinceCode === "" ? "missing" : $provinceCode,
                    is_array($wards) ? count($wards) : -1,
                    $wardCode === "" ? "missing" : $wardCode
                ));
                exit(1);
            }

            printf(
                "LOCATION_DIRECTORY_CLI=OK provinces=%d first_code=%s first_wards=%d first_ward_code=%s\n",
                count($provinces),
                $provinceCode,
                count($wards),
                $wardCode
            );
        '
    )
}

CURRENT_STEP=backend_cache
step "Clearing only Laravel runtime caches"
(
    cd "$BACKEND_DIR"
    "$PHP_BIN" artisan config:clear --no-ansi
    "$PHP_BIN" artisan route:clear --no-ansi
    "$PHP_BIN" artisan view:clear --no-ansi
    # LocationDirectory intentionally uses rememberForever. Remove only its
    # two known keys so a previous invalid/BOM parse cannot survive a deploy;
    # do not run cache:clear because that could evict unrelated application
    # state.
    "$PHP_BIN" artisan cache:forget locations_payload --no-ansi || true
    "$PHP_BIN" artisan cache:forget locations_provinces --no-ansi || true
)

CURRENT_STEP=backend_location_runtime
step "Validating and warming the active Laravel location directory"
check_location_directory_cli \
    || fail "Active Laravel location directory is invalid"

CURRENT_STEP=backend_reload
step "Restarting PHP-FPM to activate the backend source"
restart_php_fpm

check_http() {
    local label="$1"
    local url="$2"
    local mode="${3:-public}"
    local -a curl_args=(
        curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30
        -H 'Cache-Control: no-cache'
        -H 'Pragma: no-cache'
    )
    local status

    if [ "$mode" = "origin" ]; then
        curl_args+=(--resolve "$API_LOCAL_RESOLVE" --insecure)
    elif [ "$mode" != "public" ]; then
        echo "HTTP_CHECK=FAIL label=$label reason=invalid_mode mode=$mode" >&2
        return 1
    fi

    status="$("${curl_args[@]}" -sS -o /dev/null -w '%{http_code}' "$url" 2>/dev/null)" \
        || status=000
    printf '%s MODE=%s URL=%s HTTP=%s\n' "$label" "$mode" "$url" "$status"
    [[ "$status" == 2?? ]]
}

read_location_code() {
    local response_file="$1"

    "$PHP_BIN" -r '
        $raw = file_get_contents($argv[1]);
        $raw = $raw === false ? "" : $raw;
        $data = json_decode(ltrim($raw, "\xEF\xBB\xBF"), true);

        $extract = static function (mixed $value) use (&$extract): array {
            if (! is_array($value)) {
                return [];
            }
            if (array_is_list($value)) {
                return array_values($value);
            }
            foreach (["data", "provinces", "wards", "items", "result"] as $key) {
                if (array_key_exists($key, $value)) {
                    $items = $extract($value[$key]);
                    if ($items !== []) {
                        return $items;
                    }
                }
            }
            return [];
        };

        $items = $extract($data);
        $first = $items[0] ?? null;
        $code = is_array($first)
            ? trim((string) ($first["code"] ?? $first["id"] ?? ""))
            : "";
        $rootKeys = is_array($data) && ! array_is_list($data)
            ? implode(",", array_keys($data))
            : "list";
        $head = preg_replace("/\\s+/u", " ", mb_substr($raw, 0, 240));

        fwrite(STDERR, sprintf(
            "LOCATION_RESPONSE bytes=%d json_error=%s root_type=%s root_keys=%s items=%d first_code=%s body_head=%s\n",
            strlen($raw),
            json_last_error_msg(),
            get_debug_type($data),
            $rootKeys,
            count($items),
            $code === "" ? "missing" : $code,
            json_encode($head, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        ));

        if ($code === "") {
            exit(1);
        }
        echo $code;
    ' "$response_file"
}

fetch_location_code() {
    local label="$1"
    local path="$2"
    local mode="$3"
    local response_file headers_file probe url result status content_type code
    local -a curl_args=(
        curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30
        -H 'Accept: application/json'
        -H 'Cache-Control: no-cache'
        -H 'Pragma: no-cache'
    )

    response_file="$(mktemp "/tmp/hpcom-${label}-body-XXXXXX")"
    headers_file="$(mktemp "/tmp/hpcom-${label}-headers-XXXXXX")"
    probe="${BACKEND_TAG}-$(date +%s)-${RANDOM}"
    url="${API_ORIGIN}${path}?deploy_probe=${probe}"

    if [ "$mode" = "origin" ]; then
        curl_args+=(--resolve "$API_LOCAL_RESOLVE" --insecure)
    elif [ "$mode" != "public" ]; then
        rm -f -- "$response_file" "$headers_file"
        echo "LOCATION_HTTP=FAIL label=$label reason=invalid_mode mode=$mode" >&2
        return 1
    fi

    result="$("${curl_args[@]}" -sS -D "$headers_file" -o "$response_file" \
        -w '%{http_code}|%{content_type}' "$url" 2>/dev/null)" || result='000|'
    status="${result%%|*}"
    content_type="${result#*|}"
    printf 'LOCATION_HTTP label=%s mode=%s HTTP=%s CONTENT_TYPE=%s URL=%s\n' \
        "$label" "$mode" "$status" "${content_type:-missing}" "$url" >&2

    if [[ "$status" != 2?? ]]; then
        read_location_code "$response_file" >/dev/null || true
        rm -f -- "$response_file" "$headers_file"
        return 1
    fi

    if ! code="$(read_location_code "$response_file")"; then
        rm -f -- "$response_file" "$headers_file"
        return 1
    fi
    rm -f -- "$response_file" "$headers_file"
    printf '%s' "$code"
}

check_locations_api() {
    local mode="$1"
    local province_code ward_code

    province_code="$(fetch_location_code locations_provinces \
        /api/v1/locations/provinces "$mode")" || return 1
    ward_code="$(fetch_location_code locations_wards \
        "/api/v1/locations/provinces/${province_code}/wards" "$mode")" || return 1
    printf 'LOCATIONS_API=OK MODE=%s FIRST_PROVINCE_CODE=%s FIRST_WARD_CODE=%s\n' \
        "$mode" "$province_code" "$ward_code"
}

check_frontend_locations_dataset() {
    local response_file url result status content_type code

    response_file="$(mktemp "/tmp/hpcom-frontend-locations-body-XXXXXX")"
    url="${PUBLIC_ORIGIN}/data/locations.json?deploy_probe=${FRONTEND_TAG}-$(date +%s)-${RANDOM}"
    result="$(curl --retry 3 --retry-delay 2 --connect-timeout 10 --max-time 30 \
        -H 'Accept: application/json' \
        -H 'Cache-Control: no-cache' \
        -H 'Pragma: no-cache' \
        -sS -o "$response_file" -w '%{http_code}|%{content_type}' "$url" 2>/dev/null)" \
        || result='000|'
    status="${result%%|*}"
    content_type="${result#*|}"
    printf 'FRONTEND_LOCATION_HTTP HTTP=%s CONTENT_TYPE=%s URL=%s\n' \
        "$status" "${content_type:-missing}" "$url"

    if [[ "$status" != 2?? ]]; then
        read_location_code "$response_file" >/dev/null || true
        rm -f -- "$response_file"
        return 1
    fi
    if ! code="$(read_location_code "$response_file")"; then
        rm -f -- "$response_file"
        return 1
    fi
    rm -f -- "$response_file"
    printf 'FRONTEND_LOCATION_DATASET=OK FIRST_CODE=%s\n' "$code"
}

CURRENT_STEP=backend_smoke_before_migration
step "Checking HPCom backend before migration"
echo "API_LOCAL_RESOLVE=$API_LOCAL_RESOLVE"
check_http admin_login_origin "$API_ORIGIN/admin/login" origin \
    || fail "Admin login origin endpoint failed"
check_locations_api origin \
    || fail "Locations origin API returned invalid province/ward data"
check_http header_menu_origin "$API_ORIGIN/api/v1/menus/header" origin \
    || fail "Header menu origin endpoint failed"
check_http admin_login_public "$API_ORIGIN/admin/login" public \
    || fail "Admin login public endpoint failed"
check_locations_api public \
    || fail "Locations public API returned invalid province/ward data"
check_http header_menu_public "$API_ORIGIN/api/v1/menus/header" public \
    || fail "Header menu public endpoint failed"

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
test -f "$FRONTEND_STAGE_DIR/.output/server/index.mjs"

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
"$PM2_BIN" reload "$PM2_APP" --update-env 9>&-

CURRENT_STEP=production_smoke
step "Running HPCom production smoke checks"
check_http public_frontend "$PUBLIC_ORIGIN$FRONTEND_HEALTH_PATH" \
    || fail "Public frontend endpoint failed"
check_http public_admin_login "$API_ORIGIN/admin/login" \
    || fail "Public admin endpoint failed"
check_locations_api origin \
    || fail "Final locations origin API check failed"
check_locations_api public \
    || fail "Final locations public API check failed"
check_frontend_locations_dataset \
    || fail "Public frontend locations dataset is invalid"

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
