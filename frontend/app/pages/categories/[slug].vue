<script setup>
// Redirect old /categories/:slug to /:slug
const route = useRoute()
navigateTo(`/${route.params.slug}`, { redirectCode: 301 })
</script>