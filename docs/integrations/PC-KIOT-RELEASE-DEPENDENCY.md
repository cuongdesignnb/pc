# PC release dependency on KIOT provider

PC PR #1 is the consumer half of the frozen KIOT and PC V1 release pair. Its
reviewed application head is
`bcb7a82c22dcbccacddc9c00d9e966f3418aba47`. It depends on KIOT provider head
`691a7c6932978a10d7cce8937257574ad60dfc42` in KIOT PR #32.

The KIOT SHA contains the previously verified provider
`b5a02d47194cff5bc96ccc93b1794b62f42508a7` plus the current
`production-customer-group` base. The new base change is confined to POS
checkout idempotency-key lifecycle; affected POS tests were rerun and the V1
provider contract is unchanged.

## Locked contract

- Base path: `/api/integrations/v1/pc`.
- Endpoints: product list/detail and Order create/status/cancel only.
- HMAC: `METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256_RAW_BODY`.
- Query is excluded from the canonical path; URL path segments are raw encoded.
- Retries keep payload, raw body, event ID, and idempotency key stable while
  regenerating timestamp, nonce, and signature.
- SKU comparison is exact-case; service Products are excluded.
- Product pagination, timezone-aware incremental watermarks, tombstones,
  duplicate success, business/retry error classes, and cancel states match.

All rows in the canonical contract matrix are `PASS`. The authoritative
manifest, matrix, security/migration result, and release decisions are in KIOT:

```text
docs/integrations/PC-KIOT-RELEASE-MANIFEST.md
docs/integrations/PC-KIOT-FINAL-SIGNOFF.md
docs/integrations/PC-KIOT-DEPLOY-WITH-FLAGS-OFF.md
```

## Mandatory release order

1. KIOT PR #32 receives required approval and a green final-head CI gate.
2. KIOT merges into `production-customer-group`.
3. KIOT deploys with `PC_INTEGRATION_ENABLED=false` and completes its smoke
   checkpoint.
4. Only after `KIOT_DEPLOY_WITH_FLAGS_OFF_VERIFIED=YES` may PC PR #1 merge.
5. PC deploys with every KIOT integration flag false and proves zero outbound
   KIOT requests.
6. A separately approved production dry run precedes any enablement.

PC defaults remain:

```dotenv
KIOT_INTEGRATION_ENABLED=false
KIOT_PRODUCT_SYNC_ENABLED=false
KIOT_ORDER_SYNC_ENABLED=false
```

Product and outbox schedules are additionally runtime-gated and use overlap and
single-server locks. With flags off, deployment must not sync Product data,
submit/cancel Orders, or show provider-dependent payment instructions.

## Merge and enablement gates

PC is not safe to merge before KIOT is deployed and verified with its flag off.
It is not safe to enable before the production connection test, product dry run,
SKU reconciliation, targeted Product/COD/duplicate/cancel/timeout pilots, stock
parity check, monitoring, and rollback switch have separate approval.

```text
MERGE_ORDER = KIOT_THEN_PC
PC_FLAGS_DEFAULT_OFF = YES
PC_MUST_NOT_ENABLE_BEFORE_PRODUCTION_DRY_RUN = YES
SAFE_TO_ENABLE_PRODUCTION = NO
```

No integration credential belongs in this document, Git, a PR body, or a CI log.
