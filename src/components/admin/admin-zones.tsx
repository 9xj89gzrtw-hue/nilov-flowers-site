"use client"

import { useState } from "react"
import { MapPin, Plus, Trash2 } from "lucide-react"
import { toast } from "sonner"
import type { DeliveryZone } from "@/lib/types"
import { money } from "@/lib/types"

// Доставка по СПб: таблица районов с ценой и сроками
export function AdminZones({
  zones,
  setZones,
  onSaved,
}: {
  zones: DeliveryZone[]
  setZones: React.Dispatch<React.SetStateAction<DeliveryZone[]>>
  onSaved: () => void
}) {
  const [newName, setNewName] = useState("")
  const [newPrice, setNewPrice] = useState("")
  const [newEta, setNewEta] = useState("")

  const patch = async (id: number, data: Record<string, unknown>) => {
    try {
      const r = await fetch(`/api/admin/zones/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      })
      if (!r.ok) throw new Error()
      const d = await r.json()
      setZones((list) => list.map((z) => (z.id === id ? d.zone : z)))
      onSaved()
    } catch {
      toast.error("Не удалось сохранить зону")
    }
  }

  const add = async () => {
    if (newName.trim().length < 2) {
      toast.error("Укажите название района")
      return
    }
    try {
      const r = await fetch("/api/admin/zones", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: newName, price: Number(newPrice.replace(/\D/g, "")) || 0, eta: newEta }),
      })
      const d = await r.json()
      if (!r.ok) throw new Error(d.error)
      setZones((list) => [...list, d.zone].sort((a, b) => a.sort - b.sort))
      setNewName("")
      setNewPrice("")
      setNewEta("")
      onSaved()
      toast.success(`Зона «${d.zone.name}» добавлена`)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка")
    }
  }

  const remove = async (z: DeliveryZone) => {
    if (!confirm(`Удалить зону «${z.name}»?`)) return
    try {
      const r = await fetch(`/api/admin/zones/${z.id}`, { method: "DELETE" })
      const d = await r.json()
      if (!r.ok) throw new Error(d.error || "Ошибка")
      setZones((list) => list.filter((x) => x.id !== z.id))
      onSaved()
      toast.success("Зона удалена")
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка")
    }
  }

  const cellCls =
    "h-11 w-full rounded-lg border border-transparent bg-transparent px-2 text-[13px] outline-none transition-colors hover:border-border focus:border-pine/50 focus:bg-white min-h-[44px]"

  return (
    <div>
      <h2 className="font-display text-xl text-foreground">Доставка по СПб</h2>
      <p className="mt-0.5 text-[12.5px] text-muted-foreground">
        Районы из этой таблицы сразу видны на витрине и в форме оформления заказа
      </p>

      <div className="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm nice-scroll">
        <table className="w-full min-w-[620px] text-left text-[13px]">
          <thead>
            <tr className="border-b border-border text-[11px] uppercase tracking-wider text-muted-foreground">
              <th className="px-4 py-3 font-semibold">Район</th>
              <th className="px-4 py-3 font-semibold">Цена, ₽</th>
              <th className="px-4 py-3 font-semibold">Срок</th>
              <th className="px-4 py-3 font-semibold">Примечание (бесплатно от…)</th>
              <th className="px-4 py-3 font-semibold"></th>
            </tr>
          </thead>
          <tbody>
            {zones.map((z) => (
              <tr key={z.id} className="border-b border-border/50 hover:bg-accent/30">
                <td className="px-4 py-2">
                  <span className="flex items-center gap-2">
                    <MapPin className="h-3.5 w-3.5 shrink-0 text-pine" aria-hidden />
                    <input
                      defaultValue={z.name}
                      onBlur={(e) => e.target.value.trim() !== z.name && void patch(z.id, { name: e.target.value.trim() })}
                      className={cellCls}
                      aria-label={`Название зоны ${z.id}`}
                    />
                  </span>
                </td>
                <td className="w-28 px-4 py-2">
                  <input
                    defaultValue={z.price}
                    onBlur={(e) => {
                      const n = Number(e.target.value.replace(/\D/g, "")) || 0
                      if (n !== z.price) void patch(z.id, { price: n })
                    }}
                    inputMode="numeric"
                    className={`${cellCls} font-grotesk font-bold text-right tnum`}
                    aria-label={`Цена зоны ${z.id}`}
                  />
                </td>
                <td className="w-36 px-4 py-2">
                  <input
                    defaultValue={z.eta || ""}
                    onBlur={(e) => e.target.value !== (z.eta || "") && void patch(z.id, { eta: e.target.value })}
                    className={cellCls}
                    placeholder="90–120 мин"
                    aria-label={`Срок зоны ${z.id}`}
                  />
                </td>
                <td className="px-4 py-2">
                  <input
                    defaultValue={z.note || ""}
                    onBlur={(e) => e.target.value !== (z.note || "") && void patch(z.id, { note: e.target.value })}
                    className={cellCls}
                    placeholder="бесплатно от 3 000 ₽"
                    aria-label={`Примечание зоны ${z.id}`}
                  />
                </td>
                <td className="px-4 py-2 text-right">
                  <button
                    onClick={() => void remove(z)}
                    aria-label={`Удалить зону ${z.name}`}
                    className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-powder hover:text-berry min-h-[44px] min-w-[44px]"
                  >
                    <Trash2 className="h-4 w-4" aria-hidden />
                  </button>
                </td>
              </tr>
            ))}
            <tr className="bg-linen/60">
              <td className="px-4 py-2">
                <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="Новый район…" className={cellCls} aria-label="Название новой зоны" />
              </td>
              <td className="px-4 py-2">
                <input value={newPrice} onChange={(e) => setNewPrice(e.target.value.replace(/[^\d]/g, ""))} placeholder="450" inputMode="numeric" className={`${cellCls} font-grotesk font-bold text-right tnum`} aria-label="Цена новой зоны" />
              </td>
              <td className="px-4 py-2">
                <input value={newEta} onChange={(e) => setNewEta(e.target.value)} placeholder="120–160 мин" className={cellCls} aria-label="Срок новой зоны" />
              </td>
              <td className="px-4 py-2" colSpan={2}>
                <button onClick={() => void add()} className="inline-flex h-11 items-center gap-1.5 rounded-full bg-pine px-4 font-grotesk text-[12.5px] font-bold text-primary-foreground min-h-[44px]">
                  <Plus className="h-4 w-4" aria-hidden />
                  Добавить
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p className="mt-3 text-[12px] text-muted-foreground">
        Правки сохраняются при выходе из поля. Текущий порог бесплатной доставки (по всей корзине) настраивается во
        вкладке «Сайт» — сейчас {money(5000)}.
      </p>
    </div>
  )
}
