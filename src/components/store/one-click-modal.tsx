"use client"

import { useState } from "react"
import Image from "next/image"
import { Zap } from "lucide-react"
import { toast } from "sonner"
import { money } from "@/lib/types"
import { useStore } from "@/lib/store"
import { ModalShell } from "./quick-view"

// «Купить в 1 клик»: только имя и телефон — заказ сразу падает флористу
export function OneClickModal() {
  const oneClick = useStore((s) => s.oneClickProduct)
  const setOneClick = useStore((s) => s.setOneClick)
  const [name, setName] = useState("")
  const [phone, setPhone] = useState("")
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<string | null>(null)

  const close = () => {
    setOneClick(null)
    setDone(null)
    setName("")
    setPhone("")
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!oneClick) return
    setBusy(true)
    try {
      const r = await fetch("/api/orders", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customerName: name,
          customerPhone: phone,
          oneClick: true,
          paymentMethod: "cash",
          items: [{ kind: "product", productId: oneClick.id, qty: 1 }],
        }),
      })
      const data = await r.json()
      if (!r.ok) {
        toast.error(data.error || "Не удалось оформить заказ")
        return
      }
      setDone(data.order.number)
    } catch {
      toast.error("Сеть недоступна — попробуйте ещё раз")
    } finally {
      setBusy(false)
    }
  }

  return (
    <ModalShell open={!!oneClick} onClose={close} label="Купить в один клик">
      {oneClick && (
        <div className="max-h-[92dvh] overflow-y-auto nice-scroll p-6 sm:p-7">
          {done ? (
            <div className="py-6 text-center">
              <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-pine/10">
                <Zap className="h-6 w-6 text-pine" aria-hidden />
              </div>
              <h3 className="mt-4 font-display text-2xl text-foreground">Заказ {done} принят</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                Флорист уже видит его на рабочем столе и перезвонит в течение 5 минут, чтобы подтвердить букет,
                адрес и время доставки.
              </p>
              <button
                onClick={close}
                className="mt-6 h-12 w-full rounded-full bg-pine font-grotesk font-bold text-primary-foreground min-h-[44px]"
              >
                Отлично
              </button>
            </div>
          ) : (
            <form onSubmit={submit}>
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-pine">Купить в 1 клик</p>
              <h3 className="mt-1.5 font-display text-2xl text-foreground">{oneClick.name}</h3>
              <div className="mt-4 flex items-center gap-3.5 rounded-2xl bg-secondary/70 p-3">
                <span className="relative h-20 w-16 shrink-0 overflow-hidden rounded-xl bg-white">
                  {oneClick.photo && <Image src={oneClick.photo} alt="" fill sizes="64px" className="object-cover" />}
                </span>
                <div>
                  <p className="font-grotesk text-xl font-extrabold text-foreground tnum">{money(oneClick.price)}</p>
                  <p className="mt-0.5 text-xs text-muted-foreground">фото букета до отправки · замена 7 дней</p>
                </div>
              </div>

              <div className="mt-5 space-y-3.5">
                <label className="block">
                  <span className="mb-1.5 block text-[13px] font-semibold text-foreground">Ваше имя</span>
                  <input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    minLength={2}
                    autoComplete="name"
                    placeholder="Как к вам обращаться"
                    className="h-12 w-full rounded-xl border border-input bg-white px-4 text-[15px] outline-none transition-colors focus:border-pine/50 min-h-[44px]"
                  />
                </label>
                <label className="block">
                  <span className="mb-1.5 block text-[13px] font-semibold text-foreground">Телефон</span>
                  <input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    required
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    placeholder="+7 (___) ___-__-__"
                    className="h-12 w-full rounded-xl border border-input bg-white px-4 text-[15px] outline-none transition-colors focus:border-pine/50 min-h-[44px]"
                  />
                </label>
              </div>
              <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                Флорист перезвонит в течение 5 минут, уточнит адрес и время. Оплата — картой, СБП или при получении.
              </p>
              <button
                type="submit"
                disabled={busy}
                className="mt-5 h-12 w-full rounded-full bg-pine font-grotesk font-bold text-primary-foreground transition-colors hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
              >
                {busy ? "Отправляем…" : "Заказать — флорист перезвонит"}
              </button>
            </form>
          )}
        </div>
      )}
    </ModalShell>
  )
}
