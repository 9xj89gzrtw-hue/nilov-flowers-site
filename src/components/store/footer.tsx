"use client"

import { Clock, Flower2, MapPin, Phone, Send, Star } from "lucide-react"
import type { ShopSettings } from "@/lib/types"

export function Footer({ settings }: { settings: ShopSettings }) {
  return (
    <footer className="mt-auto bg-pine-deep text-cream/80" style={{ paddingBottom: "max(0px, env(safe-area-inset-bottom))" }}>
      <div className="mx-auto max-w-7xl px-4 py-12 md:py-14">
        <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-cream/10">
                <Flower2 className="h-5 w-5 text-cream" aria-hidden />
              </span>
              <span className="font-display text-xl text-cream">
                Nilov <span className="italic">Flowers</span>
              </span>
            </div>
            <p className="mt-4 max-w-[280px] text-[13px] leading-relaxed text-cream/60">
              Студия флористики на Мойке. 9 флористов, свой холодильник и логистика — букеты, которые
              приезжают вовремя.
            </p>
            <span className="mt-4 inline-flex items-center gap-1.5 text-[12.5px] text-cream/70">
              <Star className="h-3.5 w-3.5 fill-hit text-hit" aria-hidden />
              {String(settings.rating_badge || "")}
            </span>
          </div>

          <nav aria-label="Разделы">
            <h3 className="text-[12px] font-bold uppercase tracking-[0.14em] text-cream/40">Каталог</h3>
            <ul className="mt-4 space-y-2.5 text-[13.5px]">
              <li><a href="#catalog" className="hover:text-cream transition-colors">Все букеты</a></li>
              <li><a href="#catalog" className="hover:text-cream transition-colors">Шляпные коробки</a></li>
              <li><a href="#catalog" className="hover:text-cream transition-colors">Пионы и монобукеты</a></li>
              <li><a href="#catalog" className="hover:text-cream transition-colors">Сладости к букету</a></li>
            </ul>
          </nav>

          <div>
            <h3 className="text-[12px] font-bold uppercase tracking-[0.14em] text-cream/40">Контакты</h3>
            <ul className="mt-4 space-y-3 text-[13.5px]">
              <li>
                <a
                  href={`tel:${String(settings.shop_phone || "").replace(/[^\d+]/g, "")}`}
                  className="flex items-start gap-2.5 hover:text-cream transition-colors"
                >
                  <Phone className="mt-0.5 h-4 w-4 shrink-0 text-cream/50" aria-hidden />
                  <span>
                    <span className="block font-grotesk font-bold tnum">{String(settings.shop_phone || "")}</span>
                    <span className="block text-[12px] text-cream/55">{String(settings.shop_phone_note || "")}</span>
                  </span>
                </a>
              </li>
              <li className="flex items-start gap-2.5">
                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-cream/50" aria-hidden />
                {String(settings.shop_address || "")}
              </li>
              <li className="flex items-start gap-2.5">
                <Clock className="mt-0.5 h-4 w-4 shrink-0 text-cream/50" aria-hidden />
                {String(settings.working_hours || "")}
              </li>
              <li className="flex gap-3 pt-1">
                <a
                  href={String(settings.whatsapp || "#")}
                  target="_blank"
                  rel="noreferrer"
                  aria-label="WhatsApp"
                  className="grid h-10 w-10 place-items-center rounded-full bg-cream/10 hover:bg-cream/20 transition-colors min-h-[44px] min-w-[44px]"
                >
                  <svg viewBox="0 0 24 24" className="h-5 w-5 fill-current" aria-hidden>
                    <path d="M12.04 2a9.9 9.9 0 0 0-8.42 15.13L2.05 22l4.98-1.53A9.9 9.9 0 1 0 12.04 2Zm5.77 14.06c-.24.68-1.4 1.3-1.93 1.35-.53.06-1.02.24-3.45-.72-2.94-1.16-4.78-4.2-4.92-4.4-.15-.2-1.17-1.57-1.17-3s.75-2.11 1.01-2.4c.27-.28.58-.35.78-.35s.4 0 .57.01c.19.01.44-.07.68.52.25.6.84 2.06.91 2.21.08.15.13.32.03.52-.1.2-.31.47-.46.62-.15.15-.31.32-.16.61.15.3.66 1.09 1.42 1.77.97.87 1.77 1.14 2.05 1.27.28.13.44.11.6-.07.16-.18.7-.81.88-1.09.19-.28.37-.23.62-.13.25.1 1.6.75 1.87.89.28.13.46.2.53.31.06.12.06.68-.18 1.36Z" />
                  </svg>
                </a>
                <a
                  href={String(settings.telegram || "#")}
                  target="_blank"
                  rel="noreferrer"
                  aria-label="Telegram"
                  className="grid h-10 w-10 place-items-center rounded-full bg-cream/10 hover:bg-cream/20 transition-colors min-h-[44px] min-w-[44px]"
                >
                  <Send className="h-5 w-5" aria-hidden />
                </a>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-[12px] font-bold uppercase tracking-[0.14em] text-cream/40">Покупателям</h3>
            <ul className="mt-4 space-y-2.5 text-[13.5px]">
              <li className="text-cream/70">{String(settings.split_text || "")}</li>
              <li className="text-cream/55 text-[12.5px]">Бесплатная доставка от {new Intl.NumberFormat("ru-RU").format(Number(settings.free_delivery_from || 5000))} ₽</li>
              <li className="text-cream/55 text-[12.5px]">Фото букета перед отправкой</li>
            </ul>
            <a
              href="#admin"
              className="mt-6 inline-flex items-center gap-2 rounded-full border border-cream/15 px-4 py-2 text-[12px] text-cream/50 transition-colors hover:border-cream/40 hover:text-cream"
            >
              Вход для флористов
            </a>
          </div>
        </div>

        <div className="mt-10 flex flex-col gap-2 border-t border-cream/10 pt-6 text-[11.5px] text-cream/40 sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} Nilov Flowers · Санкт-Петербург</p>
          <p>ИП Нилов А. С. · ОГРНИП — по запросу</p>
        </div>
      </div>
      {/* распорка под мобильную липкую панель (критик 4: 69px панель + safe-area) */}
      <div className="h-[76px] md:hidden" aria-hidden />
    </footer>
  )
}
