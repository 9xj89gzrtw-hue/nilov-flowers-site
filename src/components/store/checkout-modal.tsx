"use client"

import { useEffect, useMemo, useState } from "react"
import Image from "next/image"
import { Calendar, Check, Clock, CreditCard, Gift, MapPin, Phone, Store, Truck, User, Wallet } from "lucide-react"
import { toast } from "sonner"
import type { DeliveryZone, ShopSettings } from "@/lib/types"
import { deliverySlots, money, waLink } from "@/lib/types"
import { cartSum, promoDiscount, useStore } from "@/lib/store"
import { ModalShell } from "./quick-view"

export function CheckoutModal({
  zones,
  upsellHints,
  settings,
}: {
  zones: DeliveryZone[]
  upsellHints: { slug: string; name: string; price: number; photo: string | null }[]
  settings: ShopSettings
}) {
  const { cart, checkoutOpen, setCheckoutOpen, cardText, promo, clearCart } = useStore()

  const [name, setName] = useState("")
  const [phone, setPhone] = useState("")
  const [email, setEmail] = useState("")
  const [comment, setComment] = useState("")

  const [pickup, setPickup] = useState(false)
  const [surprise, setSurprise] = useState(false)
  const [knowAddress, setKnowAddress] = useState(false)
  const [recipientName, setRecipientName] = useState("")
  const [recipientPhone, setRecipientPhone] = useState("")

  const [zoneId, setZoneId] = useState<number | "">(zones[0]?.id ?? "")
  const [address, setAddress] = useState("")
  const [date, setDate] = useState("")
  const [slot, setSlot] = useState(deliverySlots[1])

  const [payment, setPayment] = useState<"online" | "cash">("online")
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<{ number: string; total: number } | null>(null)

  const zone = zones.find((z) => z.id === zoneId) || null
  const sum = cartSum(cart)
  const discount = promoDiscount(sum, promo)
  const freeFrom = Number(settings.free_delivery_from || 5000)
  // Раунд 2 (критик 8, P0): единая математика с сервером — порог бесплатной доставки
  // считается от суммы БУКЕТОВ до применения промокода (как в /api/orders и в корзине).
  const deliveryPrice = useMemo(() => {
    if (pickup) return 0
    if (!zone) return 0
    return sum >= freeFrom ? 0 : zone.price
  }, [pickup, zone, sum, freeFrom])
  const total = Math.max(0, sum - discount) + deliveryPrice

  const today = useMemo(() => new Date().toISOString().slice(0, 10), [])

  useEffect(() => {
    if (checkoutOpen && !done) {
      const prev = document.body.style.overflow
      document.body.style.overflow = "hidden"
      return () => {
        document.body.style.overflow = prev
      }
    }
  }, [checkoutOpen, done])

  const close = () => {
    setCheckoutOpen(false)
    if (done) {
      clearCart()
      setDone(null)
      setName("")
      setPhone("")
      setEmail("")
      setComment("")
      setRecipientName("")
      setRecipientPhone("")
      setAddress("")
      setDate("")
    }
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (busy) return
    if (!pickup && !knowAddress && address.trim().length < 5) {
      toast.error("Укажите адрес доставки — улицу и дом")
      return
    }
    if (!pickup && !date) {
      toast.error("Выберите дату доставки")
      return
    }
    setBusy(true)
    try {
      const r = await fetch("/api/orders", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customerName: name,
          customerPhone: phone,
          customerEmail: email,
          comment,
          recipientName,
          recipientPhone,
          surprise,
          pickup,
          knowAddress,
          zoneId: pickup ? null : zoneId || null,
          address: pickup || knowAddress ? null : address,
          deliveryDate: pickup ? null : date,
          deliverySlot: pickup ? null : slot,
          cardText,
          paymentMethod: payment,
          promoCode: promo?.code || null,
          items: cart.map((l) => ({
            kind: l.kind,
            productId: l.productId,
            upsellSlug: l.upsellSlug,
            qty: l.qty,
          })),
        }),
      })
      const data = await r.json()
      if (!r.ok) {
        toast.error(data.error || "Не удалось оформить заказ")
        return
      }
      setDone({ number: data.order.number, total: data.order.total })
    } catch {
      toast.error("Сеть недоступна — попробуйте ещё раз")
    } finally {
      setBusy(false)
    }
  }

  return (
    <ModalShell open={checkoutOpen} onClose={close} label="Оформление заказа" wide>
      <div className="max-h-[92dvh] overflow-y-auto nice-scroll">
        {done ? (
          <div className="px-6 py-10 text-center sm:px-10">
            <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-grass/10">
              <Check className="h-8 w-8 text-grass" aria-hidden />
            </div>
            <h3 className="mt-4 font-display text-3xl text-foreground">Заказ {done.number} принят</h3>
            <p className="mx-auto mt-3 max-w-[420px] text-sm leading-relaxed text-muted-foreground">
              Флорист уже начал работу. Фото готового букета пришлём в WhatsApp перед отправкой, курьер будет
              на месте в выбранный интервал.
            </p>
            <p className="mt-4 font-grotesk text-2xl font-extrabold text-foreground tnum">{money(done.total)}</p>
            <div className="mt-6 flex flex-col justify-center gap-2.5 sm:flex-row">
              <a
                href={waLink(String(settings.shop_phone || ""), `Здравствуйте! Я оформил заказ ${done.number} на сайте.`)}
                target="_blank"
                rel="noreferrer"
                className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-grass px-6 font-grotesk font-bold text-white min-h-[44px]"
              >
                Написать нам в WhatsApp
              </a>
              <button
                onClick={close}
                className="inline-flex h-12 items-center justify-center rounded-full border border-border bg-white px-6 font-grotesk font-bold text-foreground hover:bg-accent min-h-[44px]"
              >
                Продолжить покупки
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-[1.15fr_0.85fr]">
            <div className="p-5 sm:p-7">
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-pine">Оформление заказа</p>
              <h3 className="mt-1.5 font-display text-2xl text-foreground sm:text-3xl">Куда везём букет</h3>

              {/* Способ получения */}
              <div className="mt-5 grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => setPickup(false)}
                  aria-pressed={!pickup}
                  className={`flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-[13.5px] font-semibold transition-colors min-h-[44px] ${
                    !pickup ? "border-pine bg-pine text-primary-foreground" : "border-border bg-white text-foreground hover:bg-accent"
                  }`}
                >
                  <Truck className="h-4 w-4" aria-hidden />
                  Доставка курьером
                </button>
                <button
                  type="button"
                  onClick={() => setPickup(true)}
                  aria-pressed={pickup}
                  className={`flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-[13.5px] font-semibold transition-colors min-h-[44px] ${
                    pickup ? "border-pine bg-pine text-primary-foreground" : "border-border bg-white text-foreground hover:bg-accent"
                  }`}
                >
                  <Store className="h-4 w-4" aria-hidden />
                  Заберу сам
                </button>
              </div>
              {!pickup && (
                <p className="mt-2 text-[12px] text-muted-foreground">
                  Самовывоз — студия на {String(settings.shop_address || "наб. реки Мойки, 82")}
                </p>
              )}

              {/* Контакты */}
              <fieldset className="mt-6">
                <legend className="mb-3 flex items-center gap-2 text-[13px] font-bold text-foreground">
                  <User className="h-4 w-4 text-pine" aria-hidden />1 · Ваши контакты
                </legend>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <Field label="Имя*" required>
                    <input
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      required
                      minLength={2}
                      autoComplete="name"
                      placeholder="Как к вам обращаться"
                      className={inputCls}
                    />
                  </Field>
                  <Field label="Телефон*" required>
                    <input
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      required
                      type="tel"
                      inputMode="tel"
                      autoComplete="tel"
                      placeholder="+7 (___) ___-__-__"
                      className={inputCls}
                    />
                  </Field>
                  <Field label="Email — чек и фото" full>
                    <input
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      type="email"
                      autoComplete="email"
                      placeholder="example@mail.ru"
                      className={inputCls}
                    />
                  </Field>
                </div>
              </fieldset>

              {/* Получатель */}
              <fieldset className="mt-6">
                <legend className="mb-3 flex items-center gap-2 text-[13px] font-bold text-foreground">
                  <Phone className="h-4 w-4 text-pine" aria-hidden />2 · Получатель
                </legend>
                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-white p-3.5">
                  <input
                    type="checkbox"
                    checked={surprise}
                    onChange={(e) => setSurprise(e.target.checked)}
                    className="mt-0.5 h-5 w-5 shrink-0 accent-[#143C2B]"
                  />
                  <span>
                    <span className="block text-[13.5px] font-semibold text-foreground">Сюрприз получателю</span>
                    <span className="block text-[12px] text-muted-foreground">
                      Не называем ваше имя и цену — курьер передаст букет как анонимный подарок
                    </span>
                  </span>
                </label>
                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <Field label="Имя получателя">
                    <input
                      value={recipientName}
                      onChange={(e) => setRecipientName(e.target.value)}
                      placeholder="Например: Анна"
                      className={inputCls}
                    />
                  </Field>
                  <Field label="Телефон получателя">
                    <input
                      value={recipientPhone}
                      onChange={(e) => setRecipientPhone(e.target.value)}
                      type="tel"
                      inputMode="tel"
                      placeholder="+7 (___) ___-__-__"
                      className={inputCls}
                    />
                  </Field>
                </div>
                {!pickup && (
                  <label className="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-white p-3.5">
                    <input
                      type="checkbox"
                      checked={knowAddress}
                      onChange={(e) => setKnowAddress(e.target.checked)}
                      className="mt-0.5 h-5 w-5 accent-[#143C2B]"
                    />
                    <span>
                      <span className="block text-[13.5px] font-semibold text-foreground">
                        Узнать адрес у получателя
                      </span>
                      <span className="block text-[12px] text-muted-foreground">
                        Флорист сам свяжется с получателем и согласует адрес — для сюрпризов вписывайте только телефон
                      </span>
                    </span>
                  </label>
                )}
              </fieldset>

              {/* Доставка */}
              {!pickup && (
                <fieldset className="mt-6">
                  <legend className="mb-3 flex items-center gap-2 text-[13px] font-bold text-foreground">
                    <MapPin className="h-4 w-4 text-pine" aria-hidden />3 · Доставка по СПб
                  </legend>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field label="Район" full>
                      <select
                        value={zoneId}
                        onChange={(e) => setZoneId(Number(e.target.value) || "")}
                        className={inputCls}
                      >
                        {zones.map((z) => (
                          <option key={z.id} value={z.id}>
                            {z.name} — {z.price === 0 ? "бесплатно" : `${z.price} ₽`} · {z.eta || ""}
                          </option>
                        ))}
                      </select>
                    </Field>
                    <Field label={knowAddress ? "Адрес — узнает флорист" : "Адрес: улица, дом, квартира*"} full required={!knowAddress}>
                      <input
                        value={knowAddress ? "" : address}
                        onChange={(e) => setAddress(e.target.value)}
                        disabled={knowAddress}
                        required={!knowAddress}
                        autoComplete="street-address"
                        placeholder={knowAddress ? "Флорист уточнит адрес у получателя" : "Наб. реки Мойки, 82, кв. 14"}
                        className={`${inputCls} disabled:bg-secondary/60 disabled:text-muted-foreground`}
                      />
                    </Field>
                    <Field label="Дата доставки*">
                      <div className="relative">
                        <input
                          value={date}
                          onChange={(e) => setDate(e.target.value)}
                          required
                          type="date"
                          min={today}
                          max={new Date(Date.now() + 60 * 864e5).toISOString().slice(0, 10)}
                          className={inputCls}
                        />
                        <Calendar className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                      </div>
                    </Field>
                    <Field label="Интервал">
                      <div className="relative">
                        <select value={slot} onChange={(e) => setSlot(e.target.value)} className={inputCls}>
                          {deliverySlots.map((s) => (
                            <option key={s} value={s}>
                              {s}
                            </option>
                          ))}
                        </select>
                        <Clock className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                      </div>
                    </Field>
                  </div>
                  {zone && zone.note && <p className="mt-2 text-[12px] text-grass">{zone.note}</p>}
                </fieldset>
              )}

              {/* Открытка — редактируется прямо здесь (критик 3, P2: раньше только в корзине) */}
              <fieldset className="mt-6">
                <legend className="mb-3 flex items-center gap-2 text-[13px] font-bold text-foreground">
                  <Gift className="h-4 w-4 text-pine" aria-hidden />4 · Открытка — бесплатно
                </legend>
                <textarea
                  value={cardText}
                  onChange={(e) => useStore.getState().setCardText(e.target.value)}
                  rows={2}
                  maxLength={500}
                  placeholder="С днём рождения! — от Евгения"
                  className="h-auto w-full resize-none rounded-xl border border-input bg-white px-4 py-3 text-[15px] outline-none transition-colors focus:border-pine/50 min-h-[44px]"
                />
                <p className="mt-1 flex justify-between text-[11px] text-muted-foreground">
                  <span>Флорист напишет от руки и вложит в букет</span>
                  <span className="tnum">{cardText.length}/500</span>
                </p>
              </fieldset>

              {/* Оплата */}
              <fieldset className="mt-6">
                <legend className="mb-3 flex items-center gap-2 text-[13px] font-bold text-foreground">
                  <CreditCard className="h-4 w-4 text-pine" aria-hidden />5 · Оплата
                </legend>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  <PayOption
                    active={payment === "online"}
                    onClick={() => setPayment("online")}
                    icon={<Wallet className="h-4 w-4" aria-hidden />}
                    title="Картой или СБП"
                    sub="ссылка на оплату придёт после подтверждения"
                  />
                  <PayOption
                    active={payment === "cash"}
                    onClick={() => setPayment("cash")}
                    icon={<Truck className="h-4 w-4" aria-hidden />}
                    title="При получении"
                    sub="наличными или картой курьеру"
                  />
                </div>
                <p className="mt-2 text-[12px] text-muted-foreground">{String(settings.split_text || "")}</p>
              </fieldset>

              <Field label="Комментарий — цветовые пожелания, домофон" full>
                <textarea
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  rows={2}
                  placeholder="Например: пастельная гамма, домофон 14К, позвонить за 15 минут"
                  className={`${inputCls} resize-none`}
                />
              </Field>

              {/* Раунд 2 (критик 9, P1): мобильная sticky-кнопка сабмита — до кнопки не надо скроллить 1200px */}
              <div className="sticky bottom-0 -mx-5 mt-6 bg-white/95 px-5 pt-3 pb-[max(0.5rem,env(safe-area-inset-bottom))] backdrop-blur-sm lg:hidden">
                <button
                  type="submit"
                  disabled={busy || cart.length === 0}
                  className="h-12 w-full rounded-full bg-pine font-grotesk text-[15px] font-bold text-primary-foreground transition-colors hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
                >
                  {busy ? "Оформляем…" : `Подтвердить заказ — ${money(total)}`}
                </button>
              </div>
            </div>

            {/* Сводка */}
            <aside className="border-t border-border bg-linen/50 p-5 sm:p-7 lg:border-l lg:border-t-0">
              <div className="lg:sticky lg:top-6">
              <h4 className="text-[13px] font-bold text-foreground">Ваш заказ</h4>
              <ul className="mt-3 max-h-56 space-y-2.5 overflow-y-auto nice-scroll">
                {cart.map((l) => (
                  <li key={l.key} className="flex items-center gap-2.5">
                    <span className="relative h-12 w-9 shrink-0 overflow-hidden rounded-md bg-white">
                      {l.photo && <Image src={l.photo} alt="" fill sizes="36px" className="object-cover" />}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-[12.5px] font-medium text-foreground">{l.title}</span>
                      <span className="block text-[11px] text-muted-foreground tnum">
                        {l.qty} × {money(l.price)}
                      </span>
                    </span>
                    <span className="font-grotesk text-[13px] font-bold text-foreground tnum">
                      {money(l.price * l.qty)}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="mt-4 space-y-1.5 border-t border-border pt-3 text-[13px]">
                <div className="flex justify-between text-muted-foreground">
                  <span>Букеты и подарки</span>
                  <span className="tnum">{money(sum)}</span>
                </div>
                {discount > 0 && (
                  <div className="flex justify-between text-berry">
                    <span>Промокод {promo?.code}</span>
                    <span className="tnum">−{money(discount)}</span>
                  </div>
                )}
                <div className="flex justify-between text-muted-foreground">
                  <span>
                    Доставка {zone ? `· ${zone.name.replace(/\s*\(.*\)/, "")}` : pickup ? "· самовывоз" : ""}
                  </span>
                  <span className="tnum">{deliveryPrice === 0 ? "бесплатно" : money(deliveryPrice)}</span>
                </div>
                {!pickup && sum < freeFrom && deliveryPrice > 0 && (
                  <p className="text-[11.5px] text-muted-foreground">
                    Бесплатно от {money(freeFrom)} (по сумме букетов) — добавьте ещё {money(freeFrom - sum)}
                  </p>
                )}
                <div className="flex items-baseline justify-between pt-2">
                  <span className="text-[15px] font-bold text-foreground">Итого</span>
                  <span className="font-grotesk text-[24px] font-extrabold text-foreground tnum">{money(total)}</span>
                </div>
              </div>

              <div className="sticky bottom-0 -mx-5 mt-5 bg-linen/80 px-5 pt-3 pb-1 backdrop-blur-sm sm:-mx-7 sm:px-7">
                <button
                  type="submit"
                  disabled={busy || cart.length === 0}
                  className="h-12 w-full rounded-full bg-pine font-grotesk text-[15px] font-bold text-primary-foreground transition-colors hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
                >
                  {busy ? "Оформляем…" : `Подтвердить заказ — ${money(total)}`}
                </button>
              </div>
              <p className="mt-3 text-center text-[11px] leading-relaxed text-muted-foreground">
                Нажимая кнопку, вы соглашаетесь на обработку персональных данных. Флорист позвонит для подтверждения.
              </p>
              </div>
            </aside>
          </form>
        )}
      </div>
    </ModalShell>
  )
}

const inputCls =
  "h-12 w-full rounded-xl border border-input bg-white px-4 text-[15px] outline-none transition-colors focus:border-pine/50 min-h-[44px]"

function Field({
  label,
  children,
  full,
  required,
}: {
  label: string
  children: React.ReactNode
  full?: boolean
  required?: boolean
}) {
  return (
    <label className={`block ${full ? "sm:col-span-2" : ""}`}>
      <span className="mb-1.5 block text-[12.5px] font-semibold text-foreground">{label}</span>
      {children}
    </label>
  )
}

function PayOption({
  active,
  onClick,
  icon,
  title,
  sub,
}: {
  active: boolean
  onClick: () => void
  icon: React.ReactNode
  title: string
  sub: string
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`flex items-start gap-3 rounded-xl border p-3.5 text-left transition-colors ${
        active ? "border-pine bg-pine/5" : "border-border bg-white hover:bg-accent"
      }`}
    >
      <span className={`mt-0.5 ${active ? "text-pine" : "text-muted-foreground"}`}>{icon}</span>
      <span>
        <span className="block text-[13.5px] font-semibold text-foreground">{title}</span>
        <span className="block text-[11.5px] text-muted-foreground">{sub}</span>
      </span>
    </button>
  )
}
