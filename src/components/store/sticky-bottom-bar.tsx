"use client"

import { useEffect, useState } from "react"
import { LayoutGrid, Search, Send, ShoppingCart } from "lucide-react"
import type { ShopSettings } from "@/lib/types"
import { money } from "@/lib/types"
import { cartCount, cartSum, useStore } from "@/lib/store"

export function StickyBottomBar({ settings }: { settings: ShopSettings }) {
  const cart = useStore((s) => s.cart)
  const setCartOpen = useStore((s) => s.setCartOpen)
  const [visible, setVisible] = useState(true)
  const [lastY, setLastY] = useState(0)
  const count = cartCount(cart)
  const sum = cartSum(cart)

  // Панель прячет при скролле вниз, показывает при скролле вверх
  useEffect(() => {
    const onScroll = () => {
      const y = window.scrollY
      setVisible(y < lastY || y < 120)
      setLastY(y)
    }
    window.addEventListener("scroll", onScroll, { passive: true })
    return () => window.removeEventListener("scroll", onScroll)
  }, [lastY])

  const scrollToCatalog = () => {
    document.getElementById("catalog")?.scrollIntoView({ behavior: "smooth" })
  }

  const focusSearch = () => {
    const input = document.querySelector<HTMLInputElement>('input[type="search"]')
    input?.focus()
    input?.scrollIntoView({ behavior: "smooth", block: "center" })
  }

  return (
    <nav
      aria-label="Быстрая навигация"
      className={`fixed inset-x-0 bottom-0 z-40 border-t border-border bg-white/95 backdrop-blur-md transition-transform duration-300 md:hidden ${
        visible ? "translate-y-0" : "translate-y-full"
      }`}
      style={{ paddingBottom: "max(0.5rem, env(safe-area-inset-bottom))" }}
    >
      <div className="grid grid-cols-[1fr_1fr_1.25fr_auto] items-stretch gap-1 px-2 pt-2">
        <button
          onClick={scrollToCatalog}
          className="flex min-h-[52px] flex-col items-center justify-center gap-1 rounded-xl text-muted-foreground hover:bg-accent hover:text-pine"
        >
          <LayoutGrid className="h-5 w-5" aria-hidden />
          <span className="text-[10.5px] font-semibold">Каталог</span>
        </button>
        <button
          onClick={focusSearch}
          className="flex min-h-[52px] flex-col items-center justify-center gap-1 rounded-xl text-muted-foreground hover:bg-accent hover:text-pine"
        >
          <Search className="h-5 w-5" aria-hidden />
          <span className="text-[10.5px] font-semibold">Поиск</span>
        </button>
        <button
          onClick={() => setCartOpen(true)}
          className="flex min-h-[52px] items-center justify-center gap-2 rounded-xl bg-pine font-grotesk font-bold text-primary-foreground"
          aria-label={`Корзина: ${count} позиций на ${money(sum)}`}
        >
          <ShoppingCart className="h-5 w-5" aria-hidden />
          <span className="text-[13px] tnum">{count > 0 ? money(sum) : "Корзина"}</span>
          {count > 0 && (
            <span className="grid h-5 min-w-5 place-items-center rounded-full bg-hit px-1 text-[11px] font-bold text-pine-deep tnum">
              {count}
            </span>
          )}
        </button>
        <div className="flex flex-col justify-center gap-1 pr-1">
          <a
            href={String(settings.whatsapp || "#")}
            target="_blank"
            rel="noreferrer"
            aria-label="Написать в WhatsApp"
            className="grid h-6 w-6 place-items-center rounded-full bg-grass/10 text-grass"
          >
            <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 fill-current" aria-hidden>
              <path d="M12.04 2a9.9 9.9 0 0 0-8.42 15.13L2.05 22l4.98-1.53A9.9 9.9 0 1 0 12.04 2Zm5.77 14.06c-.24.68-1.4 1.3-1.93 1.35-.53.06-1.02.24-3.45-.72-2.94-1.16-4.78-4.2-4.92-4.4-.15-.2-1.17-1.57-1.17-3s.75-2.11 1.01-2.4c.27-.28.58-.35.78-.35s.4 0 .57.01c.19.01.44-.07.68.52.25.6.84 2.06.91 2.21.08.15.13.32.03.52-.1.2-.31.47-.46.62-.15.15-.31.32-.16.61.15.3.66 1.09 1.42 1.77.97.87 1.77 1.14 2.05 1.27.28.13.44.11.6-.07.16-.18.7-.81.88-1.09.19-.28.37-.23.62-.13.25.1 1.6.75 1.87.89.28.13.46.2.53.31.06.12.06.68-.18 1.36Z" />
            </svg>
          </a>
          <a
            href={String(settings.telegram || "#")}
            target="_blank"
            rel="noreferrer"
            aria-label="Написать в Telegram"
            className="grid h-6 w-6 place-items-center rounded-full bg-pine/10 text-pine"
          >
            <Send className="h-3.5 w-3.5" aria-hidden />
          </a>
        </div>
      </div>
    </nav>
  )
}
