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

## HPCom aaPanel deploy

HPCom uses a different runtime from Laptop Plus: Laravel is served by
aaPanel PHP-FPM from `/www/wwwroot/admin.hpcomvietnam.vn`, while the Nuxt
server runs as PM2 app `hpcom` from `/www/wwwroot/hpcomvietnam.vn`. Use the
dedicated script so the Docker deploy does not accidentally target the other
site:

```bash
curl --retry 5 --retry-delay 5 --connect-timeout 20 --max-time 120 -fsSL \
  https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-hpcom.sh \
  | DEPLOY_DETACH=1 bash
```

The script fetches the latest `main` commits from `pc` and `pcfrontend`,
backs up the active MySQL database, backend code, frontend output and runtime
fingerprints, then preserves `.env`, `storage`, uploads and aaPanel files.
It runs migrations after the backup but does not run seeders unless explicitly
requested. It reloads PM2, checks the admin/login and locations API, and
checks `/release.json` on `hpcomvietnam.vn` before reporting
`HPCOM_DEPLOY_COMPLETE=YES` and `DEPLOY_STATUS=SUCCESS`.

For a code-only deploy, set `RUN_MIGRATIONS=0`. For reviewed data changes,
seeders must be explicit, for example:

```bash
RUN_SEEDERS=1 SEEDERS="ReviewedSeeder" DEPLOY_DETACH=1 bash
```

The backup directory is printed in the final output. Database rollback is not
automatic because restoring a dump could overwrite orders or settings created
by another process; the dump remains available for an operator-approved
recovery.
