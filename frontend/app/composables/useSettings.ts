type SettingValue = string | number | boolean | null

export const useSettings = () => {
  const config = useRuntimeConfig()
  const settings = useState<Record<string, SettingValue>>('site_settings', () => ({}))
  const loaded = useState<boolean>('site_settings_loaded', () => false)

  const fetchSettings = async (force = false) => {
    if (loaded.value && !force) return

    try {
      settings.value = await $fetch<Record<string, SettingValue>>(
        `${config.public.apiBase}/settings`,
      )
      loaded.value = true
    } catch (error) {
      console.error('[useSettings] Failed to load settings:', error)
    }
  }

  const get = (key: string): SettingValue => settings.value[key] ?? null

  const getString = (key: string, fallback = ''): string => {
    const value = get(key)
    return typeof value === 'string' && value.trim() !== '' ? value : fallback
  }

  const getNumber = (key: string, fallback = 0): number => {
    const value = get(key)
    if (value === null || value === '') return fallback
    const parsed = typeof value === 'number' ? value : Number(value)
    return Number.isFinite(parsed) ? parsed : fallback
  }

  const getBoolean = (key: string, fallback = false): boolean => {
    const value = get(key)
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value !== 0
    if (typeof value === 'string') {
      if (['1', 'true', 'yes', 'on'].includes(value.toLowerCase())) return true
      if (['0', 'false', 'no', 'off'].includes(value.toLowerCase())) return false
    }
    return fallback
  }

  const siteName = computed(() => getString('site_name', 'PC Shop'))
  const siteTagline = computed(() => getString('site_tagline'))
  const siteDescription = computed(() => getString('site_description'))
  const siteLogo = computed(() => getString('site_logo'))
  const siteLogoWhite = computed(() => getString('site_logo_white'))
  const siteFavicon = computed(() => getString('site_favicon'))
  const currency = computed(() => getString('currency', 'VND'))

  const sitePhone = computed(() => getString('contact_phone'))
  const siteHotline = computed(() => getString('contact_hotline', sitePhone.value))
  const siteEmail = computed(() => getString('contact_email'))
  const siteAddress = computed(() => getString('contact_address'))
  const businessHours = computed(() => getString('business_hours'))
  const contactMap = computed(() => getString('contact_map'))
  const warehouseAddresses = computed(() => getString('storefront_warehouse_addresses'))
  const warrantyInformation = computed(() => getString('storefront_warranty_information'))

  const socialFacebook = computed(() => getString('social_facebook'))
  const socialYoutube = computed(() => getString('social_youtube'))
  const socialTiktok = computed(() => getString('social_tiktok'))
  const socialZalo = computed(() => getString('social_zalo'))
  const socialInstagram = computed(() => getString('social_instagram'))

  const seoTitle = computed(() => getString('seo_title', siteName.value))
  const seoDescription = computed(() => getString('seo_description', siteDescription.value))
  const seoKeywords = computed(() => getString('seo_keywords'))
  const seoOgImage = computed(() => getString('seo_og_image', siteLogo.value))

  const heroAutoplay = computed(() => getBoolean('homepage_hero_autoplay', true))
  const heroInterval = computed(() => Math.max(1000, getNumber('homepage_hero_interval', 5000)))
  const productsPerSection = computed(() => Math.max(1, getNumber('homepage_products_per_section', 8)))
  const showBrands = computed(() => getBoolean('homepage_show_brands', true))
  const showPosts = computed(() => getBoolean('homepage_show_posts', true))

  const paymentCodEnabled = computed(() => getBoolean('payment_cod_enabled', true))
  const shippingFreeThreshold = computed(() => Math.max(0, getNumber('shipping_free_threshold', 500000)))
  const shippingDefaultFee = computed(() => Math.max(0, getNumber('shipping_default_fee', 30000)))
  const shippingExpressFee = computed(() => Math.max(0, getNumber('shipping_express_fee', 50000)))

  const formatMoney = (amount: number): string => new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currency.value,
    maximumFractionDigits: 0,
  }).format(amount)

  return {
    settings,
    loaded,
    fetchSettings,
    get,
    getString,
    getNumber,
    getBoolean,
    siteName,
    siteTagline,
    siteDescription,
    siteLogo,
    siteLogoWhite,
    siteFavicon,
    currency,
    sitePhone,
    siteHotline,
    siteEmail,
    siteAddress,
    businessHours,
    contactMap,
    warehouseAddresses,
    warrantyInformation,
    socialFacebook,
    socialYoutube,
    socialTiktok,
    socialZalo,
    socialInstagram,
    seoTitle,
    seoDescription,
    seoKeywords,
    seoOgImage,
    heroAutoplay,
    heroInterval,
    productsPerSection,
    showBrands,
    showPosts,
    paymentCodEnabled,
    shippingFreeThreshold,
    shippingDefaultFee,
    shippingExpressFee,
    formatMoney,
  }
}
