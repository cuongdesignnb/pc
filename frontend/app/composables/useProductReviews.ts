import type { ProductReview, ProductReviewSummary } from '~/types/product-detail'
import type { Ref } from 'vue'

interface ReviewsResponse {
  reviews: ProductReview[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const useProductReviews = (slug: Ref<string>) => {
  const config = useRuntimeConfig()
  const reviews = ref<ProductReview[]>([])
  const meta = ref<ReviewsResponse['meta']>({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
  const rating = ref<number | null>(null)
  const sort = ref<'newest' | 'oldest' | 'highest' | 'lowest'>('newest')
  const loading = ref(false)

  const load = async (page = 1) => {
    loading.value = true
    try {
      const response = await $fetch<ReviewsResponse>(`${config.public.apiBase}/products/${encodeURIComponent(slug.value)}/reviews`, {
        params: { page, per_page: 10, rating: rating.value || undefined, sort: sort.value },
      })
      reviews.value = response.reviews
      meta.value = response.meta
    } finally {
      loading.value = false
    }
  }

  watch([rating, sort], () => load())

  return { reviews, meta, rating, sort, loading, load }
}
