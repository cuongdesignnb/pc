<script setup lang="ts">
import type { ProductReviewSummary } from '~/types/product-detail'

const props = defineProps<{ slug: string; summary: ProductReviewSummary }>()
const { reviews, meta, rating, sort, loading, load } = useProductReviews(toRef(props, 'slug'))
onMounted(() => load())
const formattedDate = (value: string | null) => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : ''
</script>

<template>
  <section id="danh-gia" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Đánh giá sản phẩm</h2>
    <ProductReviewSummary class="mt-4" :summary="summary" />
    <div class="mt-5 flex flex-wrap gap-3"><select v-model="rating" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option :value="null">Tất cả số sao</option><option v-for="star in [5,4,3,2,1]" :key="star" :value="star">{{ star }} sao</option></select><select v-model="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="newest">Mới nhất</option><option value="oldest">Cũ nhất</option><option value="highest">Điểm cao nhất</option><option value="lowest">Điểm thấp nhất</option></select></div>
    <p v-if="loading" class="mt-5 text-sm text-slate-500">Đang tải đánh giá…</p>
    <div v-else-if="reviews.length" class="mt-5 divide-y divide-slate-200"><article v-for="review in reviews" :key="review.id" class="py-5 first:pt-0"><div class="flex flex-wrap items-center gap-x-3 gap-y-1"><strong class="text-sm text-slate-800">{{ review.reviewer_name }}</strong><span class="text-amber-500">{{ '★'.repeat(review.rating) }}<span class="text-slate-200">{{ '★'.repeat(5 - review.rating) }}</span></span><span v-if="review.verified_purchase" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Đã mua hàng</span><time class="text-xs text-slate-500">{{ formattedDate(review.created_at) }}</time></div><h3 v-if="review.title" class="mt-2 font-semibold text-slate-800">{{ review.title }}</h3><p v-if="review.body" class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ review.body }}</p><p v-if="review.admin_reply" class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-950"><strong>Phản hồi từ cửa hàng:</strong> {{ review.admin_reply }}</p></article></div>
    <p v-else class="mt-5 text-sm text-slate-500">Chưa có đánh giá phù hợp.</p>
    <div v-if="meta.last_page > 1" class="mt-5 flex justify-center gap-2"><button type="button" class="rounded border px-3 py-2 text-sm disabled:opacity-40" :disabled="meta.current_page <= 1 || loading" @click="load(meta.current_page - 1)">Trước</button><span class="px-3 py-2 text-sm text-slate-600">{{ meta.current_page }}/{{ meta.last_page }}</span><button type="button" class="rounded border px-3 py-2 text-sm disabled:opacity-40" :disabled="meta.current_page >= meta.last_page || loading" @click="load(meta.current_page + 1)">Sau</button></div>
  </section>
</template>
