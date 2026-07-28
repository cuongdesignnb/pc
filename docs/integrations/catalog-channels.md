# Catalog channels: Google Sheets, Google Merchant and Meta

## Architecture

KIOT remains the product source. Website PC persists the KIOT contract and projects local products through one `CatalogProductData` DTO. Google Sheets, Google Merchant and Meta consume that same projection; none of the downstream channels reads KIOT directly or writes product data back.

```text
KIOT -> Website PC products -> CatalogProductData
                              |- Google Sheets batch upsert
                              |- Google Merchant XML artifact
                              `- Meta Catalog CSV artifact
```

The stable external ID is `kiot:<remote_product_id>`. A normalized `sku:<sku>` fallback is used only when an old mapped record has no remote ID. Products under repair remain in eligible commerce feeds with `inventory=0` and `availability=out_of_stock`.

## Eligibility and validation

A commerce item must be a non-deleted KIOT product, active, enabled for the PC website, in an active visible category, and have a SKU, title, positive price, public HTTPS product URL and public HTTPS image URL. Private IPs, localhost, `.local`, user-info URLs and non-HTTPS URLs are rejected.

Google Sheets receives all KIOT projections, including invalid, hidden and deleted rows. It writes `ACTIVE`, `HIDDEN`, `DELETED` or `INVALID` plus explicit validation codes. It upserts by `external_id`, rejects duplicate IDs/SKUs, escapes formula-leading text and performs batch writes rather than per-cell requests.

## Configuration and secrets

All channels are disabled by default. Google Service Account data is stored only in the encrypted `configuration_encrypted` model cast. Feed tokens are generated once, returned once to an authorized operator, and persisted only as a SHA-256 hash inside encrypted configuration. Admin responses never contain the Google private key or service account JSON.

Environment fallback values are available for initial setup:

```dotenv
CATALOG_STOREFRONT_URL=https://laptopplus.vn
GOOGLE_SHEETS_ENABLED=false
GOOGLE_SHEETS_SPREADSHEET_ID=
GOOGLE_SHEETS_WORKSHEET=Products
GOOGLE_SERVICE_ACCOUNT_JSON=
GOOGLE_MERCHANT_ENABLED=false
META_CATALOG_ENABLED=false
```

Never commit a Service Account document, private key, access token, feed URL token or real spreadsheet credential. A Service Account credential file, if used operationally, must remain outside the public directory and repository.

## Feed artifacts and access

Artifacts are streamed to temporary files, validated, and atomically renamed only after successful validation:

```text
storage/app/private/catalog-feeds/google-products.xml
storage/app/private/catalog-feeds/meta-products.csv
```

Scheduled fetch URLs are:

```text
GET /feeds/google/products.xml?token=<rotated-token>
GET /feeds/meta/products.csv?token=<rotated-token>
```

Invalid tokens and missing artifacts return `404`. Successful responses include the correct UTF-8 content type, `ETag`, `Last-Modified`, `Cache-Control` and `X-Content-Type-Options: nosniff`.

## Operations

```bash
php artisan catalog:google-sheets:dry-run
php artisan catalog:google-sheets:sync
php artisan catalog:feeds:build --dry-run
php artisan catalog:feeds:build
php artisan catalog:feeds:validate
```

Google Sheets and both feed builds are scheduled every 15 minutes with scheduler mutexes and application cache locks. Queue jobs have bounded retries and exponential backoff. A successful non-dry-run KIOT product sync emits `KiotProductSyncCompleted`; a queued listener dispatches enabled downstream channels. Dispatch failure is logged safely and cannot convert a successful KIOT sync into a failed source sync.

## Admin and audit

The management page is `/admin/integrations/catalog-channels`. Read access requires `catalog-channels.view`; configuration, test, token rotation, dry-run, sync and rebuild require `catalog-channels.manage`. Super-admin bypass remains unchanged. Staff receives neither permission by default.

Audit events exclude all secret-like keys. Configuration changes, enable/disable, connection tests, token rotation, dry-runs, queued syncs, completed syncs, failures and successful feed rebuilds are recorded alongside sanitized metadata.

## Rollback

Disable all three channels first. Stop catalog queue work, then roll back the Phase 4 migration. Rollback removes only the five catalog channel tables; it does not mutate `products`, `categories`, KIOT sync state, orders or KIOT credentials. Cached feed files may be removed separately after rollback if operational policy requires it.
