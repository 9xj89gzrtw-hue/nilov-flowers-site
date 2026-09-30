"use client"

import { useEffect, useMemo, useRef, useState } from "react"
import Image from "next/image"
import Link from "next/link"
import { MapPin, Phone, Search, ShoppingCart, Star, X } from "lucide-react"
import { AnimatePresence, motion } from "framer-motion"
import type { DeliveryZone, Product, ShopSettings } from "@/lib/types"
import { money, plural } from "@/lib/types"
import { cartCount, cartSum, useStore } from "@/lib/store"

export function Header({
  settings,
  zones,
  products,
}: {
  settings: ShopSettings
  zones: DeliveryZone[]
  products: Product[]
}) {
  const cart = useStore((s) => s.cart)
  const setCartOpen = useStore((s) => s.setCartOpen)
  const setQuickView = useStore((s) => s.setQuickView)
  const [cityOpen, setCityOpen] = useState(false)
  const [district, setDistrict] = useState<string | null>(null)
  const [scrolled, setScrolled] = useState(false)

  const count = cartCount(cart)
  const sum = cartSum(cart)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener("scroll", onScroll, { passive: true })
    return () => window.removeEventListener("scroll", onScroll)
  }, [])

  // Преимущества для бегущей строки (мобайл)
  const advantages = useMemo(() => {
    const raw = String(settings.top_banner || "")
    return raw.split("•").map((s) => s.trim()).filter(Boolean)
  }, [settings.top_banner])

  return (
    <>
      {/* Верхняя полоса преимуществ */}
      <div className="bg-pine text-cream/90 text-[13px] leading-none">
        <div className="mx-auto max-w-7xl px-4 hidden md:block">
          <div className="flex h-9 items-center justify-between gap-6">
            <span className="inline-flex items-center gap-1.5 font-grotesk font-semibold text-white">
              <Star className="h-3.5 w-3.5 fill-hit text-hit" aria-hidden />
              {String(settings.rating_badge || "5.0 на Яндекс Картах")}
            </span>
            <p className="text-cream/80 text-center flex-1 truncate">{advantages.join(" • ")}</p>
            <span className="text-cream/75 whitespace-nowrap">{String(settings.split_text || "")}</span>
          </div>
        </div>
        {/* мобайл: бегущая строка */}
        <div className="md:hidden overflow-hidden h-9 flex items-center" aria-hidden>
          <div className="marquee-track flex gap-8 whitespace-nowrap pl-4">
            {[...advantages, ...advantages, ...advantages, ...advantages].map((a, i) => (
              <span key={i} className="inline-flex items-center gap-2 text-cream/85">
                <span className="h-1 w-1 rounded-full bg-hit/70" />
                {a}
              </span>
            ))}
          </div>
        </div>
      </div>

      {/* Основная шапка */}
      <header
        className={`sticky top-0 z-40 bg-white/92 backdrop-blur-md transition-shadow ${
          scrolled ? "shadow-[0_1px_0_0_var(--border),0_8px_24px_-16px_rgba(24,24,27,0.25)]" : "border-b border-border"
        }`}
      >
        <div className="mx-auto max-w-7xl px-4">
          {/* Раунд 2 (критики 9 и 8): на мобиле — два ряда: лого+телефон+корзина / полноширинный поиск */}
          <div className="flex h-16 items-center gap-3 md:h-[72px] md:gap-5">
            {/* Город с выбором района */}
            <div className="relative hidden md:block">
              <button
                onClick={() => setCityOpen((v) => !v)}
                className="flex items-center gap-1.5 rounded-full px-3 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors min-h-[44px]"
                aria-expanded={cityOpen}
                aria-haspopup="listbox"
              >
                <MapPin className="h-4 w-4 text-pine" aria-hidden />
                <span className="max-w-[130px] truncate">
                  {district ? `СПб · ${district.replace(/\s*\(.*\)/, "")}` : String(settings.shop_city || "Санкт-Петербург")}
                </span>
                <svg
                  className={`h-3.5 w-3.5 text-muted-foreground transition-transform ${cityOpen ? "rotate-180" : ""}`}
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  aria-hidden
                >
                  <path d="M5.2 7.4a1 1 0 0 1 1.4 0l3.4 3.4 3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4Z" />
                </svg>
              </button>
              <CityPopover zones={zones} open={cityOpen} onClose={() => setCityOpen(false)} onPick={setDistrict} />
            </div>

            {/* Логотип */}
            <Link href="/" className="flex items-center gap-2 shrink-0 min-h-[44px]" aria-label="Nilov Flowers — на главную">
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-pine">
                <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden>
                  <g transform="translate(12 11)">
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" />
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" transform="rotate(60)" />
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" transform="rotate(120)" />
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" transform="rotate(180)" />
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" transform="rotate(240)" />
                    <ellipse rx="2.6" ry="5.5" fill="#FAF9F6" transform="rotate(300)" />
                    <circle r="2.4" fill="#F5B301" />
                  </g>
                  <rect x="11.1" y="15.5" width="1.8" height="5" rx="0.9" fill="#FAF9F6" />
                </svg>
              </span>
              <span className="font-display text-[19px] md:text-[22px] tracking-tight text-pine leading-none">
                Nilov <span className="italic">Flowers</span>
              </span>
            </Link>

            {/* Кнопка звонка (мобильная шапка) */}
            <a
              href={`tel:${String(settings.shop_phone || "").replace(/[^\d+]/g, "")}`}
              aria-label={`Позвонить: ${String(settings.shop_phone || "")}`}
              className="grid h-11 w-11 place-items-center rounded-full bg-accent text-pine transition-colors hover:bg-pine hover:text-primary-foreground lg:hidden min-h-[44px] min-w-[44px]"
            >
              <Phone className="h-5 w-5" aria-hidden />
            </a>

            {/* Живой поиск — на мобиле переносится во второй ряд */}
            <div className="hidden min-w-0 flex-1 md:block">
              <SearchDropdown products={products} onPick={(id) => setQuickView(id)} />
            </div>

            {/* Телефон */}
            <a
              href={`tel:${String(settings.shop_phone || "").replace(/[^\d+]/g, "")}`}
              className="hidden lg:flex flex-col items-end leading-tight min-h-[44px] justify-center px-2"
            >
              <span className="font-grotesk font-bold text-[15px] text-foreground tnum">
                {String(settings.shop_phone || "")}
              </span>
              <span className="text-[11px] text-muted-foreground">{String(settings.shop_phone_note || "")}</span>
            </a>

            {/* Корзина */}
            <button
              onClick={() => setCartOpen(true)}
              className="relative ml-auto flex items-center gap-2.5 rounded-full bg-pine pl-4 pr-5 py-2.5 text-primary-foreground font-grotesk font-semibold min-h-[44px] hover:bg-pine-deep transition-colors shrink-0"
              aria-label={`Корзина: ${count} ${plural(count, ["товар", "товара", "товаров"])} на ${money(sum)}`}
            >
              <ShoppingCart className="h-[18px] w-[18px]" aria-hidden />
              <span className="hidden sm:inline tnum">{count > 0 ? money(sum) : "Корзина"}</span>
              {count > 0 && (
                <span
                  className="absolute -top-1.5 -right-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-hit px-1 text-[11px] font-bold text-pine-deep tnum"
                  aria-hidden
                >
                  {count}
                </span>
              )}
            </button>
          </div>
          {/* Мобильный ряд поиска */}
          <div className="pb-3 md:hidden">
            <SearchDropdown products={products} onPick={(id) => setQuickView(id)} />
          </div>
        </div>
      </header>
    </>
  )
}

