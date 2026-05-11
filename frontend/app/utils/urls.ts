/**
 * Build URL for a product: /{category-slug}/{product-slug}
 */
export function productUrl(product: { slug: string; category?: { slug: string } | null }): string {
  const catSlug = product.category?.slug || 'san-pham'
  return `/${catSlug}/${product.slug}`
}

/**
 * Build URL for a category: /{category-slug}
 */
export function categoryUrl(category: { slug: string }): string {
  return `/${category.slug}`
}
