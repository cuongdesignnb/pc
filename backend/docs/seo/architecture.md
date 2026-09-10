# Technical SEO architecture

`VietnameseSlugNormalizer` is the only slug policy. `PublicUrlResolver` is the
only server-side source of storefront entity paths. `SlugRedirectService`
resolves current slugs and verified history aliases; it never invents a
homepage fallback. `IndexabilityPolicy` is shared by robots and sitemap
generation.

The backend emits canonical path metadata with product/category/article API
resources. The Nuxt server proxies the backend XML feeds and emits the
storefront-origin robots file. Public pages set SSR metadata from API values;
private pages use no-store and noindex route rules.

Sitemap layout:

* `/sitemap.xml` — index;
* `/sitemaps/static.xml` — allowlisted static pages;
* `/sitemaps/categories.xml` — visible categories;
* `/sitemaps/post-categories.xml` — news categories with at least one
  published post;
* `/sitemaps/products-N.xml` — visible products with a visible primary
  category;
* `/sitemaps/posts-N.xml` — published posts.

Only current canonical paths enter shards. Product offers are emitted only for
real positive prices; contact-price products have no fake `Offer`.
