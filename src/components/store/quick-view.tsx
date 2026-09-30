"use client"

import { useEffect } from "react"
import Image from "next/image"
import { AnimatePresence, motion } from "framer-motion"
import { Check, Flower2, Minus, Plus, ShoppingBag, X, Zap } from "lucide-react"
import { toast } from "sonner"
import type { Product } from "@/lib/types"
import { money, splitPrice } from "@/lib/types"
import { useStore } from "@/lib/store"

// Универсальная модалка: bottom-sheet на мобиле, центрированная на десктопе
export function ModalShell({
  open,
  onClose,
  children,
  label,
  wide,
}: {
  open: boolean
  onClose: () => void
  children: React.ReactNode
  label: string
  wide?: boolean
}) {
  useEffect(() => {
    if (!open) return
    const prev = document.body.style.overflow
    document.body.style.overflow = "hidden"
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose()
    document.addEventListener("keydown", onKey)
    return () => {
      document.body.style.overflow = prev
      document.removeEventListener("keydown", onKey)
    }
  }, [open, onClose])

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label={label}>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onClose}
            className="absolute inset-0 bg-pine-deep/45 backdrop-blur-[2px]"
          />
          <motion.div
            initial={{ y: 60, opacity: 0, scale: 0.98 }}
            animate={{ y: 0, opacity: 1, scale: 1 }}
            exit={{ y: 40, opacity: 0, scale: 0.98 }}
            transition={{ type: "spring", damping: 28, stiffness: 320 }}
            className={`relative z-10 max-h-[92dvh] w-full overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl ${
              wide ? "sm:max-w-4xl" : "sm:max-w-lg"
            }`}
          >
            <button
              onClick={onClose}
              aria-label="Закрыть"
              className="absolute right-3 top-3 z-20 grid h-11 w-11 place-items-center rounded-full bg-white/90 text-foreground shadow-md backdrop-blur transition-transform hover:scale-105 min-h-[44px] min-w-[44px]"
            >
              <X className="h-5 w-5" aria-hidden />
            </button>
            {children}
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}

export function QuickView({ products }: { products: Product[]; upsellHints: unknown }) {
  const quickViewId = useStore((s) => s.quickViewId)
  const setQuickView = useStore((s) => s.setQuickView)
  const addProduct = useStore((s) => s.addProduct)
  const setOneClick = useStore((s) => s.setOneClick)

  const product = products.find((p) => p.id === quickViewId) || null
  const open = !!product

  return (
    <ModalShell open={open} onClose={() => setQuickView(null)} label="Быстрый просмотр букета" wide>
      {product && (
        <div className="grid max-h-[92dvh] grid-cols-1 overflow-y-auto nice-scroll sm:grid-cols-2">
          <div className="relative aspect-[3/4] bg-secondary sm:aspect-auto sm:min-h-[540px]">
            <Image
              src={product.photos[0] || "/img/products/gen1.webp"}
              alt={`Букет «${product.name}»`}
              fill
              sizes="(max-width: 640px) 100vw, 480px"
              className="object-cover"
            />
            {product.photos[1] && (
              <div className="relative h-20 w-full sm:hidden">
                <Image
                  src={product.photos[1]}
                  alt={`Второй ракурс букета «${product.name}»`}
                  fill
                  sizes="100vw"
                  className="object-cover"
                />
              </div>
            )}
          </div>

          <div className="flex flex-col p-5 sm:p-7">
            <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-pine">
              {product.line === "classic" ? "Классическая коллекция" : "Авторская коллекция"}
            </p>
            <h3 className="mt-1.5 font-display text-2xl leading-tight text-foreground sm:text-3xl">{product.name}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{product.sub}</p>

            <div className="mt-4 flex items-baseline gap-2.5">
              <span className="font-grotesk text-[28px] font-extrabold leading-none text-foreground tnum">
                {money(product.price)}
              </span>
              {product.oldPrice && product.oldPrice > product.price && (
                <span className="font-grotesk text-base text-muted-foreground line-through tnum">
                  {money(product.oldPrice)}
                </span>
              )}
            </div>
            <p className="mt-1 text-xs font-medium text-grass">
              Сплит: от {money(splitPrice(product.price))}/мес — 4 платежа без переплат
            </p>

            <dl className="mt-5 space-y-2.5 rounded-2xl bg-secondary/70 p-4 text-sm">
              {product.size && (
                <div className="flex gap-3">
                  <dt className="w-24 shrink-0 text-muted-foreground">Размер</dt>
                  <dd className="font-medium text-foreground">{product.size}</dd>
                </div>
              )}
              <div className="flex gap-3">
                <dt className="w-24 shrink-0 text-muted-foreground">Состав</dt>
                <dd className="font-medium text-foreground">
                  <ul className="space-y-0.5">
                    {product.composition.map((c, i) => (
                      <li key={i} className="flex items-start gap-1.5">
                        <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-grass" aria-hidden />
                        {c}
                      </li>
                    ))}
                  </ul>
                </dd>
              </div>
              <div className="flex gap-3">
                <dt className="w-24 shrink-0 text-muted-foreground">Уход</dt>
                <dd className="text-foreground">фото до отправки · замена при увядании в 7 дней</dd>
              </div>
            </dl>

            {product.description && (
              <p className="mt-4 text-[13.5px] leading-relaxed text-muted-foreground">{product.description}</p>
            )}

            <div className="mt-auto flex flex-col gap-2.5 pt-6 sm:flex-row">
              <button
                onClick={() => {
                  addProduct({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    photo: product.photos[0] || null,
                  })
                  toast.success(`«${product.name}» — в корзине`)
                  setQuickView(null)
                }}
                className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-pine font-grotesk text-sm font-bold text-primary-foreground transition-colors hover:bg-pine-deep min-h-[44px]"
              >
                <ShoppingBag className="h-4 w-4" aria-hidden />
                В корзину
              </button>
              <button
                onClick={() => {
                  setOneClick({ id: product.id, name: product.name, price: product.price, photo: product.photos[0] || null })
                  setQuickView(null)
                }}
                className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full border border-pine/25 bg-white font-grotesk text-sm font-bold text-pine transition-colors hover:bg-powder hover:text-berry min-h-[44px]"
              >
                <Zap className="h-4 w-4" aria-hidden />
                Купить в 1 клик
              </button>
            </div>
          </div>
        </div>
      )}
    </ModalShell>
  )
}
