#!/usr/bin/env bash

set -Eeuo pipefail

# One-command production deployment for laptopplus.vn.
# Default:
#   curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-production.sh | bash
# Optional arguments pin backend and frontend SHAs.

requested_backend_sha="${1:-}"
requested_frontend_sha="${2:-}"

STACK_DIR="${STACK_DIR:-/www/docker/laptopplus.vn}"
FRONTEND_REPO="${FRONTEND_REPO:-/www/wwwroot/pcfrontend}"
PRODUCTION_DEPLOY_DIR="${PRODUCTION_DEPLOY_DIR:-$STACK_DIR/deploy/production}"
FRONTEND_DOCKERFILE="${FRONTEND_DOCKERFILE:-/www/docker/laptopplus-frontend/Dockerfile.production}"
STACK_ENV="${STACK_ENV:-$STACK_DIR/deploy/production/stack.env}"
COMPOSE_FILE="${COMPOSE_FILE:-$STACK_DIR/docker-compose.production.yml}"
DEPLOY_LOCK="${DEPLOY_LOCK:-/tmp/laptopplus-production-deploy.lock}"
FRONTEND_HEALTH_PATH="${FRONTEND_HEALTH_PATH:-/}"

CURRENT_STEP=initialization
ERROR_REPORTED=0
ENV_UPDATED=0
ROLLBACK_DONE=0
DEPLOY_SUCCEEDED=0
COMPOSE_READY=0
BACKEND_CONTEXT_DIR=
FRONTEND_SOURCE_DIR=
BACKUP_DIR=
BACKUP_STACK_ENV=
COMPOSE=()

MIGRATION_STATUS=NOT_RUN
COMPONENT_TYPE_SEEDER_STATUS=NOT_RUN
SPECIFICATION_KEY_SEEDER_STATUS=NOT_RUN
COMPATIBILITY_RULE_SEEDER_STATUS=NOT_RUN
BUILDER_CATALOG_SEEDER_STATUS=NOT_RUN
SEEDER_STATUS=NOT_RUN

