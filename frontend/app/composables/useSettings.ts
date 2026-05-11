/**
 * Composable to fetch and cache public site settings from the API.
 * Settings are fetched once per app lifecycle and shared across all components.
 */
export const useSettings = () => {
  const config = useRuntimeConfig()

  // Shared state across all component instances
  const settings = useState<Record<string, string | null>>('site_settings', () => ({}))
  const loaded = useState<boolean>('site_settings_loaded', () => false)

  const fetchSettings = async () => {
    if (loaded.value) return

    try {
      const data = await $fetch<Record<string, string | null>>(
        `${config.public.apiBase}/settings`
      )
      settings.value = data
      loaded.value = true
    } catch (e) {
      console.error('[useSettings] Failed to load settings:', e)
    }
  }

  // Helper getters
  const get = (key: string, fallback: string = ''): string => {
    return settings.value[key] || fallback
  }

  const siteLogo = computed(() => get('site_logo'))
  const siteLogoWhite = computed(() => get('site_logo_white'))
  const siteFavicon = computed(() => get('site_favicon'))
  const siteName = computed(() => get('site_name', 'PC Shop'))
  const sitePhone = computed(() => get('contact_phone', '1900 1234'))
  const siteEmail = computed(() => get('contact_email', 'support@pcshop.vn'))
  const siteAddress = computed(() => get('contact_address', ''))

  return {
    settings,
    loaded,
    fetchSettings,
    get,
    siteLogo,
    siteLogoWhite,
    siteFavicon,
    siteName,
    sitePhone,
    siteEmail,
    siteAddress,
  }
}
