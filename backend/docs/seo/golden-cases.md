# Golden SEO cases

The following cases are the minimum review set for the shared policy and
should be covered by automated tests or a production smoke check:

1. `Card màn hình` → `card-man-hinh`.
2. `Đà Nẵng` → `da-nang`.
3. `đ` and `Đ` map consistently.
4. Precomposed and decomposed Vietnamese accents match.
5. Repeated punctuation creates one hyphen.
6. Zero-width/control characters are rejected or safely separated.
7. Empty/emoji-only source is rejected.
8. Source over 160 ASCII characters is rejected.
9. Uppercase custom slug is rejected, not rewritten.
10. Custom slug with whitespace is rejected.
11. URL/query/fragment/percent custom slug is rejected.
12. `api`, `admin`, `sitemap.xml`, and `san-pham` are reserved.
13. Category slug colliding with a product is blocked.
14. Two products with one generated candidate are deterministic collision rows.
15. A locked public slug survives a product name edit.
16. KIOT update does not overwrite an existing public slug.
17. AI/imported title uses the shared normalizer.
18. Category rename records the old category path.
19. Category rename records old child PDP paths directly.
20. Product rename records one direct old PDP alias.
21. Legacy `/products/{slug}` resolves to the current product.
22. Legacy `/categories/{slug}` resolves to the current category.
23. Unknown legacy path is 404, never homepage.
24. Page 1 query normalises away.
25. Page 2 is self-canonical.
26. Search/filter/variant query is noindex and absent from sitemap.
27. Private account/cart/checkout URLs are noindex/no-store.
28. Sitemap contains visible canonical categories/products only.
29. Contact-price product has no JSON-LD `Offer`.
30. SSR HTML contains title, canonical, H1, content and crawlable links.
