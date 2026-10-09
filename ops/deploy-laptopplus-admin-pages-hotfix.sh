#!/usr/bin/env bash

set -Eeuo pipefail

# Deploy only the LaptopPlus admin static-pages hotfix. This intentionally
# never runs migrations/seeders and never rebuilds or restarts the storefront.

TARGET_SHA="${1:-}"
DEPLOY_BRANCH="codex/laptopplus-static-pages-hotfix-e283"
BASE_SHA="e283fc3f6f224197d3b71729573ed95ebdb970be"
DEPLOY_SCRIPT_URL="${DEPLOY_SCRIPT_URL:-https://raw.githubusercontent.com/cuongdesignnb/pc/${TARGET_SHA}/ops/deploy-laptopplus-admin-pages-hotfix.sh}"

if [[ ! "$TARGET_SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo "DEPLOY_ERROR=Pass the full 40-character hotfix commit SHA" >&2
    exit 1
fi
if [[ "$DEPLOY_SCRIPT_URL" != "https://raw.githubusercontent.com/cuongdesignnb/pc/${TARGET_SHA}/ops/deploy-laptopplus-admin-pages-hotfix.sh" ]]; then
    echo "DEPLOY_ERROR=Deploy script URL must be pinned to the requested commit" >&2
    exit 1
fi

if [[ "${DEPLOY_DETACH:-0}" == "1" && "${DEPLOY_DAEMONIZED:-0}" != "1" ]]; then
    deploy_log="${DEPLOY_LOG:-/tmp/laptopplus-admin-pages-deploy-$(date -u +%Y%m%d-%H%M%S).log}"
    printf -v url_arg '%q' "$DEPLOY_SCRIPT_URL"
    printf -v sha_arg '%q' "$TARGET_SHA"
    child_command="set -o pipefail; curl --retry 5 --retry-delay 5 --connect-timeout 20 --max-time 120 -fsSL ${url_arg} | DEPLOY_DAEMONIZED=1 DEPLOY_DETACH=0 DEPLOY_SCRIPT_URL=${url_arg} bash -s -- ${sha_arg}"

    if command -v setsid >/dev/null 2>&1; then
        setsid nohup bash -c "$child_command" >"$deploy_log" 2>&1 </dev/null &
    else
        nohup bash -c "$child_command" >"$deploy_log" 2>&1 </dev/null &
    fi

    echo "DEPLOY_PID=$!"
    echo "DEPLOY_LOG=$deploy_log"
    exit 0
fi

STACK_DIR="${STACK_DIR:-/www/docker/laptopplus.vn}"
PRODUCTION_DEPLOY_DIR="${PRODUCTION_DEPLOY_DIR:-$STACK_DIR/deploy/production}"
STACK_ENV="${STACK_ENV:-$STACK_DIR/deploy/production/stack.env}"
COMPOSE_FILE="${COMPOSE_FILE:-$STACK_DIR/docker-compose.production.yml}"
DEPLOY_LOCK="${DEPLOY_LOCK:-/tmp/laptopplus-production-deploy.lock}"

ERROR_REPORTED=0
ENV_UPDATED=0
DEPLOY_SUCCEEDED=0
ROLLBACK_DONE=0
CONTEXT_DIR=""
BACKUP_DIR=""
BACKUP_STACK_ENV=""
COMPOSE=()
PREVIOUS_BACKEND_TAG=""
PREVIOUS_FRONTEND_TAG=""
NEW_TAG=""

fail() {
    ERROR_REPORTED=1
    echo "DEPLOY_ERROR=$*" >&2
    exit 1
}

step() {
    printf '\n[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
}

cleanup() {
    if [[ "$CONTEXT_DIR" == /tmp/laptopplus-admin-pages-* && -d "$CONTEXT_DIR" ]]; then
        rm -rf -- "$CONTEXT_DIR"
    fi
}

rollback() {
    [[ "$ENV_UPDATED" == "1" && "$ROLLBACK_DONE" == "0" ]] || return 0
    ROLLBACK_DONE=1
    echo "ROLLBACK=START" >&2

    if [[ -n "$BACKUP_STACK_ENV" && -f "$BACKUP_STACK_ENV" ]]; then
        if ! cp -p "$BACKUP_STACK_ENV" "$STACK_ENV"; then
            echo "ROLLBACK_ERROR=Could not restore stack.env from $BACKUP_STACK_ENV" >&2
            echo "ROLLBACK=INCOMPLETE" >&2
            return 1
        fi
    else
        echo "ROLLBACK_ERROR=stack.env backup is unavailable" >&2
        echo "ROLLBACK=INCOMPLETE" >&2
        return 1
    fi

    if ((${#COMPOSE[@]})); then
        if ! "${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate \
            backend-php backend-nginx queue scheduler; then
            echo "ROLLBACK_ERROR=Could not recreate the previous backend services" >&2
            echo "ROLLBACK=INCOMPLETE" >&2
            return 1
        fi
    fi

    ENV_UPDATED=0
    echo "ROLLBACK=COMPLETE" >&2
}

on_exit() {
    local exit_code=$?
    if [[ "$exit_code" -ne 0 ]]; then
        [[ "$ERROR_REPORTED" == "1" ]] || echo "DEPLOY_ERROR=exit_code=$exit_code" >&2
        rollback || true
        echo "DEPLOY_STATUS=FAILED" >&2
    elif [[ "$DEPLOY_SUCCEEDED" == "1" ]]; then
        echo "DEPLOY_STATUS=SUCCESS"
    fi
    cleanup
    exit "$exit_code"
}

trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap '' HUP

for command in git docker curl sed cp mktemp flock grep awk tar date sleep rm find; do
    command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done

[[ -d "$STACK_DIR" ]] || fail "Stack directory not found: $STACK_DIR"
[[ -d "$PRODUCTION_DEPLOY_DIR" ]] || fail "Production Docker build config not found: $PRODUCTION_DEPLOY_DIR"
[[ -f "$PRODUCTION_DEPLOY_DIR/backend.Dockerfile" ]] || fail "Production backend Dockerfile is missing"
[[ -f "$PRODUCTION_DEPLOY_DIR/backend-nginx.conf" ]] || fail "Production backend Nginx config is missing"
[[ -f "$STACK_ENV" ]] || fail "Stack env is missing: $STACK_ENV"
[[ -f "$COMPOSE_FILE" ]] || fail "Compose file is missing: $COMPOSE_FILE"
[[ "$(grep -c '^APP_IMAGE_TAG=' "$STACK_ENV" || true)" == "1" ]] || fail "Expected exactly one APP_IMAGE_TAG in stack.env"
[[ "$(grep -c '^FRONTEND_IMAGE_TAG=' "$STACK_ENV" || true)" == "1" ]] || fail "Expected exactly one FRONTEND_IMAGE_TAG in stack.env"

exec 9>"$DEPLOY_LOCK"
flock -n 9 || fail "Another LaptopPlus production deploy is already running: $DEPLOY_LOCK"

ORIGIN_URL="$(git -C "$STACK_DIR" remote get-url origin)" \
    || fail "Could not identify the source repository"
case "$ORIGIN_URL" in
    https://github.com/cuongdesignnb/pc|https://github.com/cuongdesignnb/pc.git|git@github.com:cuongdesignnb/pc|git@github.com:cuongdesignnb/pc.git|ssh://git@github.com/cuongdesignnb/pc|ssh://git@github.com/cuongdesignnb/pc.git) ;;
    *) fail "Unexpected source repository origin; expected cuongdesignnb/pc" ;;
esac

step "Fetching the pinned hotfix branch"
git -C "$STACK_DIR" -c http.connectTimeout=20 -c http.lowSpeedLimit=1000 \
    -c http.lowSpeedTime=30 fetch --no-tags origin "$DEPLOY_BRANCH"
FETCHED_SHA="$(git -C "$STACK_DIR" rev-parse FETCH_HEAD)"
[[ "$FETCHED_SHA" == "$TARGET_SHA" ]] || fail "Hotfix branch tip does not match requested SHA"
git -C "$STACK_DIR" cat-file -e "${BASE_SHA}^{commit}" \
    || fail "Expected running release base $BASE_SHA is not available locally"
git -C "$STACK_DIR" merge-base --is-ancestor "$BASE_SHA" "$TARGET_SHA" \
    || fail "Hotfix is not based on the expected LaptopPlus release"

expected_files=$'backend/resources/js/Pages/Admin/Pages/Index.vue\nbackend/tests/Feature/AdminPagesTest.php\nbackend/tests/ui/admin-pages-index.test.mjs\nops/deploy-laptopplus-admin-pages-hotfix.sh'
actual_files="$(git -C "$STACK_DIR" diff --name-only "$BASE_SHA" "$TARGET_SHA" | LC_ALL=C sort)"
expected_files="$(printf '%s\n' "$expected_files" | LC_ALL=C sort)"
[[ "$actual_files" == "$expected_files" ]] \
    || fail "Hotfix file scope differs from the reviewed admin-only change"

PREVIOUS_BACKEND_TAG="$(sed -n 's/^APP_IMAGE_TAG=//p' "$STACK_ENV")"
PREVIOUS_FRONTEND_TAG="$(sed -n 's/^FRONTEND_IMAGE_TAG=//p' "$STACK_ENV")"
[[ -n "$PREVIOUS_BACKEND_TAG" && -n "$PREVIOUS_FRONTEND_TAG" ]] \
    || fail "Image tag in stack.env is empty"
[[ "$PREVIOUS_BACKEND_TAG" == "${BASE_SHA:0:7}" ]] \
    || fail "Production backend tag differs from the audited base release"

CURRENT_PHP_IMAGE="$(docker inspect laptopplus-backend-php --format '{{.Config.Image}}')" \
    || fail "Could not inspect the running backend PHP container"
CURRENT_NGINX_IMAGE="$(docker inspect laptopplus-backend-nginx --format '{{.Config.Image}}')" \
    || fail "Could not inspect the running backend Nginx container"
CURRENT_FRONTEND_IMAGE="$(docker inspect laptopplus-frontend --format '{{.Config.Image}}')" \
    || fail "Could not inspect the running storefront container"
[[ "$CURRENT_PHP_IMAGE" == "laptopplus-backend:$PREVIOUS_BACKEND_TAG" ]] \
    || fail "Running PHP image does not match APP_IMAGE_TAG; refusing to deploy"
[[ "$CURRENT_NGINX_IMAGE" == "laptopplus-backend-nginx:$PREVIOUS_BACKEND_TAG" ]] \
    || fail "Running Nginx image does not match APP_IMAGE_TAG; refusing to deploy"
[[ "$CURRENT_FRONTEND_IMAGE" == "laptopplus-frontend:$PREVIOUS_FRONTEND_TAG" ]] \
    || fail "Running frontend image does not match FRONTEND_IMAGE_TAG; refusing to deploy"

NEW_TAG="${TARGET_SHA:0:12}"
CONTEXT_DIR="$(mktemp -d "/tmp/laptopplus-admin-pages-${NEW_TAG}-XXXXXX")"

step "Preparing the immutable admin-only backend build context"
git -C "$STACK_DIR" archive --format=tar "$TARGET_SHA" | tar -xf - -C "$CONTEXT_DIR"
find "$CONTEXT_DIR" -type f -name '.env*' ! -name '.env.example' -delete
if find "$CONTEXT_DIR" -type f -name '.env*' ! -name '.env.example' -print -quit | grep -q .; then
    fail "A tracked runtime environment file remains in the build context"
fi
mkdir -p "$CONTEXT_DIR/deploy/production"
cp -a "$PRODUCTION_DEPLOY_DIR"/. "$CONTEXT_DIR/deploy/production/"
echo "BUILD_CONTEXT=REPOSITORY_ROOT_LAYOUT"
echo "BUILD_CONTEXT_ENV_FILES=SANITIZED"

step "Building backend PHP and Nginx images only"
docker build --pull --progress=plain --target php \
    -f "$CONTEXT_DIR/deploy/production/backend.Dockerfile" \
    -t "laptopplus-backend:$NEW_TAG" "$CONTEXT_DIR"
docker build --pull --progress=plain --target nginx \
    -f "$CONTEXT_DIR/deploy/production/backend.Dockerfile" \
    -t "laptopplus-backend-nginx:$NEW_TAG" "$CONTEXT_DIR"
docker image inspect "laptopplus-backend:$NEW_TAG" >/dev/null \
    || fail "Built backend PHP image is missing"
docker image inspect "laptopplus-backend-nginx:$NEW_TAG" >/dev/null \
    || fail "Built backend Nginx image is missing"

BACKUP_DIR="/www/backups/laptopplus.vn/admin-pages-${NEW_TAG}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
BACKUP_STACK_ENV="$BACKUP_DIR/stack.env"
cp -p "$STACK_ENV" "$BACKUP_STACK_ENV"
printf 'PREVIOUS_BACKEND_TAG=%s\nFRONTEND_TAG_UNCHANGED=%s\n' \
    "$PREVIOUS_BACKEND_TAG" "$PREVIOUS_FRONTEND_TAG" >"$BACKUP_DIR/release.env"

step "Switching only the backend image tag"
temporary_env="$(mktemp "${STACK_ENV}.tmp.XXXXXX")"
if ! awk -v backend="$NEW_TAG" '
    BEGIN { changed = 0 }
    /^APP_IMAGE_TAG=/ { print "APP_IMAGE_TAG=" backend; changed++; next }
    { print }
    END { if (changed != 1) exit 42 }
' "$STACK_ENV" >"$temporary_env"; then
    rm -f -- "$temporary_env"
    fail "Could not safely update APP_IMAGE_TAG"
fi
mv -f -- "$temporary_env" "$STACK_ENV"
ENV_UPDATED=1
COMPOSE=(docker compose --env-file "$STACK_ENV" -f "$COMPOSE_FILE")
"${COMPOSE[@]}" config --quiet

step "Recreating backend and workers only; database and storefront stay untouched"
"${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate backend-php

wait_healthy() {
    local container="$1"
    local attempt state
    for attempt in {1..60}; do
        state="$(docker inspect "$container" \
            --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}' \
            2>/dev/null || true)"
        [[ "$state" == "running|healthy" ]] && return 0
        printf 'HEALTH_WAIT container=%s attempt=%s/60 state=%s\n' \
            "$container" "$attempt" "${state:-missing}"
        sleep 2
    done
    return 1
}

wait_healthy laptopplus-backend-php || {
    "${COMPOSE[@]}" ps
    fail "Backend PHP health check failed"
}
"${COMPOSE[@]}" up -d --no-build --no-deps --force-recreate \
    backend-nginx queue scheduler
wait_healthy laptopplus-backend-nginx || {
    "${COMPOSE[@]}" ps
    fail "Backend Nginx health check failed"
}

check_http() {
    local url="$1"
    local status
    status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$url")" || status=000
    printf '%s HTTP=%s\n' "$url" "$status"
    [[ "$status" == 2?? ]]
}

step "Checking backend health, admin login, and the rebuilt admin page asset"
check_http http://127.0.0.1:8901/healthz || fail "Backend health endpoint failed"
check_http http://127.0.0.1:8901/admin/login || fail "Admin login endpoint failed"
manifest="$(curl -fsS --max-time 20 http://127.0.0.1:8901/build/manifest.json)" \
    || fail "Backend asset manifest is unavailable"
printf '%s' "$manifest" | grep -Fq '"resources/js/Pages/Admin/Pages/Index.vue"' \
    || fail "Built asset manifest is missing the Admin Pages component"
asset_file="$(printf '%s\n' "$manifest" | awk '
    /"resources\/js\/Pages\/Admin\/Pages\/Index\.vue":/ { in_target = 1; next }
    in_target && /"file":/ {
        sub(/^.*"file":[[:space:]]*"/, "")
        sub(/".*$/, "")
        print
        exit
    }
')"
[[ "$asset_file" =~ ^assets/[A-Za-z0-9._/-]+\.js$ && "$asset_file" != *..* ]] \
    || fail "Admin Pages JavaScript asset path is invalid"
check_http "http://127.0.0.1:8901/build/$asset_file" \
    || fail "Admin Pages JavaScript asset is not being served"

FINAL_FRONTEND_IMAGE="$(docker inspect laptopplus-frontend --format '{{.Config.Image}}')" \
    || fail "Could not re-check the storefront image"
[[ "$FINAL_FRONTEND_IMAGE" == "$CURRENT_FRONTEND_IMAGE" ]] \
    || fail "Storefront image changed unexpectedly"

"${COMPOSE[@]}" ps
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend \
    --format '{{.Name}} IMAGE={{.Config.Image}} STATUS={{.State.Status}} HEALTH={{if .State.Health}}{{.State.Health.Status}}{{end}}'

ENV_UPDATED=0
DEPLOY_SUCCEEDED=1
echo "BACKUP_DIR=$BACKUP_DIR"
echo "BACKEND_SHA=$TARGET_SHA"
echo "BACKEND_IMAGE=laptopplus-backend:$NEW_TAG"
echo "BACKEND_NGINX_IMAGE=laptopplus-backend-nginx:$NEW_TAG"
echo "FRONTEND_IMAGE_UNCHANGED=$CURRENT_FRONTEND_IMAGE"
echo "MIGRATION=NOT_RUN"
echo "SEEDERS=NOT_RUN"
echo "DATABASE_CHANGED=NO"
echo "STOREFRONT_RECREATED=NO"
echo "LAPTOPPLUS_ADMIN_HOTFIX_DEPLOY_COMPLETE=YES"
