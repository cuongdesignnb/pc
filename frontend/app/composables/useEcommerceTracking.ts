import type { ProductDetail } from '~/types/product-detail'

declare global {
  interface Window {
    gtag?: (...args: unknown[]) => void
    fbq?: (...args: unknown[]) => void
  }
}

export const useEcommerceTracking = () => {
  const item = (product: ProductDetail, quantity = 1) => ({
    item_id: String(product.id),
    item_name: product.name,
    item_brand: product.brand?.name,
    item_category: product.category?.name,
    price: product.pricing.display_price,
    quantity,
  })

  const track = (event: 'view_item' | 'add_to_cart' | 'begin_checkout', product: ProductDetail, quantity = 1) => {
    if (!import.meta.client) return
    const payload = { currency: 'VND', value: product.pricing.display_price * quantity, items: [item(product, quantity)] }
    window.gtag?.('event', event, payload)
    const facebookEvent = {
      view_item: 'ViewContent',
      add_to_cart: 'AddToCart',
      begin_checkout: 'InitiateCheckout',
    }[event]
    window.fbq?.('track', facebookEvent, payload)
  }

  return { track }
}
