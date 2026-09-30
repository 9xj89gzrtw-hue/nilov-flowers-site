"use client"

import { useState } from "react"
import { Camera, ChevronDown, Clock, Leaf, MapPin, Shield, Star, Truck } from "lucide-react"
import { motion } from "framer-motion"
import type { DeliveryZone, ShopSettings } from "@/lib/types"
import { money } from "@/lib/types"
import type { Editorial } from "./store-app"

const GUARANTEE_ICONS: Record<string, React.ReactNode> = {
  leaf: <Leaf className="h-5 w-5" aria-hidden />,
  camera: <Camera className="h-5 w-5" aria-hidden />,
  truck: <Truck className="h-5 w-5" aria-hidden />,
  shield: <Shield className="h-5 w-5" aria-hidden />,
}

const GUARANTEES = [
  { icon: "leaf", t: "Срезка этого утра", d: "Две поставки в неделю, свой холодильник на 3 °C, никакого «вчерашнего» стока." },
  { icon: "camera", t: "Фото до отправки", d: "Подтверждаете снимок — только потом курьер выезжает." },
  { icon: "truck", t: "90 минут по центру", d: "Своя логистика: 6 машин с термобоксами и диспетчер на связи." },
  { icon: "shield", t: "7 дней гарантии", d: "Завяло раньше — меняем букет целиком, без экспертиз и чеков." },
]

const STEPS = [
  { n: "01", t: "Выбираете букет", d: "В каталоге — только букеты в наличии: то, что видите, то и приедет." },
  { n: "02", t: "Подтверждаем детали", d: "Флорист перезванивает за 5 минут и присылает фото готового букета." },
  { n: "03", t: "Курьер везёт вовремя", d: "Термобокс, интервал 2 часа, открытка с вашим текстом уже в букете." },
]

