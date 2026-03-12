export const useAuth = () => {
  const config = useRuntimeConfig()
  const router = useRouter()
  
  const token = useCookie('auth_token')
  const userCookie = useCookie('auth_user')
  
  const user = computed(() => {
    try {
      return userCookie.value ? JSON.parse(userCookie.value as string) : null
    } catch {
      return null
    }
  })
  
  const isAuthenticated = computed(() => !!token.value)
  
  const login = async (email: string, password: string, remember: boolean = false) => {
    const response = await $fetch<{ user: any; token: string }>(
      `${config.public.apiBase}/auth/login`,
      {
        method: 'POST',
        body: { email, password },
      }
    )
    
    const maxAge = remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24
    
    token.value = response.token
    userCookie.value = JSON.stringify(response.user)
    
    return response
  }
  
  const register = async (data: {
    name: string
    email: string
    phone?: string
    password: string
    password_confirmation: string
  }) => {
    const response = await $fetch<{ user: any; token: string }>(
      `${config.public.apiBase}/auth/register`,
      {
        method: 'POST',
        body: data,
      }
    )
    
    token.value = response.token
    userCookie.value = JSON.stringify(response.user)
    
    return response
  }
  
  const logout = async () => {
    try {
      await $fetch(`${config.public.apiBase}/auth/logout`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      })
    } catch {
      // Ignore errors
    }
    
    token.value = null
    userCookie.value = null
    
    router.push('/')
  }
  
  const fetchUser = async () => {
    if (!token.value) return null
    
    try {
      const response = await $fetch<{ user: any }>(
        `${config.public.apiBase}/user`,
        {
          headers: {
            Authorization: `Bearer ${token.value}`,
          },
        }
      )
      
      userCookie.value = JSON.stringify(response.user)
      return response.user
    } catch {
      token.value = null
      userCookie.value = null
      return null
    }
  }
  
  const authFetch = <T>(url: string, options: any = {}) => {
    return $fetch<T>(url, {
      ...options,
      headers: {
        ...options.headers,
        Authorization: `Bearer ${token.value}`,
      },
    })
  }
  
  return {
    user,
    token,
    isAuthenticated,
    login,
    register,
    logout,
    fetchUser,
    authFetch,
  }
}
