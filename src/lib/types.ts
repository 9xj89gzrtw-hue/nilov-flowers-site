// Типы витрины и админки Nilov Flowers S2

export interface Category {
  id: number
  slug: string
  name: string
  sort: number
}

export interface Product {
  id: number
  slug: string
  name: string
  sub: string | null
  line: string // premium | classic
  price: number
  oldPrice: number | null
  composition: string[] // распарсенный JSON
  description: string | null
  size: string | null
  photos: string[]
  badge: string | null
  categoryId: number | null
  tags: string[]
  inStock: boolean
  visible: boolean
  popular: boolean
  sort: number
}

export interface DeliveryZone {
  id: number
  name: string
  price: number
  eta: string | null
  note: string | null
  sort: number
}

export interface Upsell {
  id: number
  slug: string
  name: string
  description: string | null
  price: number
  photo: string | null
  active: boolean
  sort: number
}

export interface ShopSettings {
  shop_name: string
  shop_city: string
  shop_phone: string
  shop_phone_note: string
  shop_email: string
  shop_address: string
  working_hours: string
  whatsapp: string
  telegram: string
  instagram: string
  top_banner: string
  rating_badge: string
  split_text: string
  free_delivery_from: number
  split_months: string
  hero_title: string
  hero_lead: string
  founder_name: string
  [key: string]: string | number
}

export type OrderStatus = "new" | "photo" | "assembly" | "courier" | "done" | "canceled"

export const ORDER_STATUSES: { id: OrderStatus; label: string; hint: string }[] = [
  { id: "new", label: "Новый заказ", hint: "Только что поступил" },
  { id: "photo", label: "Согласование фото", hint: "Фото букета отправлено клиенту" },
  { id: "assembly", label: "Флорист собирает", hint: "Букет в работе" },
  { id: "courier", label: "У курьера", hint: "Едет к получателю" },
  { id: "done", label: "Доставлен", hint: "Курьер передал букет" },
  { id: "canceled", label: "Отменен", hint: "Отказ или перенос" },
]

export interface OrderItem {
  id: number
  productId: number | null
  title: string
  price: number
  qty: number
  photo: string | null
}

export interface Order {
  id: number
  number: string
  status: OrderStatus
  customerName: string
  customerPhone: string
  customerEmail: string | null
  comment: string | null
  recipientName: string | null
  recipientPhone: string | null
  surprise: boolean
  pickup: boolean
  knowAddress: boolean
  zoneId: number | null
  zone: DeliveryZone | null
  address: string | null
  deliveryDate: string | null
  deliverySlot: string | null
  cardText: string | null
  paymentMethod: string
  deliveryPrice: number
  itemsTotal: number
  total: number
  createdAt: string
  updatedAt: string
  items: OrderItem[]
}

// Сырые строки из БД (JSON-поля) → типизированные объекты.
// Идемпотентно: если данные уже распарсены (массив) — не ломаем (фикс двойного парса Раунда 2).
export function parseProductRow(row: any): Product {
  return {
    ...row,
    composition: safeJson(row.composition, []),
    photos: safeJson(row.photos, []),
    tags: safeJson(row.tags, []),
  }
}

export function parseOrderRow(row: any): Order {
  return {
    ...row,
    status: row.status as OrderStatus,
    items: (row.items || []).map((i: any) => ({ ...i })),
  }
}

export function safeJson<T>(raw: string | T[] | null | undefined, fallback: T): T {
  if (raw === null || raw === undefined) return fallback
  if (Array.isArray(raw)) return raw as T
  if (typeof raw !== "string") return fallback
  try {
    return JSON.parse(raw)
  } catch {
    return fallback
  }
}

export const money = (n: number) =>
  new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(n) + " ₽"

// Русские склонения: plural(1, ['букет','букета','букетов']) → «букет»
export function plural(n: number, forms: [string, string, string]): string {
  const abs = Math.abs(n) % 100
  const d = abs % 10
  if (abs > 10 && abs < 20) return forms[2]
  if (d > 1 && d < 5) return forms[1]
  if (d === 1) return forms[0]
  return forms[2]
}

// +7 (921) 123-45-67 из сырых цифр
export function formatPhone(raw: string): string {
  let d = raw.replace(/\D/g, "")
  if (d.length === 11 && d.startsWith("8")) d = "7" + d.slice(1)
  if (d.length === 11 && d.startsWith("7")) {
    return `+7 (${d.slice(1, 4)}) ${d.slice(4, 7)}-${d.slice(7, 9)}-${d.slice(9)}`
  }
  return raw
}

export const moneyShort = (n: number) =>
  new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(n)

export const splitPrice = (price: number) => Math.ceil(price / 4 / 10) * 10

export const waLink = (phone: string, text?: string) => {
  const digits = phone.replace(/\D/g, "")
  const normalized = digits.startsWith("8") ? "7" + digits.slice(1) : digits
  return `https://wa.me/${normalized}${text ? `?text=${encodeURIComponent(text)}` : ""}`
}

export const deliverySlots = [
  "10:00–12:00",
  "12:00–14:00",
  "14:00–16:00",
  "16:00–18:00",
  "18:00–20:00",
  "20:00–22:00",
]
