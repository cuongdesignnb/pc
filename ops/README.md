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

## aaPanel sites (many domains, one script)

Every site that runs Laravel through aaPanel PHP-FPM and the `pcfrontend`
Nuxt server through PM2 is deployed by `ops/deploy-aapanel.sh`. Laptop Plus is
not one of them; it keeps using the Docker script above.

Each site has one config file `<site>.env` with its directories, domains, PM2
app and port (no secrets). The script reads `/www/deploy/sites/<site>.env` on
the server first and falls back to `ops/sites/<site>.env` in this repository.
Start a new site from `ops/sites/example.env.template`.

Deploy one site:

```bash
curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-aapanel.sh | DEPLOY_DETACH=1 DEPLOY_FOLLOW=1 bash -s -- hpcom
```

Deploy every site in `/www/deploy/sites`, one after another, stopping at the
first failure:

```bash
curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-aapanel.sh | DEPLOY_DETACH=1 DEPLOY_FOLLOW=1 bash -s -- all
```

List the configured sites:

```bash
curl -fsSL https://raw.githubusercontent.com/cuongdesignnb/pc/main/ops/deploy-aapanel.sh | bash -s -- list
```

For each site the script fetches the latest `main` of `pc` and `pcfrontend`
into a dedicated mirror (`/www/deploy/<site>-source`), builds the Laravel/Vite
admin assets and the Nuxt storefront from immutable source, backs up the
site's MySQL database, backend code, previous admin bundle and frontend
output, keeps `.env`, `storage`, uploads and aaPanel files, runs migrations,
signals queue workers to restart, restarts PHP-FPM, reloads the PM2 app and
checks the admin login, locations API and `/release.json`. A failure restores
the code and frontend output; the database dump is kept for a manual restore.
Success ends with `AAPANEL_DEPLOY_COMPLETE=YES` and `DEPLOY_STATUS=SUCCESS`.

One-off overrides are environment variables and win over the config file, for
example `RUN_MIGRATIONS=0` for a code-only deploy or
`RUN_SEEDERS=1 SEEDERS="ReviewedSeeder"` for a reviewed data change.

Before the first deploy of a new site, create once by hand:

- the two aaPanel sites (admin running directory `/public`, storefront
  reverse proxy to `FRONTEND_PORT`), the database and SSL;
- `BACKEND_DIR/.env` and `FRONTEND_DIR/.env` with that site's values;
- the PM2 app named `PM2_APP` serving `FRONTEND_DIR/.output/server/index.mjs`
  on `FRONTEND_PORT`, plus a first `.output` build;
- the cron `php artisan schedule:run` every minute and a queue worker for
  `BACKEND_DIR`.

The script refuses to run when another config in `/www/deploy/sites` uses
the same `FRONTEND_PORT`, `PM2_APP`, `BACKEND_DIR` or `FRONTEND_DIR`. PHP-FPM
is shared by every site on the same PHP version, so each deploy restarts it
once; expect a brief pause on the other sites.
