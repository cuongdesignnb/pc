import type { ProductCard } from '~/types/product-detail'

const STORAGE_KEY = 'pc_recently_viewed'

export const useRecentlyViewedProducts = () => {
  const config = useRuntimeConfig()
  const products = ref<ProductCard[]>([])

  const record = (slug: string) => {
    if (!import.meta.client) return
    const current = readSlugs()
    localStorage.setItem(STORAGE_KEY, JSON.stringify([slug, ...current.filter(item => item !== slug)].slice(0, 12)))
  }

  const readSlugs = (): string[] => {
    if (!import.meta.client) return []
    try {
      const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]') as unknown
      return Array.isArray(value) ? value.filter((slug): slug is string => typeof slug === 'string') : []
    } catch {
      return []
    }
  }

  const load = async (excludeSlug: string) => {
    const slugs = readSlugs().filter(slug => slug !== excludeSlug).slice(0, 8)
    if (!slugs.length) {
      products.value = []
      return
    }
    const response = await $fetch<{ products: ProductCard[] }>(`${config.public.apiBase}/products/cards`, {
      method: 'POST',
      body: { slugs },
    })
    const cards = new Map(response.products.map(product => [product.slug, product]))
    products.value = slugs.map(slug => cards.get(slug)).filter((product): product is ProductCard => Boolean(product))
  }

  return { products, record, load }
}
