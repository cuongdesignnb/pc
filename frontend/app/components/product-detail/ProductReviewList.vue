<script setup lang="ts">
import type { ProductReview, ProductReviewSummary } from '~/types/product-detail'

const props = defineProps<{ slug: string; summary: ProductReviewSummary }>()
const { reviews, meta, rating, sort, loading, load } = useProductReviews(toRef(props, 'slug'))
const featuredReviews = computed(() => reviews.value.slice(0, 3))
const remainingReviews = computed(() => reviews.value.slice(3))

onMounted(() => load())

const formattedDate = (value: string | null) => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : ''
const stars = (review: ProductReview) => '★'.repeat(review.rating)
const emptyStars = (review: ProductReview) => '★'.repeat(Math.max(0, 5 - review.rating))
</script>

<template>
  <section id="danh-gia" class="pdp-panel scroll-mt-28">
    <div class="flex items-center justify-between gap-3">
      <h2 class="pdp-section-title">Đánh giá sản phẩm</h2>
      <span class="text-[11px] text-slate-500">{{ summary.count }} đánh giá</span>
    </div>

    <div class="mt-3 grid gap-4 lg:grid-cols-[28%_minmax(0,1fr)]">
      <ProductReviewSummary :summary="summary" />
      <div v-if="featuredReviews.length" class="grid gap-2 md:grid-cols-3">
        <article v-for="review in featuredReviews" :key="review.id" class="pdp-review-card">
          <div class="flex items-center justify-between gap-2">
            <strong class="truncate text-[11px] text-slate-800">{{ review.reviewer_name }}</strong>
            <time class="shrink-0 text-[9px] text-slate-400">{{ formattedDate(review.created_at) }}</time>
          </div>
          <div class="mt-1 text-[11px] text-amber-500"><span>{{ stars(review) }}</span><span class="text-slate-200">{{ emptyStars(review) }}</span></div>
          <span v-if="review.verified_purchase" class="mt-1 inline-flex rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-medium text-emerald-700">Đã mua hàng</span>
          <h3 v-if="review.title" class="mt-2 line-clamp-1 text-xs font-semibold text-slate-800">{{ review.title }}</h3>
          <p v-if="review.body" class="mt-1 line-clamp-4 whitespace-pre-line text-[11px] leading-4 text-slate-600">{{ review.body }}</p>
          <div v-if="review.media?.length" class="mt-2 flex gap-1.5 overflow-hidden">
            <NuxtImg v-for="media in review.media.slice(0, 3)" :key="media.id" :src="media.url" alt="Ảnh đánh giá" width="52" height="40" class="h-10 w-[52px] rounded border border-slate-100 object-cover" />
          </div>
        </article>
      </div>
      <p v-else class="text-xs text-slate-500">Chưa có đánh giá phù hợp.</p>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
      <p class="text-[11px] text-slate-500">Xem thêm đánh giá từ khách hàng</p>
      <div class="flex gap-2">
        <label class="sr-only" for="review-rating-filter">Lọc theo số sao</label>
        <select id="review-rating-filter" v-model="rating" class="h-8 rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] text-slate-600"><option :value="null">Tất cả số sao</option><option v-for="star in [5, 4, 3, 2, 1]" :key="star" :value="star">{{ star }} sao</option></select>
        <label class="sr-only" for="review-sort">Sắp xếp đánh giá</label>
        <select id="review-sort" v-model="sort" class="h-8 rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] text-slate-600"><option value="newest">Mới nhất</option><option value="oldest">Cũ nhất</option><option value="highest">Điểm cao nhất</option><option value="lowest">Điểm thấp nhất</option></select>
      </div>
    </div>

    <p v-if="loading" class="mt-3 text-xs text-slate-500">Đang tải đánh giá…</p>
    <div v-else-if="remainingReviews.length" class="mt-2 divide-y divide-slate-100">
      <article v-for="review in remainingReviews" :key="review.id" class="py-3 first:pt-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1"><strong class="text-xs text-slate-800">{{ review.reviewer_name }}</strong><span class="text-[11px] text-amber-500">{{ stars(review) }}<span class="text-slate-200">{{ emptyStars(review) }}</span></span><span v-if="review.verified_purchase" class="rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] text-emerald-700">Đã mua hàng</span><time class="text-[9px] text-slate-400">{{ formattedDate(review.created_at) }}</time></div>
        <h3 v-if="review.title" class="mt-1 text-xs font-semibold text-slate-800">{{ review.title }}</h3>
        <p v-if="review.body" class="mt-1 whitespace-pre-line text-[11px] leading-4 text-slate-600">{{ review.body }}</p>
        <p v-if="review.admin_reply" class="mt-2 rounded-[6px] bg-blue-50 px-2.5 py-2 text-[11px] leading-4 text-blue-900"><strong>Phản hồi từ cửa hàng:</strong> {{ review.admin_reply }}</p>
      </article>
    </div>
    <p v-else-if="!loading && !featuredReviews.length" class="mt-3 text-xs text-slate-500">Chưa có đánh giá phù hợp.</p>

    <div v-if="meta.last_page > 1" class="mt-3 flex justify-center gap-2 border-t border-slate-100 pt-3"><button type="button" class="h-8 rounded-[6px] border border-slate-200 px-3 text-[11px] disabled:opacity-40" :disabled="meta.current_page <= 1 || loading" @click="load(meta.current_page - 1)">Trước</button><span class="px-2 py-2 text-[11px] text-slate-500">{{ meta.current_page }}/{{ meta.last_page }}</span><button type="button" class="h-8 rounded-[6px] border border-slate-200 px-3 text-[11px] disabled:opacity-40" :disabled="meta.current_page >= meta.last_page || loading" @click="load(meta.current_page + 1)">Sau</button></div>
  </section>
</template>
