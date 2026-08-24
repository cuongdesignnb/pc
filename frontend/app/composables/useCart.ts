interface CartItem {
  id: number
  product_id: number
  variant_id?: number | null
  quantity: number
  variant?: { id: number; name: string; sku?: string | null } | null
  product: {
    id: number
    name: string
    slug: string
    price: number
    sale_price: number | null
    quantity: number
    images?: { url: string }[]
  }
}

export const useCart = () => {
  const config = useRuntimeConfig()
  const { getHeaders } = useCartSession()
  
  const items = useState<CartItem[]>('cart-items', () => [])
  const total = useState<number>('cart-total', () => 0)
  const loading = useState<boolean>('cart-loading', () => false)
  
  const itemCount = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  
  const fetchCart = async () => {
    loading.value = true
    try {
      const response = await $fetch<{ items: CartItem[], total: number }>(
        `${config.public.apiBase}/cart`,
        { headers: getHeaders() }
      )
      items.value = response.items || []
      total.value = response.total || 0
    } catch (error) {
      console.error('Error fetching cart:', error)
    } finally {
      loading.value = false
    }
  }
  
  const addItem = async (productId: number, quantity: number = 1, variantId?: number | null) => {
    loading.value = true
    try {
      await $fetch(`${config.public.apiBase}/cart/items`, {
        method: 'POST',
        headers: getHeaders(),
        body: { product_id: productId, quantity, variant_id: variantId || null },
      })
      await fetchCart()
      return true
    } catch (error) {
      console.error('Error adding to cart:', error)
      return false
    } finally {
      loading.value = false
    }
  }
  
  const updateItem = async (itemId: number, quantity: number) => {
    loading.value = true
    try {
      await $fetch(`${config.public.apiBase}/cart/items/${itemId}`, {
        method: 'PATCH',
        headers: getHeaders(),
        body: { quantity },
      })
      await fetchCart()
      return true
    } catch (error) {
      console.error('Error updating cart:', error)
      return false
    } finally {
      loading.value = false
    }
  }
  
  const removeItem = async (itemId: number) => {
    loading.value = true
    try {
      await $fetch(`${config.public.apiBase}/cart/items/${itemId}`, {
        method: 'DELETE',
        headers: getHeaders(),
      })
      await fetchCart()
      return true
    } catch (error) {
      console.error('Error removing from cart:', error)
      return false
    } finally {
      loading.value = false
    }
  }
  
  const clearCart = async () => {
    loading.value = true
    try {
      await $fetch(`${config.public.apiBase}/cart`, {
        method: 'DELETE',
        headers: getHeaders(),
      })
      items.value = []
      total.value = 0
      return true
    } catch (error) {
      console.error('Error clearing cart:', error)
      return false
    } finally {
      loading.value = false
    }
  }
  
  return {
    items,
    total,
    loading,
    itemCount,
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    clearCart,
  }
}
