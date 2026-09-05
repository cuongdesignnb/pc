import type { ProductCard } from '~/types/product-detail'

export interface HomepageBannerMetadata {
  cta_label?: string
  cta_link?: string
  cta2_label?: string
  cta2_link?: string
  [key: string]: string | undefined
}

export interface HomepageBanner {
  id: number
  title: string
  description: string | null
  badge: string | null
  image: string | null
  link: string | null
  position: string
  sort_order: number
  metadata: HomepageBannerMetadata | null
}

export interface HomepageCategoryCard {
  id: number
  name: string
  slug: string
  image: string | null
  icon: string | null
}

export interface HomepagePost {
  id: number
  title: string
  slug: string
  excerpt: string | null
  featured_image: string | null
  published_at: string | null
  view_count: number
}

export interface HomepageTestimonial {
  id: number
  name: string
  avatar: string | null
  city: string | null
  rating: number
  body: string
  verified_purchase: boolean
}

export interface HomepageResponse {
  hero_banners: HomepageBanner[]
  sidebar_banners: HomepageBanner[]
  category_sidebar: HomepageCategoryCard[]
  featured_categories: HomepageCategoryCard[]
  flash_sale: {
    enabled: boolean
    ends_at: string | null
    products: ProductCard[]
  }
  best_sellers: {
    laptop: ProductCard[]
    pc_gaming: ProductCard[]
    components: ProductCard[]
  }
  pc_builder_banner: HomepageBanner | null
  combo_banners: HomepageBanner[]
  setup_banners: HomepageBanner[]
  featured_accessories: ProductCard[]
  posts: HomepagePost[]
  testimonials: HomepageTestimonial[]
}
