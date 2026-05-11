<script setup lang="ts">
import type { Post } from '~/types'

interface PostDetailResponse {
  post: Post
  related: Post[]
}

const config = useRuntimeConfig()
const route = useRoute()

const slug = route.params.slug as string

// Fetch post
const { data } = await useFetch<PostDetailResponse>(`${config.public.apiBase}/blog/${slug}`)

const post = computed(() => data.value?.post)
const relatedPosts = computed(() => data.value?.related || [])

// Format date
const formatDate = (date: string | null) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('vi-VN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

// SEO
useSeoMeta({
  title: () => post.value?.title ? `${post.value.title} - PC Shop` : 'Bài viết - PC Shop',
  description: () => post.value?.excerpt || post.value?.meta_description,
})
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <template v-if="post">
      <!-- Breadcrumb -->
      <nav class="mb-6 text-sm">
        <NuxtLink to="/" class="text-gray-500 hover:text-primary-600">Trang chủ</NuxtLink>
        <span class="mx-2 text-gray-400">/</span>
        <NuxtLink to="/tin-tuc" class="text-gray-500 hover:text-primary-600">Tin tức</NuxtLink>
        <span v-if="post.category" class="mx-2 text-gray-400">/</span>
        <span v-if="post.category" class="text-gray-500">{{ post.category.name }}</span>
      </nav>

      <div class="grid lg:grid-cols-4 gap-8">
        <!-- Main Content -->
        <article class="lg:col-span-3">
          <!-- Header -->
          <header class="mb-8">
            <div class="flex items-center gap-3 text-sm text-gray-500 mb-4">
              <span v-if="post.category" class="px-2 py-1 bg-primary-100 text-primary-700 rounded">
                {{ post.category.name }}
              </span>
              <span>{{ formatDate(post.published_at) }}</span>
              <span>•</span>
              <span>{{ post.view_count }} lượt xem</span>
            </div>
            <h1 class="text-4xl font-bold mb-4">{{ post.title }}</h1>
            <p v-if="post.excerpt" class="text-xl text-gray-600">{{ post.excerpt }}</p>
          </header>

          <!-- Featured Image -->
          <div v-if="post.featured_image" class="mb-8 rounded-xl overflow-hidden">
            <img 
              :src="post.featured_image" 
              :alt="post.title"
              class="w-full"
            >
          </div>

          <!-- Content -->
          <div class="prose prose-lg max-w-none" v-html="post.body"></div>

          <!-- Tags -->
          <div v-if="post.tags?.length" class="mt-8 pt-8 border-t">
            <h3 class="font-semibold mb-3">Tags:</h3>
            <div class="flex flex-wrap gap-2">
              <span 
                v-for="tag in post.tags" 
                :key="tag.id"
                class="px-3 py-1 bg-gray-100 rounded-full text-sm"
              >
                {{ tag.name }}
              </span>
            </div>
          </div>

          <!-- Author -->
          <div v-if="post.author" class="mt-8 p-6 bg-gray-50 rounded-xl flex items-center gap-4">
            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center text-2xl font-bold">
              {{ post.author.name?.[0] }}
            </div>
            <div>
              <p class="font-semibold">{{ post.author.name }}</p>
              <p class="text-gray-500 text-sm">Tác giả</p>
            </div>
          </div>

          <!-- Share -->
          <div class="mt-8 flex items-center gap-4">
            <span class="text-gray-500">Chia sẻ:</span>
            <a 
              :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent($route.fullPath)}`"
              target="_blank"
              class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center hover:bg-blue-700"
            >
              f
            </a>
            <a 
              :href="`https://twitter.com/intent/tweet?url=${encodeURIComponent($route.fullPath)}&text=${encodeURIComponent(post.title)}`"
              target="_blank"
              class="w-10 h-10 bg-sky-500 text-white rounded-full flex items-center justify-center hover:bg-sky-600"
            >
              𝕏
            </a>
          </div>
        </article>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 space-y-6">
          <!-- Related Posts -->
          <div v-if="relatedPosts.length" class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold mb-4">Bài viết liên quan</h3>
            <div class="space-y-4">
              <NuxtLink 
                v-for="related in relatedPosts" 
                :key="related.id"
                :to="`/tin-tuc/${related.slug}`"
                class="flex gap-3 hover:opacity-80"
              >
                <div class="w-20 h-16 bg-gray-100 rounded flex-shrink-0">
                  <img 
                    v-if="related.featured_image" 
                    :src="related.featured_image"
                    :alt="related.title"
                    class="w-full h-full object-cover rounded"
                  >
                </div>
                <div>
                  <h4 class="text-sm font-medium line-clamp-2">{{ related.title }}</h4>
                  <p class="text-xs text-gray-500 mt-1">{{ formatDate(related.published_at) }}</p>
                </div>
              </NuxtLink>
            </div>
          </div>

          <!-- Back to Blog -->
          <NuxtLink 
            to="/tin-tuc" 
            class="block text-center px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            ← Quay lại Tin tức
          </NuxtLink>
        </aside>
      </div>
    </template>

    <template v-else>
      <div class="text-center py-12">
        <p class="text-gray-500">Đang tải...</p>
      </div>
    </template>
  </div>
</template>
