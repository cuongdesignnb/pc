import type { BuyNowItem } from '~/types/product-detail'

const STORAGE_KEY = 'pc-buy-now-item'

export const useBuyNow = () => {
  const start = async (item: BuyNowItem) => {
    if (import.meta.client) sessionStorage.setItem(STORAGE_KEY, JSON.stringify(item))
    await navigateTo('/thanh-toan?mode=buy-now')
  }

  const read = (): BuyNowItem | null => {
    if (!import.meta.client) return null
    try {
      const value = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null') as BuyNowItem | null
      return value && Number.isInteger(value.product_id) && value.quantity > 0 ? value : null
    } catch {
      return null
    }
  }

  const clear = () => {
    if (import.meta.client) sessionStorage.removeItem(STORAGE_KEY)
  }

  return { start, read, clear }
}
