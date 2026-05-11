<script setup>
// Redirect old /products/:slug to new URL format
// We need to fetch the product to know its category
const config = useRuntimeConfig()
const route = useRoute()
const slug = route.params.slug

try {
  const data = await $fetch(`${config.public.apiBase}/products/${slug}`)
  const catSlug = data.product?.category?.slug || 'san-pham'
  navigateTo(`/${catSlug}/${slug}`, { redirectCode: 301 })
} catch {
  throw createError({ statusCode: 404, message: 'Sản phẩm không tồn tại' })
}
</script>