function CityPopover({
  zones,
  open,
  onClose,
  onPick,
}: {
  zones: DeliveryZone[]
  open: boolean
  onClose: () => void
  onPick: (d: string | null) => void
}) {
  const [district, setDistrict] = useState<string | null>(null)
  useEffect(() => {
    if (!open) return
    const close = () => onClose()
    const t = setTimeout(() => document.addEventListener("click", close), 0)
    return () => {
      clearTimeout(t)
      document.removeEventListener("click", close)
    }
  }, [open, onClose])

  if (!open) return null
  return (
    <div
      role="listbox"
      className="absolute left-0 top-full z-50 mt-2 w-[320px] rounded-2xl border border-border bg-white p-2 shadow-xl rise"
    >
      <div className="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
        Район доставки
      </div>
      <div className="max-h-80 overflow-y-auto nice-scroll">
        <button
          role="option"
          aria-selected={district === null}
          onClick={() => {
            setDistrict(null)
            onPick(null)
            onClose()
          }}
          className="w-full rounded-xl px-3 py-2.5 text-left text-sm hover:bg-accent transition-colors min-h-[44px] flex justify-between items-center"
        >
          <span className="font-medium">Весь Петербург</span>
        </button>
        {zones.map((z) => (
          <button
            key={z.id}
            role="option"
            aria-selected={district === z.name}
            onClick={() => {
              setDistrict(z.name)
              onPick(z.name)
              onClose()
            }}
            className="w-full rounded-xl px-3 py-2.5 text-left text-sm hover:bg-accent transition-colors min-h-[44px] flex justify-between items-center gap-2"
          >
            <span>{z.name}</span>
            <span className="text-xs text-muted-foreground whitespace-nowrap tnum">
              {z.price === 0 ? "бесплатно" : money(z.price)}
            </span>
          </button>
        ))}
      </div>
    </div>
  )
}

