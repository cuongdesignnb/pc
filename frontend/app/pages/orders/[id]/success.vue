<script setup>
const route = useRoute()
navigateTo('/don-hang/' + route.params.id + '/thanh-cong', { redirectCode: 301 })
</script>