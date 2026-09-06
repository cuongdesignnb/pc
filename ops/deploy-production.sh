#!/usr/bin/env bash

set -Eeuo pipefail

fail() {
    echo "DEPLOY_ERROR=$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

requested_backend_sha="${1:-}"
requested_frontend_sha="${2:-}"

STACK_DIR="${STACK_DIR:-/www/docker/laptopplus.vn}"
FRONTEND_REPO="${FRONTEND_REPO:-/www/wwwroot/pcfrontend}"
PRODUCTION_DEPLOY_DIR="${PRODUCTION_DEPLOY_DIR:-$STACK_DIR/deploy/production}"
FRONTEND_DOCKERFILE="${FRONTEND_DOCKERFILE:-/www/docker/laptopplus-frontend/Dockerfile.production}"
STACK_ENV="${STACK_ENV:-$STACK_DIR/deploy/production/stack.env}"
COMPOSE_FILE="${COMPOSE_FILE:-$STACK_DIR/docker-compose.production.yml}"

for command in git docker curl sed cp mktemp flock grep; do
    require_command "$command"
done

test -d "$STACK_DIR" || fail "Stack directory not found: $STACK_DIR"
test -d "$FRONTEND_REPO" || fail "Frontend repository not found: $FRONTEND_REPO"
test -f "$PRODUCTION_DEPLOY_DIR/backend.Dockerfile" || fail "Production Dockerfile not found: $PRODUCTION_DEPLOY_DIR/backend.Dockerfile"
test -f "$PRODUCTION_DEPLOY_DIR/backend-nginx.conf" || fail "Production Nginx config not found: $PRODUCTION_DEPLOY_DIR/backend-nginx.conf"
test -f "$FRONTEND_DOCKERFILE" || fail "Frontend Dockerfile not found: $FRONTEND_DOCKERFILE"
test -f "$STACK_ENV" || fail "Stack env not found: $STACK_ENV"
test -f "$COMPOSE_FILE" || fail "Compose file not found: $COMPOSE_FILE"
grep -q '^APP_IMAGE_TAG=' "$STACK_ENV" || fail "APP_IMAGE_TAG is missing from $STACK_ENV"
grep -q '^FRONTEND_IMAGE_TAG=' "$STACK_ENV" || fail "FRONTEND_IMAGE_TAG is missing from $STACK_ENV"

exec 9>"/tmp/laptopplus-production-deploy.lock"
flock -n 9 || fail "Another production deploy is already running"

git -C "$STACK_DIR" fetch --no-tags origin main
git -C "$FRONTEND_REPO" fetch --no-tags origin main

BACKEND_SHA="${requested_backend_sha:-$(git -C "$STACK_DIR" rev-parse origin/main)}"
FRONTEND_SHA="${requested_frontend_sha:-$(git -C "$FRONTEND_REPO" rev-parse origin/main)}"

[[ "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Backend SHA must be a 40-character commit SHA"
[[ "$FRONTEND_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Frontend SHA must be a 40-character commit SHA"
test "$(git -C "$STACK_DIR" rev-parse origin/main)" = "$BACKEND_SHA" || fail "Backend SHA is not origin/main"
test "$(git -C "$FRONTEND_REPO" rev-parse origin/main)" = "$FRONTEND_SHA" || fail "Frontend SHA is not origin/main"

BACKEND_TAG="${BACKEND_SHA:0:7}"
FRONTEND_TAG="${FRONTEND_SHA:0:7}"
BACKEND_SOURCE_DIR="$(mktemp -d "/tmp/laptopplus-backend-${BACKEND_TAG}-XXXXXX")"
FRONTEND_SOURCE_DIR="$(mktemp -d "/tmp/pcfrontend-${FRONTEND_TAG}-XXXXXX")"
BACKEND_CONTEXT_DIR="$(mktemp -d "/tmp/laptopplus-backend-context-${BACKEND_TAG}-XXXXXX")"

cleanup() {
    git -C "$STACK_DIR" worktree remove --force "$BACKEND_SOURCE_DIR" >/dev/null 2>&1 || true
    git -C "$FRONTEND_REPO" worktree remove --force "$FRONTEND_SOURCE_DIR" >/dev/null 2>&1 || true
    rm -rf -- "$BACKEND_CONTEXT_DIR"
}
trap cleanup EXIT

git -C "$STACK_DIR" worktree add --detach "$BACKEND_SOURCE_DIR" "$BACKEND_SHA"
git -C "$FRONTEND_REPO" worktree add --detach "$FRONTEND_SOURCE_DIR" "$FRONTEND_SHA"

# The production Dockerfile is server-owned, while the application source comes from the target commit.
cp -a "$BACKEND_SOURCE_DIR"/. "$BACKEND_CONTEXT_DIR"/
mkdir -p "$BACKEND_CONTEXT_DIR/deploy/production"
cp -a "$PRODUCTION_DEPLOY_DIR"/. "$BACKEND_CONTEXT_DIR/deploy/production/"

BACKEND_CONTEXT_DOCKERFILE="$BACKEND_CONTEXT_DIR/deploy/production/backend.Dockerfile"
test -f "$BACKEND_CONTEXT_DOCKERFILE"
test -f "$BACKEND_CONTEXT_DIR/deploy/production/backend-nginx.conf"

docker build --pull \
    --target php \
    -f "$BACKEND_CONTEXT_DOCKERFILE" \
    -t "laptopplus-backend:$BACKEND_TAG" \
    "$BACKEND_CONTEXT_DIR"

docker build --pull \
    --target nginx \
    -f "$BACKEND_CONTEXT_DOCKERFILE" \
    -t "laptopplus-backend-nginx:$BACKEND_TAG" \
    "$BACKEND_CONTEXT_DIR"

docker build --pull \
    --build-arg NUXT_PUBLIC_API_BASE=/api/v1 \
    --build-arg NUXT_API_PROXY_TARGET=http://backend-nginx \
    -f "$FRONTEND_DOCKERFILE" \
    -t "laptopplus-frontend:$FRONTEND_TAG" \
    "$FRONTEND_SOURCE_DIR"

BACKUP_DIR="/www/backups/laptopplus.vn/${BACKEND_TAG}-${FRONTEND_TAG}-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -a "$STACK_ENV" "$BACKUP_DIR/stack.env"

PREVIOUS_BACKEND_TAG="$(sed -n 's/^APP_IMAGE_TAG=//p' "$STACK_ENV")"
PREVIOUS_FRONTEND_TAG="$(sed -n 's/^FRONTEND_IMAGE_TAG=//p' "$STACK_ENV")"
printf 'BACKEND_IMAGE_TAG=%s\nFRONTEND_IMAGE_TAG=%s\n' "$PREVIOUS_BACKEND_TAG" "$PREVIOUS_FRONTEND_TAG" > "$BACKUP_DIR/previous-images.env"

sed -i -E \
    "s#^APP_IMAGE_TAG=.*#APP_IMAGE_TAG=$BACKEND_TAG#; s#^FRONTEND_IMAGE_TAG=.*#FRONTEND_IMAGE_TAG=$FRONTEND_TAG#" \
    "$STACK_ENV"

COMPOSE=(docker compose --env-file "$STACK_ENV" -f "$COMPOSE_FILE")
ENV_UPDATED=1
MIGRATION_STATUS=NOT_RUN
COMPONENT_TYPE_SEEDER_STATUS=NOT_RUN
SPECIFICATION_KEY_SEEDER_STATUS=NOT_RUN
COMPATIBILITY_RULE_SEEDER_STATUS=NOT_RUN
BUILDER_CATALOG_SEEDER_STATUS=NOT_RUN
SEEDER_STATUS=NOT_RUN

rollback() {
    if [ "$ENV_UPDATED" -eq 1 ]; then
        sed -i -E \
            "s#^APP_IMAGE_TAG=.*#APP_IMAGE_TAG=$PREVIOUS_BACKEND_TAG#; s#^FRONTEND_IMAGE_TAG=.*#FRONTEND_IMAGE_TAG=$PREVIOUS_FRONTEND_TAG#" \
            "$STACK_ENV"
        "${COMPOSE[@]}" up -d --force-recreate backend-php backend-nginx queue scheduler frontend || true
    fi
}

# Run only the idempotent application migration and builder preset seeder from
# the new backend image. The full demo seeder is intentionally never executed
# during production deployment.
if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan migrate --force; then
    rollback
    fail "Database migration failed; previous image tags were restored"
fi
MIGRATION_STATUS=RUN

if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed --class=ComponentTypeSeeder --force; then
    rollback
    fail "Component type seeder failed; previous image tags were restored"
fi
COMPONENT_TYPE_SEEDER_STATUS=ComponentTypeSeeder

if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed --class=SpecificationKeySeeder --force; then
    rollback
    fail "Specification key seeder failed; previous image tags were restored"
fi
SPECIFICATION_KEY_SEEDER_STATUS=SpecificationKeySeeder

if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed --class=CompatibilityRuleSeeder --force; then
    rollback
    fail "Compatibility rule seeder failed; previous image tags were restored"
fi
COMPATIBILITY_RULE_SEEDER_STATUS=CompatibilityRuleSeeder

if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed --class=BuilderCatalogSeeder --force; then
    rollback
    fail "Builder catalog seeder failed; previous image tags were restored"
fi
BUILDER_CATALOG_SEEDER_STATUS=BuilderCatalogSeeder

if ! "${COMPOSE[@]}" run --rm --no-deps backend-php php artisan db:seed --class=BuildPresetSeeder --force; then
    rollback
    fail "Builder preset seeder failed; previous image tags were restored"
fi
SEEDER_STATUS=BuildPresetSeeder

if ! "${COMPOSE[@]}" up -d --force-recreate backend-php backend-nginx queue scheduler frontend; then
    rollback
    fail "Compose start failed; previous image tags were restored"
fi

wait_for_health() {
    local attempt container state ready

    for attempt in {1..60}; do
        ready=1
        for container in laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend; do
            state="$(docker inspect "$container" --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}' 2>/dev/null || true)"
            if [ "$state" != "running|healthy" ]; then
                ready=0
            fi
        done

        if [ "$ready" -eq 1 ]; then
            return 0
        fi

        sleep 2
    done

    return 1
}

if ! wait_for_health; then
    "${COMPOSE[@]}" ps
    rollback
    fail "Container health check failed; previous image tags were restored"
fi

check_http() {
    local url="$1"
    local status

    status="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time 20 "$url")" || return 1
    printf '%s HTTP %s\n' "$url" "$status"
    [[ "$status" == 2?? ]]
}

if ! check_http "http://127.0.0.1:8901/api/v1/categories/linh-kien" \
    || ! check_http "http://127.0.0.1:8901/api/v1/menus/header" \
    || ! check_http "http://127.0.0.1:8901/api/v1/builder/component-types" \
    || ! check_http "http://127.0.0.1:8901/api/v1/builder/presets" \
    || ! check_http "http://127.0.0.1:8902/cau-hinh"; then
    rollback
    fail "HTTP verification failed; previous image tags were restored"
fi

"${COMPOSE[@]}" ps
docker inspect laptopplus-backend-php laptopplus-backend-nginx laptopplus-frontend \
    --format '{{.Name}} IMAGE={{.Config.Image}} STATUS={{.State.Status}} HEALTH={{if .State.Health}}{{.State.Health.Status}}{{end}}'

ENV_UPDATED=0
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
