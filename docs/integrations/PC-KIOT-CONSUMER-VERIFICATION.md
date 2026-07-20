# PC KIOT consumer verification

Verified on 2026-07-20 against the immutable KIOT provider checkout at
`b5a02d47194cff5bc96ccc93b1794b62f42508a7`. The website work started at
`87a4a3cb58731678da3cd7fd8823b70621831345` on
`integration/kiot-products-orders-v1`. No provider source, production data,
production flags, deployment, or merge was used.

## Contract alignment

The consumer uses the provider's `/api/integrations/v1/pc` product, order, and
cancel endpoints. Requests sign the exact raw body with the canonical method,
path (excluding query), Unix timestamp, fresh nonce, and SHA-256 body hash.
Retries preserve the raw body, payload hash, event ID, and idempotency key while
regenerating timestamp, nonce, and signature.

The verified provider contract differs from the earlier
`f8fce9fc0ea11a1449f0ed17df30c15ab01f7fdb` contract in three material areas:

| Area | Verified behavior | Consumer proof |
| --- | --- | --- |
| Service products | Excluded from list, detail, incremental, and tombstone flows | Local service SKU remains unmatched and cannot be sold |
| Incremental time | `updated_since` is timezone-aware; equal timestamps use an ID tie-break | RFC 3339 watermark, overlap, pagination, and same-time tests |
| Completed invoice | Order-to-invoice conversion follows the provider lock period | Live invoice conversion, cancellation rejection, and stock parity |

Provider error codes are classified before HTTP status. Business rejections and
fatal conflicts are terminal; authentication/configuration failures fail closed;
429, 5xx, connection failures, and timeouts are retried with bounded backoff.

## Product verification

Safe fixtures were created with
`scripts/uat/kiot-consumer-fixture.php`; the provider fixture used was
`D:\Kiot\kiotviet-clone\scripts\uat\pc-integration-fixture.php`. Both scripts
refuse to run unless `APP_ENV=testing` and the database name contains `test`.

The live dry run returned 10 remote products, 7 exact SKU matches, 3 remote
unmatched rows, and 2 local unmatched rows. Its before/after checksum remained
`f3cda6b26fae585cd3fd599e9bd3b3b026922f36cfab0df3ede55e9fc2a3a21c`.
Full apply mapped price, barcode, physical/reserved/available quantities,
serial metadata, weight, warranty, lifecycle state, and the website stock cache.
It preserved sale price, cost price, slug, category, brand, descriptions, SEO,
specifications, images, and featured state. Remote unmatched products were not
created and local unmatched products were not deleted.

Inactive, deleted, non-direct-sale, service, missing, and zero-available products
are blocked consistently by storefront, cart, checkout, and PC Builder. KIOT
SKU, price, stock, and barcode are read-only through admin form, crafted update,
mass assignment, and CSV import paths. Local product behavior is unchanged.

## Order, outbox, and payment verification

Local order, immutable line snapshots, reservation-free local stock behavior,
and the create outbox event are committed atomically. A stable checkout UUID is
stored with a hash-only guest access token. Sequential and live concurrent
duplicate checkout produced one local order and one create event; the live
statuses were 201 and 200 for the same order ID.

COD live UAT was accepted by KIOT, exposed the provider order ID/code, supported
authorized polling, and cancelled KIOT-first. Missing or wrong guest tokens
returned 404. A frozen create event replay returned provider duplicate success.
Five two-worker runs each claimed an outbox event exactly once. Stale locks are
recoverable, future retry timestamps are not claimed, and rejected/sent/dead
letter events are not resent.

The stock=1 concurrency scenario ran five times. Every run had exactly one 201
and one 422 `INSUFFICIENT_AVAILABLE_STOCK`; only one provider reservation was
created. Serial-product checkout omitted serial/IMEI data. A completed invoice
of quantity 2 produced physical/reserved/available values 8/0/8 in KIOT and the
website cache; `STOCK_PARITY_DRIFT=0`. Cancelling the invoiced order returned
`ORDER_ALREADY_INVOICED` and did not mutate local order or stock.

SePay QR/payment instructions remain absent until KIOT accepts the order. A
webhook received before provider recovery was persisted idempotently and later
reconciled after the create event succeeded. The webhook fails closed unless it
receives `Authorization: Apikey ...`; the gateway IPN separately fails closed
unless it receives `X-Secret-Key`. No payment mutation is sent to KIOT.

## Security and operations

Guest order read, payment polling, and cancellation require the matching
high-entropy token; authenticated reads require ownership. Order presenters do
not expose the token hash, idempotency keys, integration payloads, or customer
secrets. Admin integration pages require explicit settings permissions and mask
the integration secret.

Product sync is scheduled every five minutes and outbox recovery every minute.
Both use overlap protection, run on one scheduler server, and have runtime flag
conditions. Integration flags default off; KIOT cancellation fails closed when
the relevant flags are disabled.

## Validation record

- Backend: 35 tests, 177 assertions, zero failures in 28.31 seconds.
- Changed PHP files: 20/20 pass Pint and PHP lint. Repository-wide Pint still
  reports 73 pre-existing style issues outside the integration change set.
- Baseline: `origin/main` also redirects `/` to `/admin`; the obsolete 200
  expectation was corrected in a separate commit without changing behavior.
- Live connection: HTTP 200 in 988 ms; no database write.
- Migration: fresh migrate, rollback of the three integration migrations, and
  re-migrate passed; pre-existing product/order counts were preserved.
- Admin frontend: clean install and production build passed. The system Node
  20.15.1 is below Vite's requirement; build verification used bundled Node
  24.14.0.
- Storefront: clean `npm ci` from the normalized lockfile and Nuxt 4.3.1
  production SSR/Nitro build passed. External font metadata was unavailable in
  the sandbox but is non-fatal.
- Browser: the production bundle rendered and hydrated on `localhost:3000`;
  an order page without a guest token showed only the access-denied state and
  produced no new console warning/error.
- Evidence: masked machine artifacts are under
  `backend/storage/app/audit/kiot-consumer-verification-20260720/` and remain
  ignored. They contain no credentials or production/customer data.

Release sequence remains: cross-repository sign-off, merge/deploy KIOT first
with flags off, then merge/deploy PC with flags off, production dry run, and only
then a separately approved controlled enablement. This verification does not
authorize merge, deployment, or production flag changes.
