"use client"

import { useCallback, useEffect, useRef, useState } from "react"
import { Flower2, LayoutDashboard, LogOut, Package, Settings, ShoppingBag, Truck } from "lucide-react"
import { toast } from "sonner"
import type { DeliveryZone, Order, Product, Upsell } from "@/lib/types"
import { parseOrderRow, parseProductRow } from "@/lib/types"
import { AdminOrders } from "./admin-orders"
import { AdminCatalog } from "./admin-catalog"
import { AdminSettings } from "./admin-settings"
import { AdminZones } from "./admin-zones"
import { AdminUpsells } from "./admin-upsells"

type Tab = "orders" | "catalog" | "zones" | "upsells" | "settings"

export function AdminGate({ onExit }: { onExit: () => void }) {
  const [authed, setAuthed] = useState<boolean | null>(null)

  useEffect(() => {
    fetch("/api/admin/session")
      .then((r) => r.json())
      .then((d) => setAuthed(!!d.admin))
      .catch(() => setAuthed(false))
  }, [])

  if (authed === null) {
    return (
      <div className="grid min-h-screen place-items-center bg-linen">
        <div className="animate-pulse text-sm text-muted-foreground">Проверяем сессию…</div>
      </div>
    )
  }

  if (!authed) return <AdminLogin onSuccess={() => setAuthed(true)} onExit={onExit} />
  return <AdminShell onExit={onExit} onLogout={() => setAuthed(false)} />
}

function AdminLogin({ onSuccess, onExit }: { onSuccess: () => void; onExit: () => void }) {
  const [password, setPassword] = useState("")
  const [busy, setBusy] = useState(false)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    try {
      const r = await fetch("/api/admin/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
      })
      const data = await r.json()
      if (!r.ok) {
        toast.error(data.error || "Неверный пароль")
        return
      }
      onSuccess()
    } catch {
      toast.error("Сеть недоступна")
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid min-h-screen place-items-center bg-linen px-4">
      <form onSubmit={submit} className="w-full max-w-sm rounded-3xl bg-white p-8 shadow-xl rise">
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-pine">
          <Flower2 className="h-7 w-7 text-cream" aria-hidden />
        </div>
        <h1 className="mt-5 text-center font-display text-2xl text-foreground">Панель флориста</h1>
        <p className="mt-1.5 text-center text-[13px] text-muted-foreground">
          Nilov Flowers · рабочее место смены
        </p>
        <label className="mt-6 block">
          <span className="mb-1.5 block text-[13px] font-semibold text-foreground">Пароль</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoFocus
            autoComplete="current-password"
            placeholder="••••••••"
            className="h-12 w-full rounded-xl border border-input bg-white px-4 text-[15px] outline-none transition-colors focus:border-pine/50 min-h-[44px]"
          />
        </label>
        <button
          type="submit"
          disabled={busy}
          className="mt-5 h-12 w-full rounded-full bg-pine font-grotesk font-bold text-primary-foreground transition-colors hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
        >
          {busy ? "Проверяем…" : "Войти"}
        </button>
        <button
          type="button"
          onClick={onExit}
          className="mt-3 w-full text-center text-[12.5px] text-muted-foreground hover:text-foreground transition-colors"
        >
          ← Вернуться на витрину
        </button>
        <p className="mt-5 text-center text-[11px] text-muted-foreground/70">
          Пароль по умолчанию задаётся в разделе «Настройки сайта»
        </p>
      </form>
    </div>
  )
}

