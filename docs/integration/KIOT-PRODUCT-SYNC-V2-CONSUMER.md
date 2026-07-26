# KIOT Product Consumer V2

## Provider contract

The canonical provider contract is `cuongdesignnb/kiot` commit
`fb3dde37ff2e826d920c76ae7ac3343b950ee3ff`. The Website PC consumer uses the
numeric KIOT category, product and image IDs as remote identities. SKU is
case-sensitive conflict metadata and is never the integration primary key.

Endpoints:

- `GET /api/integrations/v1/pc/categories`
- `GET /api/integrations/v1/pc/price-books`
- `GET /api/integrations/v1/pc/products`
- `GET /api/integrations/v1/pc/products/{sku}`

List endpoints use stable cursor pagination and accept `updated_since`.
Incremental reads are inclusive, so checksums and remote identities make every
upsert idempotent.

## Ownership

KIOT owns remote identity, SKU, barcode, provider name/basic description,
category assignment, selected price, inventory, availability, repair state,
publishing flags, active/tombstone state, weight, warranty and provider images.

Website PC owns SEO fields, marketing short content, layout, banners, featured
state, local specifications and local-only sale campaigns. A product slug is
generated on first import and remains stable. Provider description fills an
empty local description but does not overwrite later Website marketing edits.

## Publishing and purchasing

Categories and products are retained in admin for `inactive`, `deleted` or
`show_on_pc_website=false`. They are never hard-deleted by synchronization.
Storefront category/product queries apply the provider publishing flags.

A product is purchasable only when all conditions hold:

1. its category is active and visible on Website PC;
2. the product is active and visible on Website PC;
3. normalized availability is `available` with positive available quantity;
4. the selected KIOT price is greater than zero;
5. provider sync status is `active`.

The same backend rule protects cart and checkout. Repairing products may remain
visible but are never purchasable.

## Category and product upserts

Categories use unique `(provider, remote_category_id)` identity. Renames and
parent changes update the same row. Parent links are resolved after each page so
payload ordering cannot create duplicates.

Products use unique `(provider, remote_product_id)` identity. A matching remote
identity updates only KIOT-owned fields. If an unmapped local product already
uses the exact-case SKU, synchronization records an operator-visible conflict
and does not create or overwrite a product.

`pricing.selected_price` is the only KIOT selling price consumed. Monetary
values are normalized as integer decimal strings and never processed as floats.
`fallback_used=true` is accepted and reported. A zero selected price imports the
record but blocks purchasing.

## Image mirroring

Provider images are downloaded only during an apply run. The downloader accepts
HTTPS URLs from the configured provider origin, revalidates public DNS in
production, disables redirects, applies connect/request timeouts and a maximum
body size, validates decoded JPEG/PNG/WebP content and stores a checksum-derived
filename on the Website public disk. Executables and caller-controlled paths are
never used.

An unchanged checksum is skipped. A changed image is replaced and a removed
provider relation is archived by deleting only the Website relation and its
unreferenced mirrored file. Image failures become run warnings and do not abort
the product upsert.

## Runs, locking and flags

Every dry-run, full or incremental request creates an `integration_sync_runs`
record with progress, totals, warnings and terminal status. Dry-run persists
only its run/report; it never writes categories/products/images or cursors.

Apply runs require all runtime KIOT/product flags and use a distributed cache
lock. Scheduled incremental synchronization remains disabled while flags are
OFF. Queue retries are bounded and use backoff for connection, HTTP 429 and 5xx
failures; business conflicts are recorded without infinite retry.

Production flags remain OFF throughout Phase 3B. Phase 3B does not deploy or run
a production import.

## Migration and rollback

Migrations are additive and indexed for MySQL. No data backfill is required:
existing Website records retain local visibility defaults and remain unmapped.
Rollback drops only the new integration metadata/run/conflict structures. It
does not delete existing products/categories or automatically remove mirrored
files. Expected lock risk is low-to-moderate metadata locking while columns and
indexes are added to current catalog tables.
