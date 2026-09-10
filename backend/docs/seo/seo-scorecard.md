# SEO scorecard

| Area | Code evidence | Production evidence |
|---|---|---|
| Slug policy | `VietnameseSlugNormalizer`, metadata columns, audit/plan/apply commands | Run audit on production data |
| Alias redirects | `SlugHistory`, `SlugRedirectService`, legacy API/page resolvers | Check old URLs return one 301 |
| Canonical | `PublicUrlResolver` and API resource `seo.canonical_*` fields | Inspect SSR HTML on storefront origin |
| Robots/sitemap | `SeoController`, Nuxt proxy routes, allowlist shards | Fetch each shard and verify 200/404 set |
| Private routes | `IndexabilityPolicy`, Nuxt no-store route rules | Verify response headers and meta robots |
| Structured data | Homepage/article/product/category SSR schema paths | Validate rendered JSON-LD with real catalog data |
| Data integrity | no fake author/review/price/offer fallbacks in SEO payloads | Compare against admin/catalog source |
| Search visibility | not claimed by code | Requires Search Console/indexing evidence |

This document does not claim rankings or indexing. Those require a production
crawl and Search Console data after deployment.
