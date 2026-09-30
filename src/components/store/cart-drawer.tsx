"use client"

import { useEffect, useState } from "react"
import Image from "next/image"
import { AnimatePresence, motion } from "framer-motion"
import { Gift, Minus, Plus, Tag, Trash2, Truck, X } from "lucide-react"
import { toast } from "sonner"
import type { ShopSettings, Upsell } from "@/lib/types"
import { money } from "@/lib/types"
import { cartSum, promoDiscount, useStore } from "@/lib/store"

export function CartDrawer({ upsells, settings }: { upsells: Upsell[]; settings: ShopSettings }) {
  const { cart, cartOpen, setCartOpen, setQty, removeLine, promo, setPromo, cardText, setCardText } = useStore()
  const setCheckoutOpen = useStore((s) => s.setCheckoutOpen)

  const [promoInput, setPromoInput] = useState("")
  const [promoBusy, setPromoBusy] = useState(false)

  const sum = cartSum(cart)
  const discount = promoDiscount(sum, promo)
  const freeFrom = Number(settings.free_delivery_from || 5000)
  const left = Math.max(0, freeFrom - sum)
  const progress = Math.min(100, Math.round((sum / freeFrom) * 100))

  useEffect(() => {
    if (!cartOpen) return
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setCartOpen(false)
    document.addEventListener("keydown", onKey)
    return () => document.removeEventListener("keydown", onKey)
  }, [cartOpen, setCartOpen])

  useEffect(() => {
    if (cartOpen) document.body.style.overflow = "hidden"
    else document.body.style.overflow = ""
    return () => {
      document.body.style.overflow = ""
    }
  }, [cartOpen])

  const applyPromo = async () => {
    if (!promoInput.trim()) return
    setPromoBusy(true)
    try {
      const r = await fetch("/api/promo", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ code: promoInput, sum }),
      })
      const data = await r.json()
      if (!r.ok) {
        toast.error(data.error)
        return
      }
      setPromo(data.promo)
      toast.success(`Промокод ${data.promo.code} применён: ${data.promo.label}`)
    } catch {
      toast.error("Сеть недоступна")
    } finally {
      setPromoBusy(false)
    }
  }

  const freeUpsells = upsells.filter((u) => u.price === 0)
  const paidUpsells = upsells.filter((u) => u.price > 0)
  const inCart = (slug: string) => cart.some((l) => l.upsellSlug === slug)

  return (
    <AnimatePresence>
      {cartOpen && (
        <div className="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Корзина">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={() => setCartOpen(false)}
            className="absolute inset-0 bg-pine-deep/45 backdrop-blur-[2px]"
          />
          <motion.aside
            initial={{ x: "100%" }}
            animate={{ x: 0 }}
            exit={{ x: "100%" }}
            transition={{ type: "spring", damping: 30, stiffness: 300 }}
            className="absolute inset-y-0 right-0 flex w-full flex-col bg-background shadow-2xl sm:w-[440px]"
          >
            {/* Шапка корзины */}
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
              <h2 className="font-display text-xl text-foreground">
                Корзина{" "}
                {cart.length > 0 && (
                  <span className="text-muted-foreground">
                    · {cart.reduce((n, l) => n + l.qty, 0)}
                  </span>
                )}
              </h2>
              <button
                onClick={() => setCartOpen(false)}
                aria-label="Закрыть корзину"
                className="grid h-11 w-11 place-items-center rounded-full text-foreground hover:bg-accent min-h-[44px] min-w-[44px]"
              >
                <X className="h-5 w-5" aria-hidden />
              </button>
            </div>

            {cart.length === 0 ? (
              <div className="flex flex-1 flex-col items-center justify-center gap-3 px-8 text-center">
                <div className="grid h-16 w-16 place-items-center rounded-full bg-secondary">
                  <Truck className="h-7 w-7 text-muted-foreground" aria-hidden />
                </div>
                <p className="font-display text-lg text-foreground">В корзине пока пусто</p>
                <p className="text-sm text-muted-foreground">
                  Соберите букет — флорист дополнит его открыткой и Кризалом для свежести.
                </p>
                <button
                  onClick={() => setCartOpen(false)}
                  className="mt-2 h-11 rounded-full bg-pine px-6 font-grotesk font-bold text-primary-foreground min-h-[44px]"
                >
                  К каталогу
                </button>
              </div>
            ) : (
              <>
                {/* Прогресс до бесплатной доставки */}
                <div className="border-b border-border bg-linen/60 px-5 py-3.5">
                  {left > 0 ? (
                    <p className="text-[13px] text-foreground">
                      <Truck className="mr-1.5 inline h-4 w-4 text-pine" aria-hidden />
                      Добавьте товаров на <b className="tnum">{money(left)}</b> для бесплатной доставки
                    </p>
                  ) : (
                    <p className="text-[13px] font-semibold text-grass">
                      <Truck className="mr-1.5 inline h-4 w-4" aria-hidden />
                      Доставка по СПб — бесплатно
                    </p>
                  )}
                  <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                    <motion.div
                      className="h-full rounded-full bg-pine"
                      initial={false}
                      animate={{ width: `${progress}%` }}
                      transition={{ duration: 0.4 }}
                    />
                  </div>
                </div>

                <div className="flex-1 overflow-y-auto nice-scroll px-5 py-4">
                  {/* Линии корзины */}
                  <ul className="space-y-3.5">
                    {cart.map((l) => (
                      <li key={l.key} className="flex gap-3.5">
                        <span className="relative h-[88px] w-[66px] shrink-0 overflow-hidden rounded-xl bg-secondary">
                          {l.photo && <Image src={l.photo} alt="" fill sizes="66px" className="object-cover" />}
                        </span>
                        <div className="flex min-w-0 flex-1 flex-col">
                          <div className="flex items-start justify-between gap-2">
                            <p className="text-sm font-medium leading-snug text-foreground">{l.title}</p>
                            <button
                              onClick={() => removeLine(l.key)}
                              aria-label={`Убрать ${l.title}`}
                              className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-powder hover:text-berry"
                            >
                              <Trash2 className="h-4 w-4" aria-hidden />
                            </button>
                          </div>
                          <p className="mt-0.5 text-[13px] font-grotesk font-bold text-foreground tnum">
                            {money(l.price)}
                          </p>
                          <div className="mt-auto flex items-center gap-1 pt-2 sm:gap-1.5">
                            <button
                              onClick={() => setQty(l.key, l.qty - 1)}
                              aria-label="Уменьшить количество"
                              className="grid h-9 w-9 place-items-center rounded-full border border-input bg-white hover:bg-accent min-h-[44px] min-w-[44px] sm:h-8 sm:w-8 sm:min-h-0 sm:min-w-0"
                            >
                              <Minus className="h-3.5 w-3.5" aria-hidden />
                            </button>
                            <span className="w-8 text-center font-grotesk text-sm font-bold tnum">{l.qty}</span>
                            <button
                              onClick={() => setQty(l.key, l.qty + 1)}
                              aria-label="Увеличить количество"
                              className="grid h-9 w-9 place-items-center rounded-full border border-input bg-white hover:bg-accent min-h-[44px] min-w-[44px] sm:h-8 sm:w-8 sm:min-h-0 sm:min-w-0"
                            >
                              <Plus className="h-3.5 w-3.5" aria-hidden />
                            </button>
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>

                  {/* Бесплатные подарки */}
                  {freeUpsells.length > 0 && (
                    <div className="mt-6">
                      <p className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-pine">
                        <Gift className="h-4 w-4" aria-hidden />
                        Добавить к букету — бесплатно
                      </p>
                      <div className="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        {freeUpsells.map((u) => (
                          <button
                            key={u.slug}
                            onClick={() => {
                              useStore.getState().addUpsell({ slug: u.slug, name: u.name, price: u.price, photo: u.photo })
                              toast.success(`${u.name} — добавлено`)
                            }}
                            disabled={inCart(u.slug)}
                            className="rounded-2xl border border-pine/15 bg-white p-3 text-left transition-colors hover:border-pine/40 hover:bg-accent disabled:opacity-50 disabled:cursor-default"
                          >
                            <span className="block text-[13px] font-semibold leading-snug text-foreground">{u.name}</span>
                            <span className="mt-0.5 line-clamp-2 block text-[11.5px] leading-snug text-muted-foreground">
                              {u.description}
                            </span>
                            <span className="mt-1.5 inline-block rounded-full bg-pine/10 px-2 py-0.5 text-[11px] font-bold text-pine">
                              0 ₽ · добавить
                            </span>
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Платные допродажи */}
                  {paidUpsells.length > 0 && (
                    <div className="mt-5">
                      <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
                        Сладости и уход
                      </p>
                      <div className="mt-3 space-y-2.5">
                        {paidUpsells.map((u) => (
                          <button
                            key={u.slug}
                            onClick={() => {
                              useStore.getState().addUpsell({ slug: u.slug, name: u.name, price: u.price, photo: u.photo })
                              toast.success(`${u.name} — в корзине`)
                            }}
                            className="flex w-full items-center gap-3 rounded-2xl border border-border bg-white p-2.5 text-left transition-colors hover:border-pine/40 hover:bg-accent"
                          >
                            <span className="relative h-14 w-12 shrink-0 overflow-hidden rounded-lg bg-secondary">
                              {u.photo && <Image src={u.photo} alt="" fill sizes="48px" className="object-cover" />}
                            </span>
                            <span className="min-w-0 flex-1">
                              <span className="block truncate text-[13px] font-semibold text-foreground">{u.name}</span>
                              <span className="block truncate text-[11.5px] text-muted-foreground">{u.description}</span>
                            </span>
                            <span className="shrink-0 font-grotesk text-sm font-bold text-foreground tnum">
                              {money(u.price)}
                            </span>
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Открытка */}
                  <div className="mt-6 rounded-2xl border border-border bg-white p-4">
                    <label htmlFor="nf-card-text" className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                      <Gift className="h-4 w-4 text-berry" aria-hidden />
                      Текст для открытки — бесплатно
                    </label>
                    <textarea
                      id="nf-card-text"
                      value={cardText}
                      onChange={(e) => setCardText(e.target.value)}
                      rows={2}
                      maxLength={500}
                      placeholder="С днём рождения! — от Евгения"
                      className="mt-2 w-full resize-none rounded-xl border border-input bg-background px-3.5 py-2.5 text-sm outline-none transition-colors focus:border-pine/50"
                    />
                    <p className="mt-1 text-right text-[11px] text-muted-foreground tnum">{cardText.length}/500</p>
                  </div>

                  {/* Промокод */}
                  <div className="mt-4">
                    {promo ? (
                      <div className="flex items-center justify-between rounded-2xl border border-grass/30 bg-grass/5 px-4 py-3">
                        <p className="text-[13px] text-foreground">
                          <Tag className="mr-1.5 inline h-3.5 w-3.5 text-grass" aria-hidden />
                          <b>{promo.code}</b> · {promo.label}
                        </p>
                        <button onClick={() => setPromo(null)} aria-label="Убрать промокод" className="text-muted-foreground hover:text-berry">
                          <X className="h-4 w-4" aria-hidden />
                        </button>
                      </div>
                    ) : (
                      <div className="flex gap-2">
                        <input
                          value={promoInput}
                          onChange={(e) => setPromoInput(e.target.value)}
                          placeholder="Промокод"
                          aria-label="Промокод"
                          className="h-11 min-w-0 flex-1 rounded-xl border border-input bg-white px-4 text-sm uppercase outline-none transition-colors focus:border-pine/50 min-h-[44px]"
                        />
                        <button
                          onClick={applyPromo}
                          disabled={promoBusy}
                          className="h-11 shrink-0 rounded-xl border border-pine/25 bg-white px-4 font-grotesk text-[13px] font-bold text-pine hover:bg-accent disabled:opacity-60 min-h-[44px]"
                        >
                          {promoBusy ? "…" : "Применить"}
                        </button>
                      </div>
                    )}
                  </div>
                </div>

                {/* Итог и чекаут */}
                <div className="border-t border-border bg-white px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                  <div className="space-y-1.5 text-sm">
                    {discount > 0 && (
                      <div className="flex justify-between text-muted-foreground">
                        <span>Скидка по промокоду</span>
                        <span className="tnum">−{money(discount)}</span>
                      </div>
                    )}
                    <div className="flex justify-between text-muted-foreground">
                      <span>Доставка по СПб</span>
                      <span>{left > 0 ? "по району, 300–500 ₽" : "бесплатно"}</span>
                    </div>
                    <div className="flex items-baseline justify-between pt-1">
                      <span className="text-[15px] font-semibold text-foreground">Итого</span>
                      <span className="font-grotesk text-[22px] font-extrabold text-foreground tnum">
                        {money(sum - discount)}
                      </span>
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      setCartOpen(false)
                      setCheckoutOpen(true)
                    }}
                    className="mt-3 inline-flex h-12 w-full items-center justify-center rounded-full bg-pine font-grotesk text-[15px] font-bold text-primary-foreground transition-colors hover:bg-pine-deep min-h-[44px]"
                  >
                    Оформить заказ
                  </button>
                  <p className="mt-2 text-center text-[11.5px] text-muted-foreground">
                    Оплата картой, СБП, наличными при получении · Яндекс Сплит частями
                  </p>
                </div>
              </>
            )}
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  )
}
