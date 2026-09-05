export type RelationType = 'related' | 'accessory' | 'frequently_bought' | 'alternative'

export interface ProductImage {
  id: number
  url: string | null
  alt: string | null
  width: number | null
  height: number | null
  sort_order: number
  is_primary: boolean
}

export interface ProductCard {
  id: number
  name: string
  slug: string
  sku: string
  short_description: string | null
  brand: { id: number; name: string; slug: string; logo: string | null } | null
  category: { id: number; name: string; slug: string } | null
  images: ProductImage[]
  pricing: { price: number; sale_price: number | null; display_price: number }
  inventory: { purchasable: boolean; availability_label: string }
  rating?: { average: number | null; count: number }
  sold_count?: number
  warranty_months: number | null
  is_featured?: boolean
}

export interface ProductVariant {
  id: number
  name: string
  sku: string | null
  attributes: Record<string, string> | null
  pricing: { price: number; sale_price: number | null; display_price: number }
  inventory: { quantity: number; is_available: boolean }
}

export interface ProductHighlight {
  id: number
  title: string
  icon: string | null
}

export interface ProductDetailBlock {
  id: number
  type: 'hero_banner' | 'feature_cards' | 'benchmark_cards' | 'use_case_cards' | 'notice' | 'image_text'
  title: string | null
  payload: Record<string, unknown>
}

export interface ProductSpecification {
  key: string | null
  label: string
  value: string
  unit: string | null
}

export interface ProductReviewSummary {
  average: number | null
  count: number
  breakdown: Record<string, number>
}

export interface ProductReview {
  id: number
  reviewer_name: string
  rating: number
  title: string | null
  body: string | null
  admin_reply: string | null
  verified_purchase: boolean
  created_at: string | null
  media: ProductReviewMedia[]
}

export interface ProductReviewMedia {
  id: number
  url: string
}

export interface ProductQuestionAnswer {
  id: number
  author_name: string
  body: string
  is_official: boolean
  created_at: string | null
}

export interface ProductQuestion {
  id: number
  asker_name: string
  body: string
  created_at: string | null
  answers: ProductQuestionAnswer[]
}

export interface ProductDetail {
  id: number
  name: string
  slug: string
  sku: string
  brand: { id: number; name: string; slug: string; logo: string | null } | null
  category: { id: number; name: string; slug: string } | null
  component_type: { id: number; name: string; slug: string } | null
  is_featured: boolean
  pricing: {
    price: number
    sale_price: number | null
    display_price: number
    discount_percent: number
    saving: number
  }
  inventory: { quantity: number; purchasable: boolean; availability_label: string }
  warranty_months: number | null
  sold_count: number
  rating: ProductReviewSummary
  questions_count: number
  images: ProductImage[]
  variants: ProductVariant[]
  highlights: ProductHighlight[]
  detail_blocks: ProductDetailBlock[]
  specifications: ProductSpecification[]
  short_description: string | null
  description: string | null
  seo: { title: string | null; description: string | null }
}

export interface ProductDetailResponse {
  product: ProductDetail
}

export interface PaginatedResponse<T> {
  [key: string]: T[] | { current_page: number; last_page: number; per_page: number; total: number }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface BuyNowItem {
  product_id: number
  product_slug: string
  variant_id: number | null
  quantity: number
}
