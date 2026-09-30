"use client"

import Image from "next/image"
import { ArrowRight, Camera, Clock, Sparkles, Star } from "lucide-react"
import type { ShopSettings } from "@/lib/types"
import { money } from "@/lib/types"

export function Hero({ settings, minPrice }: { settings: ShopSettings; minPrice: number }) {
  return (
    <section className="relative overflow-hidden bg-gradient-to-b from-cream via-linen to-cream" aria-label="Главный экран">
      <div className="mx-auto grid max-w-7xl grid-cols-1 items-center gap-8 px-4 pb-12 pt-10 md:pb-20 md:pt-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14">
        <div className="rise">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-pine/15 bg-white px-4 py-1.5 text-[13px] font-medium text-pine">
            <Sparkles className="h-3.5 w-3.5" aria-hidden />
            {String(settings.shop_city || "Санкт-Петербург")} · свежий срез каждое утро
          </p>
          <h1 className="font-display text-[34px] leading-[1.08] tracking-tight text-foreground sm:text-5xl lg:text-[64px] lg:leading-[1.03] text-balance">
            {String(settings.hero_title || "Букеты, которые приезжают вовремя")}
          </h1>
          <p className="mt-5 max-w-[520px] text-[15px] leading-relaxed text-muted-foreground sm:text-base">
            {String(settings.hero_lead || "")}
          </p>

          <div className="mt-8 flex flex-wrap items-center gap-3">
            <a
              href="#catalog"
              className="group inline-flex h-14 items-center gap-2.5 rounded-full bg-pine px-7 font-grotesk text-[15px] font-bold text-primary-foreground transition-all hover:bg-pine-deep hover:shadow-lg hover:shadow-pine/25"
            >
              Выбрать букет
              <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden />
            </a>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2.5 text-[13px] font-semibold text-foreground shadow-sm">
              <Star className="h-4 w-4 fill-hit text-hit" aria-hidden />
              {String(settings.rating_badge || "5.0 на Яндекс Картах")}
            </span>
            {/* Раунд 1 (критик 3, P2): ценовой якорь в hero */}
            {minPrice > 0 && (
              <span className="w-full text-[13px] font-medium text-muted-foreground sm:w-auto">
                Готовые букеты — <b className="font-grotesk text-foreground tnum">от {money(minPrice)}</b> · в наличии сегодня
              </span>
            )}
          </div>

          <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-[13px] text-muted-foreground">
            <li className="inline-flex items-center gap-2">
              <Clock className="h-4 w-4 text-pine" aria-hidden />
              Доставка от 60 минут
            </li>
            <li className="inline-flex items-center gap-2">
              <Camera className="h-4 w-4 text-pine" aria-hidden />
              Фото букета до отправки
            </li>
            <li className="inline-flex items-center gap-2">
              <Sparkles className="h-4 w-4 text-pine" aria-hidden />
              Бесплатная открытка
            </li>
          </ul>
        </div>

        <div className="relative mx-auto w-full max-w-[520px] rise" style={{ animationDelay: "80ms" }}>
          <div className="relative aspect-[4/5] overflow-hidden rounded-[28px] shadow-2xl shadow-pine/15 sm:aspect-[5/4] lg:aspect-[4/5]">
            <Image
              src="/img/editorial/petals-hero-4x3.webp"
              alt="Свежие лепестки пионов — утренний срез Nilov Flowers"
              fill
              priority
              sizes="(max-width: 1024px) 100vw, 520px"
              className="object-cover"
            />
            <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/45 to-transparent" aria-hidden />
            <figcaption className="absolute bottom-5 left-6 right-6 text-[13px] leading-snug text-white/90">
              Утренний срез · розы Ohara и пионы Sarah Bernhardt
            </figcaption>
          </div>
          <div className="absolute -bottom-5 -left-3 hidden rounded-2xl bg-white/95 p-4 shadow-lg shadow-black/10 backdrop-blur-sm sm:block">
            <div className="font-grotesk text-2xl font-extrabold text-pine tnum">48 000+</div>
            <div className="text-xs text-muted-foreground">букетов доставили за 2025 год</div>
          </div>
        </div>
      </div>
    </section>
  )
}
