# Indexability matrix

## Public allowlist

Only the storefront origin may publish the homepage, product/category
directories, current category URLs, current product URLs, the news landing,
published articles, and useful static pages. Every public URL must render a
meaningful SSR document containing its title, canonical, H1, primary content,
and crawlable internal links.

## Private and utility routes

Cart, checkout, payment callbacks, order status, account, authentication,
wishlist, builder state, admin, API, feeds, health checks, and signed/query
utility URLs are not indexable. They must send `noindex` where a document is
rendered and `Cache-Control: private, no-store` for user-specific data.

## Query policy

Search, filters, brand/component filters, price ranges, variants, sort values,
and tracking parameters are noindex and never enter a sitemap. `/tim-kiem` and
the `/search` legacy alias redirect to the product listing with the query
preserved and remain noindex. `page=1` is
normalised away. Page 2+ is public only when the listing has useful content and
is self-canonical; it is not represented by a canonical page-1 URL.

## Status rules

Current public entities return 200 when active/published, including an
out-of-stock product. Missing, unpublished, deleted, or unknown resources
return 404/410 according to the existing application contract. No unknown URL
redirects to the homepage.