function AdminShell({ onExit, onLogout }: { onExit: () => void; onLogout: () => void }) {
  const [tab, setTab] = useState<Tab>("orders")
  const [orders, setOrders] = useState<Order[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [zones, setZones] = useState<DeliveryZone[]>([])
  const [upsells, setUpsells] = useState<Upsell[]>([])
  const [settings, setSettings] = useState<Record<string, string>>({})
  const [loaded, setLoaded] = useState(false)
  const ordersRef = useRef(false)

  useEffect(() => {
    let alive = true
    const load = async () => {
      try {
        const [o, p, z, u, s] = await Promise.all([
          fetch("/api/admin/orders", { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)),
          fetch("/api/admin/products", { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)),
          fetch("/api/admin/zones", { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)),
          fetch("/api/admin/upsells", { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)),
          fetch("/api/admin/settings", { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)),
        ])
        if (!alive) return
        if (o) setOrders(o.orders.map(parseOrderRow))
        if (p) setProducts(p.products.map(parseProductRow))
        if (z) setZones(z.zones)
        if (u) setUpsells(u.upsells)
        if (s) setSettings(s.settings)
        setLoaded(true)
      } catch {
        if (alive) toast.error("Не удалось загрузить данные панели")
      }
    }
    void load()
    return () => {
      alive = false
    }
  }, [])

  // Новые заказы: пульс каждые 30 секунд
  useEffect(() => {
    const tick = async () => {
      if (document.visibilityState !== "visible") return
      if (ordersRef.current) return
      ordersRef.current = true
      try {
        const r = await fetch("/api/admin/orders", { cache: "no-store" })
        if (r.ok) {
          const d = await r.json()
          const next = d.orders.map(parseOrderRow) as Order[]
          setOrders((prev) => {
            if (next.length > prev.length) {
              const fresh = next.find((o) => !prev.some((p) => p.id === o.id))
              if (fresh) toast.success(`Новый заказ ${fresh.number} — ${fresh.customerName}`)
            }
            return next
          })
        }
      } catch {}
      ordersRef.current = false
    }
    const id = setInterval(tick, 30000)
    return () => clearInterval(id)
  }, [])

  const notifyStorefront = () => window.dispatchEvent(new Event("nf:data-updated"))

  const tabs: { id: Tab; label: string; icon: React.ReactNode; hint?: string }[] = [
    { id: "orders", label: "Заказы", icon: <ShoppingBag className="h-4 w-4" aria-hidden />, hint: String(orders.filter((o) => o.status === "new").length) },
    { id: "catalog", label: "Каталог", icon: <Package className="h-4 w-4" aria-hidden />, hint: String(products.filter((p) => !p.inStock).length) },
    { id: "zones", label: "Доставка", icon: <Truck className="h-4 w-4" aria-hidden /> },
    { id: "upsells", label: "Допродажи", icon: <Flower2 className="h-4 w-4" aria-hidden /> },
    { id: "settings", label: "Сайт", icon: <Settings className="h-4 w-4" aria-hidden /> },
  ]

  return (
    <div className="min-h-screen bg-linen">
      {/* Верхняя панель */}
      <header className="sticky top-0 z-40 border-b border-border bg-white/95 backdrop-blur-md">
        <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4">
          <div className="flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-pine">
              <LayoutDashboard className="h-5 w-5 text-cream" aria-hidden />
            </span>
            <div>
              <p className="font-grotesk text-[15px] font-bold text-foreground">Nilov Flowers · Админ</p>
              <p className="text-[11.5px] text-muted-foreground">
                {orders.length} заказов · {products.length} букетов · {zones.length} зон
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={onExit}
              className="h-11 rounded-full border border-border bg-white px-4 text-[13px] font-semibold text-foreground hover:bg-accent min-h-[44px]"
            >
              На витрину
            </button>
            <button
              onClick={async () => {
                await fetch("/api/admin/logout", { method: "POST" })
                onLogout()
              }}
              className="grid h-11 w-11 place-items-center rounded-full border border-border bg-white text-muted-foreground hover:bg-powder hover:text-berry min-h-[44px] min-w-[44px]"
              aria-label="Выйти"
              title="Выйти"
            >
              <LogOut className="h-5 w-5" aria-hidden />
            </button>
          </div>
        </div>
        {/* Табы */}
        <nav className="mx-auto max-w-7xl px-4" aria-label="Разделы админки">
          <div className="chips-rail flex gap-1 overflow-x-auto pb-2">
            {tabs.map((t) => (
              <button
                key={t.id}
                onClick={() => setTab(t.id)}
                aria-current={tab === t.id ? "page" : undefined}
                className={`flex h-11 shrink-0 items-center gap-2 rounded-full px-4 font-grotesk text-[13px] font-semibold transition-colors min-h-[44px] ${
                  tab === t.id ? "bg-pine text-primary-foreground" : "bg-linen text-foreground hover:bg-accent"
                }`}
              >
                {t.icon}
                {t.label}
                {t.hint && Number(t.hint) > 0 && (
                  <span
                    className={`grid h-5 min-w-5 place-items-center rounded-full px-1 text-[11px] font-bold tnum ${
                      tab === t.id ? "bg-hit text-pine-deep" : "bg-berry text-white"
                    }`}
                  >
                    {t.hint}
                  </span>
                )}
              </button>
            ))}
          </div>
        </nav>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-6 pb-24">
        {!loaded ? (
          <div className="grid h-64 place-items-center">
            <div className="animate-pulse text-sm text-muted-foreground">Загружаем данные…</div>
          </div>
        ) : (
          <>
            {tab === "orders" && (
              <AdminOrders orders={orders} setOrders={setOrders} settings={settings} products={products} />
            )}
            {tab === "catalog" && (
              <AdminCatalog
                products={products}
                setProducts={setProducts}
                zones={zones}
                onSaved={notifyStorefront}
              />
            )}
            {tab === "zones" && <AdminZones zones={zones} setZones={setZones} onSaved={notifyStorefront} />}
            {tab === "upsells" && (
              <AdminUpsells upsells={upsells} setUpsells={setUpsells} onSaved={notifyStorefront} />
            )}
            {tab === "settings" && (
              <AdminSettings settings={settings} onSaved={notifyStorefront} onRelogin={onLogout} />
            )}
          </>
        )}
      </main>
    </div>
  )
}
