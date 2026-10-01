"use client"

import { useEffect, useState } from "react"
import Image from "next/image"
import { AnimatePresence, motion } from "framer-motion"
import { Check, Flower2, Minus, Plus, ShoppingBag, X, Zap } from "lucide-react"
import { toast } from "sonner"
import type { Product } from "@/lib/types"
import { money, splitPrice } from "@/lib/types"
import { useStore } from "@/lib/store"

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
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== "Escape") return
      // Раунд 3 (критик 11, P2): Esc закрывает верхний слой — лайтбокс отдельно от модалки
      if (document.querySelector("[data-lightbox-open]")) return
      onClose()
    }
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
  // Раунд 3 (хвост критика 6, P1): лайтбокс-зум фото — рассмотреть букет за 18 900 ₽
  const [lightbox, setLightbox] = useState(false)

  const product = products.find((p) => p.id === quickViewId) || null
  const open = !!product

  const closeModal = () => {
    setQuickView(null)
    setLightbox(false)
  }

  return (
    <ModalShell open={open} onClose={closeModal} label="Быстрый просмотр букета" wide>
      {product && (
        <>
        <div className="grid max-h-[92dvh] grid-cols-1 overflow-y-auto nice-scroll sm:grid-cols-2">
          <div className="relative aspect-[3/4] bg-secondary sm:aspect-auto sm:min-h-[540px]">
            <button
              type="button"
              onClick={() => setLightbox(true)}
              aria-label="Увеличить фото букета"
              className="group absolute inset-0 z-10"
            >
              <span className="absolute bottom-3 right-3 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-pine opacity-0 shadow-md transition-opacity group-hover:opacity-100">
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                  <circle cx="11" cy="11" r="7" />
                  <path d="m21 21-4.3-4.3M11 8v6M8 11h6" />
                </svg>
              </span>
            </button>
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
            <p className="mt-1 text-[12.5px] font-medium text-grass">
              Сплит: от {money(splitPrice(product.price))}/мес — 4 платежа без переплат
            </p>

            <dl className="mt-5 space-y-2.5 rounded-2xl bg-secondary/70 p-4 text-sm">
              <div className="flex gap-3">
                <dt className="w-24 shrink-0 text-muted-foreground">Наличие</dt>
                <dd className="font-medium text-grass">в наличии сегодня · доставка 60–90 мин по центру</dd>
              </div>
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

            <div className="sticky bottom-0 -mx-5 mt-6 bg-white/95 px-5 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-sm sm:static sm:mx-0 sm:bg-transparent sm:px-0 sm:pb-0 sm:backdrop-blur-none sm:pt-6">
              <div className="flex flex-col gap-2.5 sm:flex-row">
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
        </div>

        {/* Лайтбокс-зум (Раунд 3): клик по фото → полноэкранный просмотр */}
        <AnimatePresence>
          {lightbox && (
            <motion.div
              data-lightbox-open
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setLightbox(false)}
              className="fixed inset-0 z-[80] grid cursor-zoom-out place-items-center bg-pine-deep/90 p-4"
              role="dialog"
              aria-modal="true"
              aria-label="Фото букета крупно"
            >
              {/* Раунд 3 (критик 11, P1): настоящая полноэкранность — до 94vw / 1100px */}
              <motion.div
                initial={{ scale: 0.94, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.96, opacity: 0 }}
                transition={{ type: "spring", damping: 26, stiffness: 300 }}
                className="relative aspect-[3/4] max-h-[90dvh] w-full max-w-[min(94vw,1100px)]"
              >
                <Image
                  src={product.photos[0] || "/img/products/gen1.webp"}
                  alt={`Букет «${product.name}» крупным планом`}
                  fill
                  sizes="(max-width: 640px) 100vw, 640px"
                  className="rounded-2xl object-contain"
                />
              </motion.div>
              <button
                onClick={() => setLightbox(false)}
                aria-label="Закрыть фото"
                className="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-cream backdrop-blur hover:bg-white/20 min-h-[44px] min-w-[44px]"
              >
                <X className="h-5 w-5" aria-hidden />
              </button>
            </motion.div>
          )}
        </AnimatePresence>
        </>
      )}
    </ModalShell>
  )
}