export function Sections({ settings, zones, editorial }: { settings: ShopSettings; zones: DeliveryZone[]; editorial: Editorial }) {
  return (
    <>
      {/* Преимущества */}
      <section className="bg-linen/70 py-14 md:py-20" aria-label="Гарантии">
        <div className="mx-auto max-w-7xl px-4">
          <SectionHead kicker="Почему мы" title="Гарантии, которые можно проверить" />
          <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {GUARANTEES.map((g, i) => (
              <motion.div
                key={g.t}
                initial={{ opacity: 0, y: 16 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "60px" }}
                transition={{ duration: 0.45, delay: i * 0.06 }}
                className="rounded-3xl bg-white p-6 shadow-sm"
              >
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-pine/10 text-pine">
                  {GUARANTEE_ICONS[g.icon]}
                </span>
                <h3 className="mt-4 font-grotesk text-[15px] font-bold text-foreground">{g.t}</h3>
                <p className="mt-1.5 text-[13.5px] leading-relaxed text-muted-foreground">{g.d}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Как это работает */}
      <section className="py-14 md:py-20" aria-label="Как это работает">
        <div className="mx-auto max-w-7xl px-4">
          <SectionHead kicker="3 шага" title="Как это работает" />
          <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3">
            {STEPS.map((s, i) => (
              <motion.div
                key={s.n}
                initial={{ opacity: 0, y: 16 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "60px" }}
                transition={{ duration: 0.45, delay: i * 0.08 }}
                className="relative rounded-3xl border border-border bg-white p-6"
              >
                <span className="font-display text-4xl italic text-pine/25">{s.n}</span>
                <h3 className="mt-2 font-grotesk text-[15px] font-bold text-foreground">{s.t}</h3>
                <p className="mt-1.5 text-[13.5px] leading-relaxed text-muted-foreground">{s.d}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Доставка по СПб */}
      <section className="bg-pine py-14 text-cream md:py-20" aria-label="Доставка по Санкт-Петербургу">
        <div className="mx-auto max-w-7xl px-4">
          <p className="text-[12px] font-semibold uppercase tracking-[0.14em] text-cream/60">Доставка</p>
          <h2 className="mt-2 font-display text-3xl tracking-tight text-white md:text-4xl text-balance">
            По всем районам Петербурга — честный тариф
          </h2>
          <p className="mt-3 max-w-[560px] text-[14px] leading-relaxed text-cream/75">
            Бесплатно от {money(Number(settings.free_delivery_from || 5000))}. Курьер пишет за 15 минут до приезда
            и ждёт у подъезда столько, сколько нужно.
          </p>
          <div className="mt-8 grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
            {zones.map((z) => (
              <div
                key={z.id}
                className="flex items-center justify-between gap-3 rounded-2xl bg-white/8 px-5 py-3.5 ring-1 ring-inset ring-white/10"
              >
                <div className="min-w-0">
                  <p className="truncate text-[14px] font-medium text-white">{z.name}</p>
                  <p className="text-[12px] text-cream/60">{z.eta}</p>
                </div>
                <span className="shrink-0 font-grotesk text-[15px] font-bold text-hit tnum">
                  {z.price === 0 ? "бесплатно" : money(z.price)}
                </span>
              </div>
            ))}
          </div>
          <p className="mt-5 flex items-center gap-2 text-[13px] text-cream/70">
            <MapPin className="h-4 w-4 shrink-0" aria-hidden />
            Самовывоз — студия: {String(settings.shop_address || "")}. Пригороды (Мурино, Кудрово, Пушкин) — в таблице.
          </p>
        </div>
      </section>

      {/* Отзывы */}
      <section className="py-14 md:py-20" aria-label="Отзывы покупателей">
        <div className="mx-auto max-w-7xl px-4">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHead
              kicker="Отзывы"
              title={`${String(editorial.rating.count).replace(/\B(?=(\d{3})+(?!\d))/g, " ")} отзывов · средний балл ${editorial.rating.value}`}
            />
            <span className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2.5 shadow-sm">
              <Star className="h-4 w-4 fill-hit text-hit" aria-hidden />
              <span className="text-[13px] font-semibold text-foreground">{String(settings.rating_badge || "")}</span>
            </span>
          </div>
          <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {editorial.reviews.slice(0, 4).map((r, i) => (
              <motion.figure
                key={i}
                initial={{ opacity: 0, y: 16 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "60px" }}
                transition={{ duration: 0.45, delay: i * 0.06 }}
                className="flex flex-col rounded-3xl bg-white p-6 shadow-sm"
              >
                <div className="flex gap-0.5" aria-label={`Оценка ${r.r} из 5`}>
                  {Array.from({ length: r.r }).map((_, j) => (
                    <Star key={j} className="h-3.5 w-3.5 fill-hit text-hit" aria-hidden />
                  ))}
                </div>
                <blockquote className="mt-3 flex-1 text-[13.5px] leading-relaxed text-foreground">«{r.t}»</blockquote>
                <figcaption className="mt-4 flex items-center justify-between text-[12px]">
                  <span className="font-semibold text-foreground">{r.n}</span>
                  <span className="text-muted-foreground">
                    {r.src} · {r.d}
                  </span>
                </figcaption>
              </motion.figure>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="bg-linen/70 py-14 md:py-20" aria-label="Частые вопросы">
        <div className="mx-auto max-w-3xl px-4">
          <SectionHead kicker="FAQ" title="Частые вопросы" />
          <div className="mt-8 space-y-2.5">
            {editorial.faq.slice(0, 6).map((f, i) => (
              <FaqItem key={i} q={f.q} a={f.a} defaultOpen={i === 0} />
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-14 md:py-20" aria-label="Призыв к действию">
        <div className="mx-auto max-w-7xl px-4">
          <div className="relative overflow-hidden rounded-[32px] bg-pine px-6 py-12 text-center md:py-16">
            <div
              className="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-hit/10 blur-3xl"
              aria-hidden
            />
            <div
              className="pointer-events-none absolute -bottom-32 -left-16 h-64 w-64 rounded-full bg-white/5 blur-3xl"
              aria-hidden
            />
            <h2 className="font-display text-3xl tracking-tight text-white md:text-[40px] text-balance">
              Повод не обязателен
            </h2>
            <p className="mx-auto mt-3 max-w-[480px] text-[14.5px] leading-relaxed text-cream/75">
              Флорист на связи {String(settings.working_hours || "круглосуточно")} — соберём и доставим букет
              в любой район Петербурга от 60 минут.
            </p>
            <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
              <a
                href="#catalog"
                className="inline-flex h-12 items-center justify-center rounded-full bg-hit px-7 font-grotesk font-bold text-pine-deep transition-transform hover:scale-[1.02] min-h-[44px]"
              >
                Выбрать букет
              </a>
              <a
                href={`tel:${String(settings.shop_phone || "").replace(/[^\d+]/g, "")}`}
                className="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-white/25 px-7 font-grotesk font-bold text-white transition-colors hover:bg-white/10 min-h-[44px]"
              >
                <Clock className="h-4 w-4" aria-hidden />
                {String(settings.shop_phone || "")}
              </a>
            </div>
          </div>
        </div>
      </section>
    </>
  )
}

function SectionHead({ kicker, title }: { kicker: string; title: string }) {
  return (
    <div>
      <p className="text-[12px] font-semibold uppercase tracking-[0.14em] text-pine">{kicker}</p>
      <h2 className="mt-2 font-display text-3xl tracking-tight text-foreground md:text-4xl text-balance">{title}</h2>
    </div>
  )
}

function FaqItem({ q, a, defaultOpen }: { q: string; a: string; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(!!defaultOpen)
  return (
    <div className="overflow-hidden rounded-2xl border border-border bg-white">
      <button
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left min-h-[44px]"
      >
        <span className="text-[14.5px] font-semibold text-foreground">{q}</span>
        <ChevronDown
          className={`h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-300 ${open ? "rotate-180" : ""}`}
          aria-hidden
        />
      </button>
      <div
        className={`grid transition-all duration-300 ease-out ${open ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"}`}
      >
        <div className="overflow-hidden">
          <p className="px-5 pb-4 text-[13.5px] leading-relaxed text-muted-foreground">{a}</p>
        </div>
      </div>
    </div>
  )
}
