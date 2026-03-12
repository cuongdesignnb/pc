export default defineNuxtRouteMiddleware((to, from) => {
  const token = useCookie('auth_token')
  
  if (!token.value) {
    return navigateTo({
      path: '/auth/login',
      query: { redirect: to.fullPath },
    })
  }
})
