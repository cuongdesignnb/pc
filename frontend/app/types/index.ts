// Product types
export interface Product {
  id: number
  name: string
  slug: string
  sku: string
  price: number
  sale_price: number | null
  quantity: number
  description: string | null
  short_description: string | null
  meta_description?: string | null
  warranty_months: number | null
  is_active: boolean
  is_featured: boolean
  brand?: Brand
  category?: Category
  component_type?: ComponentType
  images?: ProductImage[]
  specifications?: ProductSpecification[]
  parsed_specifications?: { label: string; value: string }[]
  reviews?: Review[]
}

export interface ProductImage {
  id: number
  url: string
  alt: string | null
  sort_order: number
}

export interface ProductSpecification {
  id: number
  specification_key?: SpecificationKey
  value: string
}

export interface SpecificationKey {
  id: number
  key: string
  label: string
  unit: string | null
  data_type: string
}

// Category types
export interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  image: string | null
  icon: string | null
  parent_id: number | null
  children?: Category[]
}

// Brand types
export interface Brand {
  id: number
  name: string
  slug: string
  logo: string | null
}

// Component types
export interface ComponentType {
  id: number
  name: string
  slug: string
  description: string | null
  icon: string | null
  sort_order: number
  is_required: boolean
}

// Review types
export interface Review {
  id: number
  user?: User
  guest_name?: string | null
  guest_email?: string | null
  rating: number
  title?: string | null
  body?: string | null
  admin_reply?: string | null
  created_at: string
}

// User types
export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
}

// Cart types
export interface CartItem {
  id: number
  product_id: number
  quantity: number
  product: Product
}

// Order types
export interface Order {
  id: number
  order_number: string
  user_id: number | null
  customer_name: string
  customer_email: string
  customer_phone: string
  shipping_address: string
  shipping_city: string
  shipping_district: string | null
  shipping_ward: string | null
  notes: string | null
  subtotal: number
  shipping_fee: number
  discount_amount: number
  total_amount: number
  payment_method: string
  payment_status: string
  status: string
  created_at: string
  items?: OrderItem[]
}

export interface OrderItem {
  id: number
  product_id: number
  quantity: number
  price: number
  product?: Product
}

// Blog types
export interface Post {
  id: number
  title: string
  slug: string
  excerpt: string | null
  body: string | null
  featured_image: string | null
  meta_description?: string | null
  status: string
  published_at: string | null
  created_at?: string
  view_count: number
  is_featured: boolean
  category?: PostCategory
  author?: User
  tags?: Tag[]
}

export interface PostCategory {
  id: number
  name: string
  slug: string
}

export interface Tag {
  id: number
  name: string
  slug: string
}

// API Response types
export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ProductsResponse {
  products: Product[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}
