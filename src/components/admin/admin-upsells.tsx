"use client"

import { useState } from "react"
import Image from "next/image"
import { Upload } from "lucide-react"
import { toast } from "sonner"
import type { Upsell } from "@/lib/types"
import { money } from "@/lib/types"

// Допродажи: открытка, Кризал, аквабокс, клубника в шоколаде
export function AdminUpsells({
  upsells,
  setUpsells,
  onSaved,
}: {
  upsells: Upsell[]
  setUpsells: React.Dispatch<React.SetStateAction<Upsell[]>>
  onSaved: () => void
}) {
  const patch = async (id: number, data: Record<string, unknown>) => {
    try {
      const r = await fetch(`/api/admin/upsells/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      })
      if (!r.ok) throw new Error()
      const d = await r.json()
      setUpsells((list) => list.map((u) => (u.id === id ? d.upsell : u)))
      onSaved()
    } catch {
      toast.error("Не удалось сохранить")
    }
  }

  const upload = async (id: number, file: File) => {
    const fd = new FormData()
    fd.append("file", file)
    try {
      const r = await fetch("/api/admin/upload", { method: "POST", body: fd })
      const d = await r.json()
      if (!r.ok) throw new Error(d.error)
      await patch(id, { photo: d.url })
      toast.success("Фото обновлено")
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка загрузки")
    }
  }

  return (
    <div>
      <h2 className="font-display text-xl text-foreground">Допродажи и подарки</h2>
      <p className="mt-0.5 text-[12.5px] text-muted-foreground">
        Эти позиции предлагаются покупателю в корзине — открытка и Кризал бесплатны
      </p>

      <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        {upsells.map((u) => (
          <div key={u.id} className="rounded-2xl bg-white p-4 shadow-sm">
            <div className="flex items-start gap-3.5">
              <label className="group relative h-20 w-16 shrink-0 cursor-pointer overflow-hidden rounded-xl bg-secondary">
                {u.photo ? (
                  <Image src={u.photo} alt={u.name} fill sizes="64px" className="object-cover" />
                ) : (
                  <span className="grid h-full w-full place-items-center text-muted-foreground">
                    <Upload className="h-5 w-5" aria-hidden />
                  </span>
                )}
                <span className="absolute inset-0 grid place-items-center bg-pine-deep/50 opacity-0 transition-opacity group-hover:opacity-100">
                  <Upload className="h-5 w-5 text-white" aria-hidden />
                </span>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  hidden
                  onChange={(e) => e.target.files?.[0] && void upload(u.id, e.target.files[0])}
                  aria-label={`Загрузить фото для ${u.name}`}
                />
              </label>
              <div className="min-w-0 flex-1">
                <input
                  defaultValue={u.name}
                  onBlur={(e) => e.target.value.trim() !== u.name && void patch(u.id, { name: e.target.value.trim() })}
                  className="w-full rounded-lg border border-transparent bg-transparent px-1.5 py-1 font-grotesk text-[14px] font-bold text-foreground outline-none hover:border-border focus:border-pine/50 min-h-[44px]"
                  aria-label={`Название ${u.slug}`}
                />
                <textarea
                  defaultValue={u.description || ""}
                  onBlur={(e) => e.target.value !== (u.description || "") && void patch(u.id, { description: e.target.value })}
                  rows={2}
                  className="mt-1 w-full resize-none rounded-lg border border-transparent bg-transparent px-1.5 py-1 text-[12px] leading-snug text-muted-foreground outline-none hover:border-border focus:border-pine/50"
                  aria-label={`Описание ${u.slug}`}
                />
                <div className="mt-2 flex items-center gap-2">
                  <input
                    defaultValue={u.price}
                    onBlur={(e) => {
                      const n = Number(e.target.value.replace(/\D/g, "")) || 0
                      if (n !== u.price) void patch(u.id, { price: n })
                    }}
                    inputMode="numeric"
                    className="h-11 w-24 rounded-lg border border-input bg-white px-2 text-right font-grotesk text-[13px] font-bold outline-none focus:border-pine/50 tnum min-h-[44px]"
                    aria-label={`Цена ${u.slug}`}
                  />
                  <span className="text-[12px] text-muted-foreground">{u.price === 0 ? "— бесплатно для покупателя" : money(u.price)}</span>
                  <button
                    onClick={() => void patch(u.id, { active: !u.active })}
                    role="switch"
                    aria-checked={u.active}
                    aria-label={`Показывать в корзине: ${u.name}`}
                    className={`ml-auto relative h-7 w-12 rounded-full transition-colors min-h-[44px] min-w-[44px] ${
                      u.active ? "bg-grass" : "bg-muted-foreground/30"
                    }`}
                  >
                    <span
                      className={`absolute top-1/2 h-5 w-5 -translate-y-1/2 rounded-full bg-white shadow transition-all ${
                        u.active ? "left-6" : "left-1"
                      }`}
                    />
                  </button>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
