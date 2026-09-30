"use client"

import { useMemo, useState } from "react"
import Image from "next/image"
import { motion } from "framer-motion"
import { ShoppingBag, Zap } from "lucide-react"
import { toast } from "sonner"
import type { Product, ShopSettings } from "@/lib/types"
import { money, plural, splitPrice } from "@/lib/types"
import { useStore } from "@/lib/store"
import { ProductCard } from "./product-card"

const CHIPS: { id: string; label: string }[] = [
  { id: "all", label: "Все букеты" },
  { id: "p:0-3500", label: "До 3 500 ₽" },
  { id: "p:3500-7000", label: "3 500–7 000 ₽" },
  { id: "p:7000-", label: "От 7 000 ₽" },
  { id: "t:пионы", label: "Пионы" },
  { id: "t:французские розы", label: "Французские розы" },
  { id: "t:гортензии", label: "Гортензии" },
  { id: "t:монобукеты", label: "Монобукеты" },
  { id: "t:шляпные коробки", label: "Шляпные коробки" },
  { id: "t:стойкие цветы", label: "Стойкие цветы" },
  { id: "t:свидание", label: "Свидание" },
]

export function Catalog({ products, settings }: { products: Product[]; settings: ShopSettings }) {
  const [chip, setChip] = useState("all")
  const setQuickView = useStore((s) => s.setQuickView)

  const filtered = useMemo(() => {
    const available = products.filter((p) => p.visible && p.inStock)
    if (chip === "all") return available
    if (chip.startsWith("p:")) {
      const [, range] = chip.split(":")
      const [from, to] = range.split("-")
      return available.filter((p) => {
        const price = p.oldPrice && p.oldPrice < p.price ? p.oldPrice : p.price
        const min = Number(from) || 0
        const max = Number(to) || Infinity
        return price >= min && price < max
      })
    }
    if (chip.startsWith("t:")) {
      const tag = chip.slice(2)
      return available.filter((p) => p.tags.some((t) => t.toLowerCase() === tag))
    }
    return available
  }, [products, chip])

  return (
    <section id="catalog" className="scroll-mt-24 pb-16 md:pb-24" aria-label="Каталог букетов">
      <div className="mx-auto max-w-7xl px-4">
        <div className="flex items-end justify-between gap-4 pt-10 md:pt-16">
          <div>
            <p className="text-[12px] font-semibold uppercase tracking-[0.14em] text-pine">Каталог</p>
            <h2 className="mt-2 font-display text-3xl tracking-tight text-foreground md:text-4xl">
              Букеты в наличии <span className="italic text-muted-foreground">сегодня</span>
            </h2>
          </div>
          <p className="hidden text-sm text-muted-foreground sm:block">
            {filtered.length} {plural(filtered.length, ["букет", "букета", "букетов"])} · фото перед отправкой
          </p>
        </div>

        {/* Липкая лента фильтров */}
        <div className="sticky top-[64px] z-30 -mx-4 mt-6 bg-background/95 px-4 py-3 backdrop-blur-md md:top-[72px]">
          <div className="chips-rail flex gap-2 overflow-x-auto pb-0.5" role="tablist" aria-label="Фильтры каталога">
            {CHIPS.map((c) => {
              const active = chip === c.id
              return (
                <button
                  key={c.id}
                  role="tab"
                  aria-selected={active}
                  onClick={() => setChip(c.id)}
                  className={`h-10 shrink-0 whitespace-nowrap rounded-full px-4 font-grotesk text-[13.5px] font-semibold transition-all min-h-[44px] ${
                    active
                      ? "bg-pine text-primary-foreground shadow-md shadow-pine/20"
                      : "bg-white text-foreground border border-border hover:border-pine/40 hover:bg-accent"
                  }`}
                >
                  {c.label}
                </button>
              )
            })}
          </div>
        </div>

        {filtered.length === 0 ? (
          <div className="mt-12 rounded-3xl border border-dashed border-border bg-white p-12 text-center">
            <p className="font-display text-xl text-foreground">Здесь пока пусто</p>
            <p className="mt-2 text-sm text-muted-foreground">
              В этом фильтре сейчас нет букетов в наличии — загляните в «Все букеты».
            </p>
            <button
              onClick={() => setChip("all")}
              className="mt-5 h-11 rounded-full bg-pine px-6 font-grotesk text-sm font-bold text-primary-foreground"
            >
              Показать все букеты
            </button>
          </div>
        ) : (
          <div className="mt-6 grid grid-cols-2 gap-x-3 gap-y-8 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-4 xl:grid-cols-5">
            {filtered.map((p, i) => (
              <ProductCard key={p.id} product={p} index={i} onOpen={() => setQuickView(p.id)} />
            ))}
          </div>
        )}
      </div>
    </section>
  )
}

export { CHIPS }
