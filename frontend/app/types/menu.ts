export interface MenuItemData {
  id: number
  title: string
  url: string | null
  type: string
  icon: string | null
  badge_text: string | null
  badge_color: string | null
  css_class: string | null
  target: string
  is_mega: boolean
  mega_columns: number
  description: string | null
  image: string | null
  children: MenuItemData[]
  category?: { slug: string } | null
}
