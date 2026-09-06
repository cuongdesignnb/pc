# Production deploy

Deploy `laptopplus.vn` backend and frontend together from the latest `main` of
both repositories:

```bash
curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-production.sh | bash
```

The script fetches and validates both repositories, builds the PHP, Nginx and
Nuxt images, runs migrations and production-safe seeders, recreates the
application containers, waits for health checks, verifies the API/frontend,
and restores the previous image tags automatically if a step fails or the
process receives `HUP`, `TERM` or `INT`.

To deploy pinned commits when needed:

```bash
curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-production.sh | bash -s -- BACKEND_SHA FRONTEND_SHA
```

The server must keep these paths and files in place:

- `/www/docker/laptopplus.vn`
- `/www/docker/laptopplus.vn/deploy/production/stack.env`
- `/www/docker/laptopplus.vn/deploy/production/backend.Dockerfile`
- `/www/docker/laptopplus.vn/deploy/production/backend-nginx.conf`
- `/www/docker/laptopplus.vn/docker-compose.production.yml`
- `/www/wwwroot/pcfrontend`
- `/www/docker/laptopplus-frontend/Dockerfile.production`

Successful output ends with `LAPTOPPLUS_DEPLOY_COMPLETE=YES` and
`DEPLOY_STATUS=SUCCESS`. A failed or interrupted run prints
`DEPLOY_STATUS=FAILED` and attempts rollback before exiting.
