# Slug migration runbook

The migration is intentionally two-step. Planning is read-only; applying is
blocked unless the exact plan checksum and an operator/change-ticket value are
provided.

```bash
php artisan seo:slug-audit
php artisan seo:slug-plan --output=storage/app/seo/slug-plan.json
php artisan seo:slug-migrate storage/app/seo/slug-plan.json
php artisan seo:slug-migrate storage/app/seo/slug-plan.json \
  --apply \
  --checksum="<PLAN_CHECKSUM>" \
  --approved-by="<ticket-or-operator>"
```

Before `--apply`, review every `REVIEW` and `COLLISION` row. The command
refuses to mutate while either decision remains. It also refuses a stale row,
checksum mismatch, reserved target, duplicate target, missing entity, or a
target that would create an alias collision. The transaction first assigns
temporary slugs to safely handle swaps, then writes metadata and one-hop
history rows.

After applying, verify current public URLs, old aliases, 404 behaviour,
canonical tags, robots, sitemap shards, and SSR HTML. Keep the generated
manifest and checksum with the deployment/change record.

## Controlled admin slug change

Normal CRUD edits keep a published slug locked. Users with the
`seo.slugs.manage` permission can use the separate JSON action:

```text
GET  /admin/seo/slugs/{entity_type}/{entity_id}/preview?new_slug=...
POST /admin/seo/slugs/{entity_type}/{entity_id}/change
     new_slug=...&reason=...
```

The preview returns the old/new paths, affected URL count and redirect
decision without writing. The change action locks the entity row, checks the
slug and path namespaces again, writes the new slug, and records a single-hop
301 in `slug_histories` in the same transaction. Category changes also record
direct PDP aliases for products assigned to that category. Reusing a previous
public alias is rejected.
