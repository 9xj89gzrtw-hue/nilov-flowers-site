"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useStore } from "@/lib/store"
import type { Category, DeliveryZone, Product, ShopSettings, Upsell } from "@/lib/types"
import { Header } from "./header"
import { Hero } from "./hero"
import { Catalog } from "./catalog"
import { Sections } from "./sections"
import { Footer } from "./footer"
import { CartDrawer } from "./cart-drawer"
import { QuickView } from "./quick-view"
import { OneClickModal } from "./one-click-modal"
import { CheckoutModal } from "./checkout-modal"
import { StickyBottomBar } from "./sticky-bottom-bar"
import { AdminGate } from "@/components/admin/admin-app"

export interface ShopData {
  products: Product[]
  zones: DeliveryZone[]
  upsells: Upsell[]
  settings: Record<string, string>
  categories: Category[]
}

export interface Editorial {
  reviews: { n: string; d: string; src: string; r: number; t: string }[]
  rating: { value: number; count: number }
  faq: { q: string; a: string }[]
}

export function StoreApp({ initialData, editorial }: { initialData: ShopData; editorial: Editorial }) {
  const [data, setData] = useState<ShopData>(initialData)
  const [adminMode, setAdminMode] = useState(false)

  // Живой рефреш: админ сохранил настройки → витрина обновилась без перезагрузки
  const refresh = useCallback(async () => {
    try {
      const r = await fetch("/api/public-data", { cache: "no-store" })
      if (r.ok) setData(await r.json())
    } catch {}
  }, [])

  useEffect(() => {
    const handler = () => void refresh()
    window.addEventListener("nf:data-updated", handler)
    window.addEventListener("focus", handler)
    return () => {
      window.removeEventListener("nf:data-updated", handler)
      window.removeEventListener("focus", handler)
    }
  }, [refresh])

  // Hash-роутинг: #admin → панель флориста
  useEffect(() => {
    const sync = () => setAdminMode(window.location.hash === "#admin")
    sync()
    window.addEventListener("hashchange", sync)
    return () => window.removeEventListener("hashchange", sync)
  }, [])

  const settings = data.settings as ShopSettings

  const minPrice = useMemo(
    () => data.products.filter((p) => p.inStock).reduce((m, p) => Math.min(m, p.price), Infinity),
    [data.products],
  )
  const safeMinPrice = Number.isFinite(minPrice) ? minPrice : 0

  const upsellProducts = useMemo(
    () => data.upsells.map((u) => ({ slug: u.slug, name: u.name, price: u.price, photo: u.photo })),
    [data.upsells],
  )

  if (adminMode) {
    return (
      <AdminGate
        onExit={() => {
          window.location.hash = ""
          setAdminMode(false)
          void refresh()
        }}
      />
    )
  }

  return (
    <div className="min-h-screen flex flex-col bg-background">
      <Header settings={settings} zones={data.zones} products={data.products} />
      <main id="main" className="flex-1">
        <Hero settings={settings} minPrice={safeMinPrice} />
        <Catalog products={data.products} settings={settings} />
        <Sections settings={settings} zones={data.zones} editorial={editorial} />
      </main>
      <Footer settings={settings} />

      <CartDrawer upsells={data.upsells} settings={settings} />
      <QuickView products={data.products} upsellHints={upsellProducts} />
      <OneClickModal />
      <CheckoutModal zones={data.zones} upsellHints={upsellProducts} settings={settings} />
      <StickyBottomBar settings={settings} />
    </div>
  )
}