fail() {
    ERROR_REPORTED=1
    echo "DEPLOY_ERROR=$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

step() {
    printf '\n[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
}

cleanup() {
    [ -z "$BACKEND_CONTEXT_DIR" ] || rm -rf -- "$BACKEND_CONTEXT_DIR" || true
    [ -z "$FRONTEND_SOURCE_DIR" ] || rm -rf -- "$FRONTEND_SOURCE_DIR" || true
}

rollback() {
    [ "$ENV_UPDATED" -eq 1 ] || return 0
    [ "$ROLLBACK_DONE" -eq 0 ] || return 0

    ROLLBACK_DONE=1
    echo "ROLLBACK=START" >&2
    if [ -n "$BACKUP_STACK_ENV" ] && [ -f "$BACKUP_STACK_ENV" ]; then
        cp -p "$BACKUP_STACK_ENV" "$STACK_ENV" || true
    fi
    if [ "$COMPOSE_READY" -eq 1 ]; then
        "${COMPOSE[@]}" up -d --no-build --force-recreate \
            backend-php backend-nginx queue scheduler frontend || true
    fi
    ENV_UPDATED=0
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
trap 'exit 129' HUP

CURRENT_STEP=preflight
for command in git docker curl sed cp mktemp flock grep awk tar date; do
    require_command "$command"
done

test -d "$STACK_DIR" || fail "Stack directory not found: $STACK_DIR"
test -d "$FRONTEND_REPO" || fail "Frontend repository not found: $FRONTEND_REPO"
test -f "$PRODUCTION_DEPLOY_DIR/backend.Dockerfile" \
    || fail "Production Dockerfile not found: $PRODUCTION_DEPLOY_DIR/backend.Dockerfile"
test -f "$PRODUCTION_DEPLOY_DIR/backend-nginx.conf" \
    || fail "Production Nginx config not found: $PRODUCTION_DEPLOY_DIR/backend-nginx.conf"
test -f "$FRONTEND_DOCKERFILE" || fail "Frontend Dockerfile not found: $FRONTEND_DOCKERFILE"
test -f "$STACK_ENV" || fail "Stack env not found: $STACK_ENV"
test -f "$COMPOSE_FILE" || fail "Compose file not found: $COMPOSE_FILE"
grep -q '^APP_IMAGE_TAG=' "$STACK_ENV" || fail "APP_IMAGE_TAG is missing from $STACK_ENV"
grep -q '^FRONTEND_IMAGE_TAG=' "$STACK_ENV" || fail "FRONTEND_IMAGE_TAG is missing from $STACK_ENV"

exec 9>"$DEPLOY_LOCK"
flock -n 9 || fail "Another production deploy is already running"

step "Fetching backend main"
CURRENT_STEP=fetch_backend
git -C "$STACK_DIR" fetch --no-tags --prune origin main
step "Fetching frontend main"
CURRENT_STEP=fetch_frontend
git -C "$FRONTEND_REPO" fetch --no-tags --prune origin main

BACKEND_SHA="${requested_backend_sha:-$(git -C "$STACK_DIR" rev-parse origin/main)}"
FRONTEND_SHA="${requested_frontend_sha:-$(git -C "$FRONTEND_REPO" rev-parse origin/main)}"
[[ "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Backend SHA must be 40 hexadecimal characters"
[[ "$FRONTEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Frontend SHA must be 40 hexadecimal characters"
test "$(git -C "$STACK_DIR" rev-parse origin/main)" = "$BACKEND_SHA" \
    || fail "Backend SHA is not origin/main"
test "$(git -C "$FRONTEND_REPO" rev-parse origin/main)" = "$FRONTEND_SHA" \
    || fail "Frontend SHA is not origin/main"

BACKEND_TAG="${BACKEND_SHA:0:7}"
FRONTEND_TAG="${FRONTEND_SHA:0:7}"
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"

BACKEND_CONTEXT_DIR="$(mktemp -d "/tmp/laptopplus-backend-context-${BACKEND_TAG}-XXXXXX")"
FRONTEND_SOURCE_DIR="$(mktemp -d "/tmp/pcfrontend-${FRONTEND_TAG}-XXXXXX")"

CURRENT_STEP=prepare_context
step "Preparing immutable source contexts"
git -C "$STACK_DIR" archive --format=tar "$BACKEND_SHA" \
    | tar -xf - -C "$BACKEND_CONTEXT_DIR"
git -C "$FRONTEND_REPO" archive --format=tar "$FRONTEND_SHA" \
    | tar -xf - -C "$FRONTEND_SOURCE_DIR"
mkdir -p "$BACKEND_CONTEXT_DIR/deploy/production"
cp -a "$PRODUCTION_DEPLOY_DIR"/. "$BACKEND_CONTEXT_DIR/deploy/production/"

BACKEND_CONTEXT_DOCKERFILE="$BACKEND_CONTEXT_DIR/deploy/production/backend.Dockerfile"
test -f "$BACKEND_CONTEXT_DOCKERFILE" || fail "Backend Dockerfile missing from context"
test -f "$BACKEND_CONTEXT_DIR/deploy/production/backend-nginx.conf" \
    || fail "Backend Nginx config missing from context"

CURRENT_STEP=backend_php_build
step "Building laptopplus-backend:$BACKEND_TAG"
docker build --pull --progress=plain --target php \
    -f "$BACKEND_CONTEXT_DOCKERFILE" \
    -t "laptopplus-backend:$BACKEND_TAG" \
    "$BACKEND_CONTEXT_DIR"

CURRENT_STEP=backend_nginx_build
step "Building laptopplus-backend-nginx:$BACKEND_TAG"
docker build --pull --progress=plain --target nginx \
    -f "$BACKEND_CONTEXT_DOCKERFILE" \
    -t "laptopplus-backend-nginx:$BACKEND_TAG" \
    "$BACKEND_CONTEXT_DIR"

CURRENT_STEP=frontend_build
step "Building laptopplus-frontend:$FRONTEND_TAG"
docker build --pull --progress=plain \
    --build-arg NUXT_PUBLIC_API_BASE=/api/v1 \
    --build-arg NUXT_API_PROXY_TARGET=http://backend-nginx \
    -f "$FRONTEND_DOCKERFILE" \
    -t "laptopplus-frontend:$FRONTEND_TAG" \
    "$FRONTEND_SOURCE_DIR"

for image in \
    "laptopplus-backend:$BACKEND_TAG" \
    "laptopplus-backend-nginx:$BACKEND_TAG" \
    "laptopplus-frontend:$FRONTEND_TAG"
do
    docker image inspect "$image" >/dev/null 2>&1 \
        || fail "Built image is missing: $image"
done

CURRENT_STEP=backup
BACKUP_DIR="/www/backups/laptopplus.vn/${BACKEND_TAG}-${FRONTEND_TAG}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
BACKUP_STACK_ENV="$BACKUP_DIR/stack.env"
cp -p "$STACK_ENV" "$BACKUP_STACK_ENV"
PREVIOUS_BACKEND_TAG="$(sed -n 's/^APP_IMAGE_TAG=//p' "$STACK_ENV" | head -n 1)"
PREVIOUS_FRONTEND_TAG="$(sed -n 's/^FRONTEND_IMAGE_TAG=//p' "$STACK_ENV" | head -n 1)"
printf 'BACKEND_IMAGE_TAG=%s\nFRONTEND_IMAGE_TAG=%s\n' \
    "$PREVIOUS_BACKEND_TAG" "$PREVIOUS_FRONTEND_TAG" \
    > "$BACKUP_DIR/previous-images.env"

update_stack_env() {
    local temporary_env
    temporary_env="$(mktemp "${STACK_ENV}.tmp.XXXXXX")"
    if ! awk -v backend="$BACKEND_TAG" -v frontend="$FRONTEND_TAG" '
        BEGIN { backend_seen = 0; frontend_seen = 0 }
        /^APP_IMAGE_TAG=/ {
            print "APP_IMAGE_TAG=" backend
            backend_seen = 1
            next
        }
        /^FRONTEND_IMAGE_TAG=/ {
            print "FRONTEND_IMAGE_TAG=" frontend
            frontend_seen = 1
            next
        }
        { print }
        END {
            if (!backend_seen || !frontend_seen) exit 42
        }
    ' "$STACK_ENV" > "$temporary_env"; then
        rm -f -- "$temporary_env"
        return 1
    fi
    mv -f -- "$temporary_env" "$STACK_ENV"
}

CURRENT_STEP=update_stack_env
step "Switching stack to new image tags"
update_stack_env || fail "Could not update $STACK_ENV atomically"
ENV_UPDATED=1
COMPOSE=(docker compose --env-file "$STACK_ENV" -f "$COMPOSE_FILE")
COMPOSE_READY=1
"${COMPOSE[@]}" config --quiet

CURRENT_STEP=dependencies
step "Ensuring database, Redis and Meilisearch are available"
"${COMPOSE[@]}" up -d --no-build mysql redis meilisearch

CURRENT_STEP=migrate
step "Running Laravel migrations"
"${COMPOSE[@]}" run --rm --no-deps backend-php php artisan migrate --force
MIGRATION_STATUS=RUN

SEEDERS=(
    ComponentTypeSeeder
    SpecificationKeySeeder
    CompatibilityRuleSeeder
    BuilderCatalogSeeder
    BuildPresetSeeder
)

for seeder in "${SEEDERS[@]}"; do
    CURRENT_STEP="seed_$seeder"
    step "Running $seeder"
    "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed \
        --class="$seeder" --force
    case "$seeder" in
        ComponentTypeSeeder) COMPONENT_TYPE_SEEDER_STATUS="$seeder" ;;
        SpecificationKeySeeder) SPECIFICATION_KEY_SEEDER_STATUS="$seeder" ;;
        CompatibilityRuleSeeder) COMPATIBILITY_RULE_SEEDER_STATUS="$seeder" ;;
        BuilderCatalogSeeder) BUILDER_CATALOG_SEEDER_STATUS="$seeder" ;;
        BuildPresetSeeder) SEEDER_STATUS="$seeder" ;;
    esac
done

CURRENT_STEP=compose_up
step "Recreating backend, workers and frontend"
"${COMPOSE[@]}" up -d --no-build --force-recreate \
    backend-php backend-nginx queue scheduler frontend

wait_for_health() {
    local attempt container state ready
    for attempt in {1..60}; do
        ready=1
        for container in laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend; do
            state="$(docker inspect "$container" \
                --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}' \
                2>/dev/null || true)"
            [ "$state" = "running|healthy" ] || ready=0
        done
        [ "$ready" -eq 1 ] && return 0
        printf 'HEALTH_WAIT attempt=%s/60\n' "$attempt"
        sleep 2
    done
    return 1
}

CURRENT_STEP=health_check
step "Waiting for application containers to become healthy"
wait_for_health || {
    "${COMPOSE[@]}" ps
    fail "Container health check failed"
}

check_http() {
    local url="$1"
    local status
    status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$url")" \
        || status=000
    printf '%s HTTP=%s\n' "$url" "$status"
    [[ "$status" == 2?? ]]
}

CURRENT_STEP=http_check
step "Checking backend API and frontend"
check_http http://127.0.0.1:8901/healthz \
    || fail "Backend health endpoint failed"
check_http http://127.0.0.1:8901/api/v1/menus/header \
    || fail "Header menu API check failed"
check_http http://127.0.0.1:8901/api/v1/builder/component-types \
    || fail "Builder component types API check failed"
check_http http://127.0.0.1:8901/api/v1/builder/presets \
    || fail "Builder presets API check failed"
check_http "http://127.0.0.1:8902$FRONTEND_HEALTH_PATH" \
    || fail "Frontend HTTP check failed"

CURRENT_STEP=finalize
"${COMPOSE[@]}" ps
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend \
    --format '{{.Name}} IMAGE={{.Config.Image}} STATUS={{.State.Status}} HEALTH={{if .State.Health}}{{.State.Health.Status}}{{end}}'

ENV_UPDATED=0
DEPLOY_SUCCEEDED=1
echo "BACKUP_DIR=$BACKUP_DIR"
echo "BACKEND_SHA=$BACKEND_SHA"
echo "FRONTEND_SHA=$FRONTEND_SHA"
echo "BACKEND_IMAGE=laptopplus-backend:$BACKEND_TAG"
echo "BACKEND_NGINX_IMAGE=laptopplus-backend-nginx:$BACKEND_TAG"
echo "FRONTEND_IMAGE=laptopplus-frontend:$FRONTEND_TAG"
echo "MIGRATION=$MIGRATION_STATUS"
echo "COMPONENT_TYPE_SEEDER=$COMPONENT_TYPE_SEEDER_STATUS"
echo "SPECIFICATION_KEY_SEEDER=$SPECIFICATION_KEY_SEEDER_STATUS"
echo "COMPATIBILITY_RULE_SEEDER=$COMPATIBILITY_RULE_SEEDER_STATUS"
echo "BUILDER_CATALOG_SEEDER=$BUILDER_CATALOG_SEEDER_STATUS"
echo "SEEDER=$SEEDER_STATUS"
echo "DATABASE_CHANGED=YES"
echo "LAPTOPPLUS_DEPLOY_COMPLETE=YES"
