import type { MaybeRef } from 'vue'
import type { ProductDetailResponse } from '~/types/product-detail'

export const useProductDetail = (slug: MaybeRef<string>) => {
  const config = useRuntimeConfig()
  const value = toValue(slug)

  return useFetch<ProductDetailResponse>(() => `${config.public.apiBase}/products/${encodeURIComponent(toValue(slug))}`, {
    key: `product-detail:${value}`,
  })
}
