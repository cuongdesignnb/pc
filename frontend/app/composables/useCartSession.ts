/**
 * Manages a persistent cart session ID for guest users.
 * Stores a UUID in a cookie so it persists across page reloads.
 */
export const useCartSession = () => {
  const sessionId = useCookie<string>('cart_session', {
    maxAge: 60 * 60 * 24 * 30, // 30 days
    path: '/',
    sameSite: 'lax',
  })

  if (!sessionId.value) {
    sessionId.value = crypto.randomUUID()
  }

  const getHeaders = (): Record<string, string> => ({
    'X-Cart-Session': sessionId.value!,
  })

  return {
    sessionId,
    getHeaders,
  }
}
