"use client"

import { useEffect, useMemo, useState } from "react"
import Image from "next/image"
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from "@dnd-kit/core"
import { Clock, Gift, MapPin, MessageCircle, Phone, Printer, Table2, Trash2, User, Columns3 } from "lucide-react"
import { toast } from "sonner"
import type { Order, OrderStatus, ShopSettings } from "@/lib/types"
import { ORDER_STATUSES, money, waLink } from "@/lib/types"

const STATUS_STYLE: Record<OrderStatus, { dot: string; chip: string; ring: string }> = {
  new: { dot: "bg-berry", chip: "bg-powder text-berry", ring: "ring-berry/25" },
  photo: { dot: "bg-hit", chip: "bg-hit/20 text-[#8a6a00]", ring: "ring-hit/30" },
  assembly: { dot: "bg-pine", chip: "bg-pine/10 text-pine", ring: "ring-pine/25" },
  courier: { dot: "bg-grass", chip: "bg-grass/10 text-grass", ring: "ring-grass/25" },
  done: { dot: "bg-grass/60", chip: "bg-grass/5 text-grass/80", ring: "ring-grass/15" },
  canceled: { dot: "bg-muted-foreground", chip: "bg-secondary text-muted-foreground", ring: "ring-border" },
}

export function AdminOrders({
  orders,
  setOrders,
  settings,
}: {
  orders: Order[]
  setOrders: React.Dispatch<React.SetStateAction<Order[]>>
  settings: Record<string, string>
}) {
  const [view, setView] = useState<"kanban" | "table">("kanban")
  const [dragging, setDragging] = useState<Order | null>(null)

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }))

  // Карточки шлют событие после правки статуса — перезагружаем список
  useEffect(() => {
    const reload = async () => {
      try {
        const r = await fetch("/api/admin/orders", { cache: "no-store" })
        if (r.ok) {
          const d = await r.json()
          setOrders(d.orders.map(parseOrderRow))
        }
      } catch {}
    }
    window.addEventListener("nf:admin-orders-changed", reload)
    return () => window.removeEventListener("nf:admin-orders-changed", reload)
  }, [setOrders])

  const stats = useMemo(() => {
    const today = new Date().toDateString()
    const todayOrders = orders.filter((o) => new Date(o.createdAt).toDateString() === today)
    return {
      today: todayOrders.length,
      revenue: todayOrders.filter((o) => o.status !== "canceled").reduce((s, o) => s + o.total, 0),
      active: orders.filter((o) => ["new", "photo", "assembly", "courier"].includes(o.status)).length,
    }
  }, [orders])

  const patchStatus = async (orderId: number, status: OrderStatus) => {
    const prev = orders
    setOrders((list) => list.map((o) => (o.id === orderId ? { ...o, status } : o)))
    try {
      const r = await fetch(`/api/admin/orders/${orderId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      })
      if (!r.ok) throw new Error()
      const label = ORDER_STATUSES.find((s) => s.id === status)?.label
      toast.success(`Статус: ${label}`)
    } catch {
      setOrders(prev)
      toast.error("Не удалось сохранить статус — вернули как было")
    }
  }

  const onDragStart = (e: DragStartEvent) => {
    const order = orders.find((o) => o.id === e.active.id)
    setDragging(order || null)
  }

  const onDragEnd = (e: DragEndEvent) => {
    setDragging(null)
    const orderId = Number(e.active.id)
    const column = String(e.over?.id || "")
    if (!column.startsWith("col:")) return
    const status = column.slice(4) as OrderStatus
    const order = orders.find((o) => o.id === orderId)
    if (!order || order.status === status) return
    void patchStatus(orderId, status)
  }

  return (
    <div>
      {/* Статистика дня */}
      <div className="grid grid-cols-3 gap-3">
        {[
          { label: "Заказов сегодня", value: String(stats.today) },
          { label: "Выручка сегодня", value: money(stats.revenue) },
          { label: "В работе", value: String(stats.active) },
        ].map((s) => (
          <div key={s.label} className="rounded-2xl bg-white p-4 shadow-sm">
            <p className="text-[11.5px] text-muted-foreground">{s.label}</p>
            <p className="mt-1 font-grotesk text-xl font-extrabold text-foreground tnum">{s.value}</p>
          </div>
        ))}
      </div>

      <div className="mt-5 flex items-center justify-between gap-3">
        <h2 className="font-display text-xl text-foreground">Рабочий стол сборки</h2>
        <div className="flex rounded-full border border-border bg-white p-1">
          <button
            onClick={() => setView("kanban")}
            aria-pressed={view === "kanban"}
            className={`flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[12.5px] font-semibold min-h-[44px] ${
              view === "kanban" ? "bg-pine text-primary-foreground" : "text-muted-foreground"
            }`}
          >
            <Columns3 className="h-4 w-4" aria-hidden />
            Канбан
          </button>
          <button
            onClick={() => setView("table")}
            aria-pressed={view === "table"}
            className={`flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[12.5px] font-semibold min-h-[44px] ${
              view === "table" ? "bg-pine text-primary-foreground" : "text-muted-foreground"
            }`}
          >
            <Table2 className="h-4 w-4" aria-hidden />
            Таблица
          </button>
        </div>
      </div>

      {view === "kanban" ? (
        <DndContext sensors={sensors} onDragStart={onDragStart} onDragEnd={onDragEnd}>
          <div className="chips-rail mt-4 flex gap-3 overflow-x-auto pb-3 lg:grid lg:grid-cols-6 lg:overflow-visible">
            {ORDER_STATUSES.map((st) => (
              <KanbanColumn key={st.id} status={st} orders={orders.filter((o) => o.status === st.id)} />
            ))}
          </div>
          <DragOverlay dropAnimation={{ duration: 220, easing: "cubic-bezier(.16,1,.3,1)" }}>
            {dragging && <OrderCard order={dragging} settings={null} dragging />}
          </DragOverlay>
        </DndContext>
      ) : (
        <OrdersTable orders={orders} settings={settings} onStatus={patchStatus} />
      )}
    </div>
  )
}

function KanbanColumn({ status, orders }: { status: (typeof ORDER_STATUSES)[number]; orders: Order[] }) {
  const { setNodeRef, isOver } = useDroppable({ id: `col:${status.id}` })
  const style = STATUS_STYLE[status.id]
  return (
    <section
      ref={setNodeRef}
      className={`w-[290px] shrink-0 rounded-2xl bg-white/70 p-2.5 ring-1 transition-all lg:w-auto ${
        isOver ? `ring-2 ${style.ring} bg-white` : "ring-border"
      }`}
      aria-label={status.label}
    >
      <header className="flex items-center justify-between gap-2 px-1.5 pb-2.5 pt-1">
        <span className="flex items-center gap-2 text-[12.5px] font-bold text-foreground">
          <span className={`h-2 w-2 rounded-full ${style.dot}`} aria-hidden />
          {status.label}
        </span>
        <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-bold text-muted-foreground tnum">
          {orders.length}
        </span>
      </header>
      <div className="flex max-h-[62vh] flex-col gap-2.5 overflow-y-auto nice-scroll p-1">
        {orders.length === 0 && (
          <p className="rounded-xl border border-dashed border-border px-3 py-6 text-center text-[11.5px] text-muted-foreground">
            {status.hint}
          </p>
        )}
        {orders.map((o) => (
          <OrderCard key={o.id} order={o} settings={null} />
        ))}
      </div>
    </section>
  )
}

function OrderCard({
  order,
  settings,
  dragging,
}: {
  order: Order
  settings: Record<string, string> | null
  dragging?: boolean
}) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: order.id })
  const style = STATUS_STYLE[order.status]
  const [printNow, setPrintNow] = useState(false)
  const patchStatus = usePatchStatus()

  if (isDragging && !dragging) return null

  return (
    <>
      <article
        ref={setNodeRef}
        {...attributes}
        {...listeners}
        className={`cursor-grab touch-none select-none rounded-xl border border-border bg-white p-3 shadow-sm transition-shadow hover:shadow-md ${
          dragging ? "rotate-2 scale-[1.02] shadow-xl" : ""
        }`}
        aria-label={`Заказ ${order.number}`}
      >
        <header className="flex items-center justify-between gap-2">
          <span className="font-grotesk text-[13px] font-extrabold text-foreground tnum">{order.number}</span>
          <span className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold ${style.chip}`}>
            {money(order.total)}
          </span>
        </header>

        <div className="mt-2 space-y-1 text-[12px] text-muted-foreground">
          <p className="flex items-center gap-1.5">
            <User className="h-3.5 w-3.5 shrink-0" aria-hidden />
            <span className="truncate">{order.customerName}</span>
          </p>
          {order.pickup ? (
            <p className="flex items-center gap-1.5">
              <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden />
              Самовывоз — студия
            </p>
          ) : (
            <>
              {order.deliveryDate && (
                <p className="flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5 shrink-0" aria-hidden />
                  <span className="tnum">
                    {formatDate(order.deliveryDate)} · {order.deliverySlot}
                  </span>
                </p>
              )}
              <p className="flex items-center gap-1.5">
                <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden />
                <span className="truncate">{order.address || order.zone?.name || "адрес уточнит флорист"}</span>
              </p>
            </>
          )}
        </div>

        <ul className="mt-2 space-y-1">
          {order.items.map((it) => (
            <li key={it.id} className="flex items-center gap-2 text-[12px]">
              <span className="relative h-9 w-7 shrink-0 overflow-hidden rounded-md bg-secondary">
                {it.photo && <Image src={it.photo} alt="" fill sizes="28px" className="object-cover" />}
              </span>
              <span className="min-w-0 flex-1 truncate text-foreground">{it.title}</span>
              <span className="text-muted-foreground tnum">×{it.qty}</span>
            </li>
          ))}
        </ul>

        {order.cardText && (
          <p className="mt-2 rounded-lg bg-powder/40 px-2.5 py-1.5 text-[11.5px] italic leading-snug text-berry">
            <Gift className="mr-1 inline h-3 w-3" aria-hidden />
            {order.cardText.slice(0, 80)}
            {order.cardText.length > 80 ? "…" : ""}
          </p>
        )}

        <div className="mt-2.5 flex gap-1.5">
          <a
            href={waLink(order.customerPhone, `Здравствуйте! Ваш заказ ${order.number} в Nilov Flowers:`)}
            target="_blank"
            rel="noreferrer"
            onClick={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
            aria-label={`Написать в WhatsApp по заказу ${order.number}`}
            className="grid h-9 w-9 place-items-center rounded-full bg-grass/10 text-grass hover:bg-grass/20 min-h-[44px] min-w-[44px]"
          >
            <MessageCircle className="h-4 w-4" aria-hidden />
          </a>
          <a
            href={`tel:${order.customerPhone.replace(/[^\d+]/g, "")}`}
            onClick={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
            aria-label="Позвонить"
            className="grid h-9 w-9 place-items-center rounded-full bg-pine/10 text-pine hover:bg-pine/20 min-h-[44px] min-w-[44px]"
          >
            <Phone className="h-4 w-4" aria-hidden />
          </a>
          <button
            onClick={(e) => {
              e.stopPropagation()
              setPrintNow(true)
            }}
            onPointerDown={(e) => e.stopPropagation()}
            aria-label="Печать записки к букету"
            className="grid h-9 w-9 place-items-center rounded-full bg-secondary text-foreground hover:bg-accent min-h-[44px] min-w-[44px]"
          >
            <Printer className="h-4 w-4" aria-hidden />
          </button>
          {order.status !== "canceled" && order.status !== "done" && (
            <button
              onClick={(e) => {
                e.stopPropagation()
                void patchStatus(order, "canceled")
              }}
              onPointerDown={(e) => e.stopPropagation()}
              aria-label="Отменить заказ"
              title="Отменить заказ"
              className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-powder hover:text-berry min-h-[44px] min-w-[44px]"
            >
              <Trash2 className="h-4 w-4" aria-hidden />
            </button>
          )}
        </div>

        {order.status === "canceled" && (
          <button
            onClick={(e) => {
              e.stopPropagation()
              void patchStatus(order, "new")
            }}
            onPointerDown={(e) => e.stopPropagation()}
            className="mt-2 w-full rounded-full border border-border bg-white px-3 py-1.5 text-[11px] font-semibold text-muted-foreground hover:text-foreground min-h-[44px]"
          >
            ↩ Вернуть в «Новые»
          </button>
        )}
      </article>

      {printNow && <PrintNote order={order} onDone={() => setPrintNow(false)} />}
    </>
  )
}

// Хук обновления статуса из карточки: PATCH + событие на перезагрузку списка
function usePatchStatus() {
  return async (order: Order, status: OrderStatus) => {
    try {
      const r = await fetch(`/api/admin/orders/${order.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      })
      if (!r.ok) throw new Error()
      toast.success(`Заказ ${order.number}: ${ORDER_STATUSES.find((s) => s.id === status)?.label}`)
      window.dispatchEvent(new Event("nf:admin-orders-changed"))
    } catch {
      toast.error("Не удалось изменить статус")
    }
  }
}

function OrdersTable({
  orders,
  settings,
  onStatus,
}: {
  orders: Order[]
  settings: Record<string, string>
  onStatus: (id: number, status: OrderStatus) => void
}) {
  const [printOrder, setPrintOrder] = useState<Order | null>(null)
  return (
    <>
      <div className="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm nice-scroll">
        <table className="w-full min-w-[860px] text-left text-[13px]">
          <thead>
            <tr className="border-b border-border text-[11px] uppercase tracking-wider text-muted-foreground">
              <th className="px-4 py-3 font-semibold">Заказ</th>
              <th className="px-4 py-3 font-semibold">Клиент</th>
              <th className="px-4 py-3 font-semibold">Состав</th>
              <th className="px-4 py-3 font-semibold">Доставка</th>
              <th className="px-4 py-3 font-semibold">Сумма</th>
              <th className="px-4 py-3 font-semibold">Статус</th>
              <th className="px-4 py-3 font-semibold"></th>
            </tr>
          </thead>
          <tbody>
            {orders.map((o) => (
              <tr key={o.id} className="border-b border-border/60 hover:bg-accent/40">
                <td className="px-4 py-3 font-grotesk font-bold tnum">
                  {o.number}
                  <span className="block text-[11px] font-normal text-muted-foreground">
                    {new Date(o.createdAt).toLocaleString("ru-RU", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <span className="block font-medium text-foreground">{o.customerName}</span>
                  <a href={`tel:${o.customerPhone.replace(/[^\d+]/g, "")}`} className="text-[12px] text-pine tnum">
                    {o.customerPhone}
                  </a>
                </td>
                <td className="max-w-[220px] px-4 py-3">
                  <span className="block truncate text-muted-foreground">
                    {o.items.map((i) => `${i.title}×${i.qty}`).join(", ")}
                  </span>
                  {o.cardText && (
                    <span className="block truncate text-[11.5px] italic text-berry">«{o.cardText}»</span>
                  )}
                </td>
                <td className="max-w-[200px] px-4 py-3 text-muted-foreground">
                  {o.pickup ? "Самовывоз" : (
                    <>
                      <span className="block truncate">{o.address || o.zone?.name || "уточнить адрес"}</span>
                      <span className="block text-[11.5px] tnum">
                        {o.deliveryDate ? `${formatDate(o.deliveryDate)} · ${o.deliverySlot}` : "—"}
                      </span>
                    </>
                  )}
                </td>
                <td className="px-4 py-3 font-grotesk font-bold tnum">{money(o.total)}</td>
                <td className="px-4 py-3">
                  <select
                    value={o.status}
                    onChange={(e) => onStatus(o.id, e.target.value as OrderStatus)}
                    aria-label={`Статус заказа ${o.number}`}
                    className="h-9 rounded-full border border-input bg-white px-3 text-[12px] font-semibold outline-none focus:border-pine/50 min-h-[44px]"
                  >
                    {ORDER_STATUSES.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.label}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-1.5">
                    <a
                      href={waLink(o.customerPhone, `Здравствуйте! Ваш заказ ${o.number} в Nilov Flowers:`)}
                      target="_blank"
                      rel="noreferrer"
                      aria-label="WhatsApp"
                      className="grid h-9 w-9 place-items-center rounded-full bg-grass/10 text-grass min-h-[44px] min-w-[44px]"
                    >
                      <MessageCircle className="h-4 w-4" aria-hidden />
                    </a>
                    <button
                      onClick={() => setPrintOrder(o)}
                      aria-label="Печать записки"
                      className="grid h-9 w-9 place-items-center rounded-full bg-secondary text-foreground min-h-[44px] min-w-[44px]"
                    >
                      <Printer className="h-4 w-4" aria-hidden />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {printOrder && <PrintNote order={printOrder} onDone={() => setPrintOrder(null)} />}
    </>
  )
}

// Печатная записка флористу (А5): состав, адрес, время, крупное пожелание
export function PrintNote({ order, onDone }: { order: Order; onDone: () => void }) {
  const flowers = order.items.filter((i) => i.productId)
  const extras = order.items.filter((i) => !i.productId)

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-pine-deep/50 p-4" role="dialog" aria-modal="true">
      <div className="flex max-h-[90dvh] flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
          <p className="font-grotesk font-bold text-foreground">Записка к букету · {order.number}</p>
          <button
            onClick={onDone}
            aria-label="Закрыть"
            className="grid h-11 w-11 place-items-center rounded-full hover:bg-accent min-h-[44px] min-w-[44px]"
          >
            ✕
          </button>
        </div>
        <div id="print-note" className="w-[340px] overflow-y-auto p-6 nice-scroll sm:w-[420px]">
          <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#143C2B]">Nilov Flowers</p>
          <p className="mt-1 font-grotesk text-lg font-extrabold text-[#18181B] tnum">Заказ {order.number}</p>

          <div className="mt-4 border-t border-[#E8E5DD] pt-4">
            <p className="text-[10px] font-bold uppercase tracking-wider text-[#71717A]">Состав</p>
            <ul className="mt-1.5 space-y-1">
              {flowers.map((i) => (
                <li key={i.id} className="text-[13px] font-medium text-[#18181B]">
                  {i.title} — {i.qty} шт.
                </li>
              ))}
            </ul>
            {extras.length > 0 && (
              <ul className="mt-2 space-y-0.5">
                {extras.map((i) => (
                  <li key={i.id} className="text-[12px] text-[#52525B]">
                    + {i.title}
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="mt-4 border-t border-[#E8E5DD] pt-4">
            <p className="text-[10px] font-bold uppercase tracking-wider text-[#71717A]">Доставка</p>
            {order.pickup ? (
              <p className="mt-1.5 text-[13px] font-semibold text-[#18181B]">Самовывоз — студия на Мойке</p>
            ) : (
              <div className="mt-1.5 space-y-0.5 text-[13px] text-[#18181B]">
                <p className="font-semibold">{order.address || `адрес узнает флорист (${order.recipientPhone})`}</p>
                <p>
                  {order.zone?.name} · {order.deliveryDate ? formatDate(order.deliveryDate) : "дата уточняется"} ·{" "}
                  {order.deliverySlot}
                </p>
              </div>
            )}
            <div className="mt-1.5 space-y-0.5 text-[12.5px] text-[#52525B]">
              <p>Получатель: {order.recipientName || order.customerName}</p>
              <p className="tnum">{order.recipientPhone || order.customerPhone}</p>
              {order.surprise && <p className="font-semibold text-[#B35663]">СЮРПРИЗ — отправителя не называть</p>}
            </div>
          </div>

          <div className="mt-4 border-t border-[#E8E5DD] pt-4">
            <p className="text-[10px] font-bold uppercase tracking-wider text-[#71717A]">Пожелание для открытки</p>
            <p className="mt-2 min-h-[80px] rounded-xl bg-[#F4DEE3]/40 p-3 font-display text-[17px] leading-relaxed text-[#18181B]">
              {order.cardText || "«без текста — открытка чистая»"}
            </p>
          </div>

          <p className="mt-4 border-t border-[#E8E5DD] pt-3 text-[10px] text-[#71717A]">
            {order.paymentMethod === "cash" ? "Оплата при получении" : "Оплачено онлайн"} · итог {money(order.total)} ·
            печать {new Date().toLocaleDateString("ru-RU")}
          </p>
        </div>
        <div className="border-t border-border px-5 py-3.5">
          <button
            onClick={() => window.print()}
            className="h-12 w-full rounded-full bg-pine font-grotesk font-bold text-primary-foreground min-h-[44px]"
          >
            Распечатать (А5)
          </button>
        </div>
      </div>
    </div>
  )
}

function formatDate(iso: string) {
  const d = new Date(iso + "T00:00:00")
  return d.toLocaleDateString("ru-RU", { day: "numeric", month: "long" })
}