function SearchDropdown({ products, onPick }: { products: Product[]; onPick: (id: number) => void }) {
  const [q, setQ] = useState("")
  const [open, setOpen] = useState(false)
  const boxRef = useRef<HTMLDivElement>(null)

  const results = useMemo(() => {
    const query = q.trim().toLowerCase()
    if (query.length < 2) return []
    return products
      .filter((p) => {
        const hay = `${p.name} ${p.sub || ""} ${(p.composition || []).join(" ")} ${p.tags.join(" ")}`.toLowerCase()
        return hay.includes(query)
      })
      .slice(0, 6)
  }, [q, products])

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener("mousedown", onDoc)
    return () => document.removeEventListener("mousedown", onDoc)
  }, [])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false)
    }
    document.addEventListener("keydown", onKey)
    return () => document.removeEventListener("keydown", onKey)
  }, [])

  return (
    <div ref={boxRef} className="relative flex-1 min-w-0">
      <div className="flex items-center gap-2.5 rounded-full border border-input bg-white px-4 py-2.5 focus-within:border-pine/50 min-h-[44px] transition-colors">
        <Search className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          type="search"
          placeholder="Найти букет: пионы, розы…"
          aria-label="Поиск букетов по названию и цветам"
          className="h-11 w-full bg-transparent py-2 text-sm outline-none placeholder:text-muted-foreground min-h-[44px]"
        />
        {q && (
          <button
            onClick={() => setQ("")}
            aria-label="Очистить поиск"
            className="grid h-6 w-6 place-items-center rounded-full text-muted-foreground hover:text-foreground"
          >
            <X className="h-3.5 w-3.5" aria-hidden />
          </button>
        )}
      </div>

      <AnimatePresence>
        {open && q.trim().length >= 2 && (
          <motion.div
            initial={{ opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 6 }}
            transition={{ duration: 0.18 }}
            className="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-border bg-white shadow-xl"
            role="listbox"
            aria-label="Результаты поиска"
          >
            {results.length === 0 ? (
              <div className="px-4 py-5 text-sm text-muted-foreground">
                По запросу «{q.trim()}» не нашлось букетов — попробуйте «розы», «пионы» или «коробка»
              </div>
            ) : (
              <ul className="max-h-96 overflow-y-auto nice-scroll">
                {results.map((p) => (
                  <li key={p.id}>
                    <button
                      role="option"
                      aria-selected="false"
                      onClick={() => {
                        onPick(p.id)
                        setOpen(false)
                      }}
                      className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-accent transition-colors min-h-[44px]"
                    >
                      <span className="relative h-14 w-11 shrink-0 overflow-hidden rounded-lg bg-secondary">
                        {p.photos[0] && (
                          <Image src={p.photos[0]} alt="" fill sizes="44px" className="object-cover" />
                        )}
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium text-foreground">{p.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">{p.sub}</span>
                      </span>
                      <span className="shrink-0 font-grotesk font-bold text-sm text-foreground tnum">
                        {money(p.price)}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
