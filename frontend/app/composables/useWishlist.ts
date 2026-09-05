const WISHLIST_STORAGE_KEY = 'pc_wishlist'
const MAX_WISHLIST_ITEMS = 100

export const useWishlist = () => {
  const ids = useState<number[]>('pc-wishlist-ids', () => [])
  const hydrated = useState<boolean>('pc-wishlist-hydrated', () => false)

  const hydrate = () => {
    if (hydrated.value || !import.meta.client) return

    try {
      const parsed: unknown = JSON.parse(localStorage.getItem(WISHLIST_STORAGE_KEY) || '[]')
      ids.value = Array.isArray(parsed)
        ? [...new Set(parsed
            .filter((id): id is number => typeof id === 'number' && Number.isInteger(id) && id > 0)
            )].slice(0, MAX_WISHLIST_ITEMS)
        : []
    } catch {
      ids.value = []
    }

    hydrated.value = true
  }

  const persist = () => {
    if (!import.meta.client) return
    try {
      localStorage.setItem(WISHLIST_STORAGE_KEY, JSON.stringify(ids.value.slice(0, MAX_WISHLIST_ITEMS)))
    } catch {
      // Keep this session's selection usable when browser storage is disabled.
    }
  }

  const has = (id: number) => computed(() => ids.value.includes(id))

  const toggle = (id: number) => {
    hydrate()
    if (ids.value.includes(id)) {
      ids.value = ids.value.filter(itemId => itemId !== id)
    } else if (ids.value.length < MAX_WISHLIST_ITEMS) {
      ids.value = [...ids.value, id]
    }
    persist()
  }

  const remove = (id: number) => {
    ids.value = ids.value.filter(itemId => itemId !== id)
    persist()
  }

  if (import.meta.client) {
    onMounted(hydrate)
  }

  return {
    ids,
    count: computed(() => ids.value.length),
    hydrate,
    has,
    toggle,
    remove,
  }
}
