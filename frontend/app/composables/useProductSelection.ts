import type { ProductDetail, ProductVariant } from '~/types/product-detail'
import type { Ref } from 'vue'

export const useProductSelection = (product: Ref<ProductDetail | undefined>) => {
  const selectedVariantId = ref<number | null>(null)
  const selectedAttributes = ref<Record<string, string>>({})
  const quantity = ref(1)

  const optionGroups = computed(() => {
    const groups = new Map<string, Set<string>>()
    for (const variant of product.value?.variants || []) {
      for (const [key, value] of Object.entries(variant.attributes || {})) {
        if (!groups.has(key)) groups.set(key, new Set())
        groups.get(key)?.add(value)
      }
    }
    return Array.from(groups.entries()).map(([name, values]) => ({ name, values: Array.from(values) }))
  })

  const selectedVariant = computed<ProductVariant | null>(() => {
    const variants = product.value?.variants || []
    if (!variants.length) return null
    if (optionGroups.value.length) {
      const keys = optionGroups.value.map(group => group.name)
      return variants.find(variant => keys.every(key => variant.attributes?.[key] === selectedAttributes.value[key])) || null
    }
    return variants.find(variant => variant.id === selectedVariantId.value) || null
  })

  const pricing = computed(() => selectedVariant.value?.pricing || product.value?.pricing || null)
  const inventory = computed(() => selectedVariant.value?.inventory || product.value?.inventory || null)
  const purchasable = computed(() => {
    if ((product.value?.variants.length || 0) > 0) return Boolean(selectedVariant.value?.inventory.is_available)
    return Boolean(product.value?.inventory.purchasable)
  })
  const sku = computed(() => selectedVariant.value?.sku || product.value?.sku || '')

  const chooseVariant = (variant: ProductVariant) => {
    selectedVariantId.value = variant.id
    selectedAttributes.value = { ...(variant.attributes || {}) }
    quantity.value = 1
  }

  const chooseAttribute = (name: string, value: string) => {
    selectedAttributes.value = { ...selectedAttributes.value, [name]: value }
    quantity.value = 1
  }

  const isOptionAvailable = (name: string, value: string) => {
    const expected = { ...selectedAttributes.value, [name]: value }
    return (product.value?.variants || []).some(variant => variant.inventory.is_available
      && Object.entries(expected).every(([key, selected]) => variant.attributes?.[key] === selected))
  }

  watch(product, (value) => {
    const firstAvailable = value?.variants.find(variant => variant.inventory.is_available) || value?.variants[0]
    if (firstAvailable) chooseVariant(firstAvailable)
  }, { immediate: true })

  watch(inventory, () => {
    if (!inventory.value || quantity.value > inventory.value.quantity) {
      quantity.value = Math.max(1, inventory.value?.quantity || 1)
    }
  })

  return {
    selectedVariant,
    selectedVariantId,
    selectedAttributes,
    optionGroups,
    pricing,
    inventory,
    purchasable,
    sku,
    quantity,
    chooseVariant,
    chooseAttribute,
    isOptionAvailable,
  }
}
