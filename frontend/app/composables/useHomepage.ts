import type { HomepageResponse } from '~/types/homepage'

const emptyHomepage = (): HomepageResponse => ({
  hero_banners: [],
  sidebar_banners: [],
  category_sidebar: [],
  featured_categories: [],
  flash_sale: { enabled: false, ends_at: null, products: [] },
  best_sellers: { laptop: [], pc_gaming: [], components: [] },
  pc_builder_banner: null,
  combo_banners: [],
  setup_banners: [],
  featured_accessories: [],
  posts: [],
  testimonials: [],
})

export const useHomepage = () => {
  const config = useRuntimeConfig()

  return useFetch<HomepageResponse>(`${config.public.apiBase}/homepage`, {
    default: emptyHomepage,
    key: 'homepage-reference-payload',
  })
}
