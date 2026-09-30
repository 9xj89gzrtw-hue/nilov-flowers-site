"use client"

// Глобальный стор витрины: корзина, апселлы, промокод, открытка, UI-состояние
import { create } from "zustand"
import { persist } from "zustand/middleware"

export interface CartLine {
  key: string // productId или upsell:slug
  kind: "product" | "upsell"
  productId?: number
  upsellSlug?: string
  title: string
  price: number
  photo: string | null
  qty: number
}

interface StoreState {
  cart: CartLine[]
  promo: { code: string; type: string; value: number; label: string } | null
  cardText: string
  cartOpen: boolean
  oneClickProduct: { id: number; name: string; price: number; photo: string | null } | null
  quickViewId: number | null
  checkoutOpen: boolean
  lastOrderNumber: string | null

  addProduct: (p: { id: number; name: string; price: number; photo: string | null }) => void
  addUpsell: (u: { slug: string; name: string; price: number; photo: string | null }) => void
  removeLine: (key: string) => void
  setQty: (key: string, qty: number) => void
  clearCart: () => void
  setPromo: (p: StoreState["promo"]) => void
  setCardText: (t: string) => void
  setCartOpen: (v: boolean) => void
  setOneClick: (p: StoreState["oneClickProduct"]) => void
  setQuickView: (id: number | null) => void
  setCheckoutOpen: (v: boolean) => void
  setLastOrder: (n: string | null) => void
}

export const useStore = create<StoreState>()(
  persist(
    (set) => ({
      cart: [],
      promo: null,
      cardText: "",
      cartOpen: false,
      oneClickProduct: null,
      quickViewId: null,
      checkoutOpen: false,
      lastOrderNumber: null,

      addProduct: (p) =>
        set((s) => {
          const key = String(p.id)
          const existing = s.cart.find((l) => l.key === key)
          if (existing) {
            return {
              cart: s.cart.map((l) => (l.key === key ? { ...l, qty: l.qty + 1 } : l)),
              cartOpen: true,
            }
          }
          return {
            cart: [
              ...s.cart,
              { key, kind: "product", productId: p.id, title: p.name, price: p.price, photo: p.photo, qty: 1 },
            ],
            cartOpen: true,
          }
        }),
      addUpsell: (u) =>
        set((s) => {
          const key = "upsell:" + u.slug
          const existing = s.cart.find((l) => l.key === key)
          if (existing) {
            return { cart: s.cart.map((l) => (l.key === key ? { ...l, qty: l.qty + 1 } : l)) }
          }
          return {
            cart: [
              ...s.cart,
              { key, kind: "upsell", upsellSlug: u.slug, title: u.name, price: u.price, photo: u.photo, qty: 1 },
            ],
          }
        }),
      removeLine: (key) => set((s) => ({ cart: s.cart.filter((l) => l.key !== key) })),
      setQty: (key, qty) =>
        set((s) => ({
          cart: qty <= 0 ? s.cart.filter((l) => l.key !== key) : s.cart.map((l) => (l.key === key ? { ...l, qty } : l)),
        })),
      clearCart: () => set({ cart: [], promo: null, cardText: "", lastOrderNumber: null }),
      setPromo: (p) => set({ promo: p }),
      setCardText: (t) => set({ cardText: t }),
      setCartOpen: (v) => set({ cartOpen: v }),
      setOneClick: (p) => set({ oneClickProduct: p }),
      setQuickView: (id) => set({ quickViewId: id }),
      setCheckoutOpen: (v) => set({ checkoutOpen: v }),
      setLastOrder: (n) => set({ lastOrderNumber: n }),
    }),
    {
      name: "nilov-cart-v1",
      partialize: (s) => ({ cart: s.cart, promo: s.promo, cardText: s.cardText }),
    },
  ),
)

export const cartCount = (cart: CartLine[]) => cart.reduce((n, l) => n + l.qty, 0)
export const cartSum = (cart: CartLine[]) => cart.reduce((n, l) => n + l.price * l.qty, 0)

export function promoDiscount(sum: number, promo: StoreState["promo"]): number {
  if (!promo || sum <= 0) return 0
  if (promo.type === "percent") return Math.round((sum * promo.value) / 100)
  return Math.min(promo.value, sum)
}
