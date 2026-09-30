"use client"

import { useRef, useState } from "react"
import Image from "next/image"
import { Check, Eye, EyeOff, GripVertical, ImagePlus, Pencil, Plus, Star, Trash2, Upload, X } from "lucide-react"
import { toast } from "sonner"
import type { Product } from "@/lib/types"
import { money, parseProductRow } from "@/lib/types"

// Каталог букетов: мгновенный тумблер наличия, инлайн-цена, полная форма
export function AdminCatalog({
  products,
  setProducts,
  zones,
  onSaved,
}: {
  products: Product[]
  setProducts: React.Dispatch<React.SetStateAction<Product[]>>
  zones: { id: number; name: string }[]
  onSaved: () => void
}) {
  const [editing, setEditing] = useState<Product | "new" | null>(null)

  const patch = async (id: number, data: Record<string, unknown>, okMsg?: string) => {
    try {
      const r = await fetch(`/api/admin/products/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      })
      if (!r.ok) {
        const d = await r.json().catch(() => ({}))
        throw new Error(d.error || "Ошибка сохранения")
      }
      const d = await r.json()
      setProducts((list) => list.map((p) => (p.id === id ? parseProductRow(d.product) : p)))
      onSaved()
      if (okMsg) toast.success(okMsg)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка")
    }
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-xl text-foreground">Каталог букетов</h2>
          <p className="mt-0.5 text-[12.5px] text-muted-foreground">
            Тумблер «В наличии» мгновенно убирает букет с витрины · клик по цене — быстрое редактирование
          </p>
        </div>
        <button
          onClick={() => setEditing("new")}
          className="inline-flex h-11 items-center gap-2 rounded-full bg-pine px-5 font-grotesk text-[13px] font-bold text-primary-foreground min-h-[44px]"
        >
          <Plus className="h-4 w-4" aria-hidden />
          Новый букет
        </button>
      </div>

      <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm">
        <table className="w-full text-left text-[13px]">
          <thead>
            <tr className="border-b border-border text-[11px] uppercase tracking-wider text-muted-foreground">
              <th className="px-3 py-3 font-semibold">Букет</th>
              <th className="px-3 py-3 font-semibold">Цена (клик)</th>
              <th className="px-3 py-3 font-semibold">Старая цена</th>
              <th className="px-3 py-3 font-semibold">В наличии</th>
              <th className="px-3 py-3 font-semibold">Витрина</th>
              <th className="px-3 py-3 font-semibold">Хит</th>
              <th className="px-3 py-3 font-semibold"></th>
            </tr>
          </thead>
          <tbody>
            {products.map((p) => (
              <ProductRow key={p.id} product={p} patch={patch} onEdit={() => setEditing(p)} />
            ))}
          </tbody>
        </table>
      </div>

      {editing && (
        <ProductForm
          product={editing === "new" ? null : editing}
          onClose={() => setEditing(null)}
          onCreated={(p) => {
            setProducts((list) => [...list, p].sort((a, b) => a.sort - b.sort))
            setEditing(null)
            onSaved()
            toast.success(`«${p.name}» добавлен в каталог`)
          }}
          onUpdated={(p) => {
            setProducts((list) => list.map((x) => (x.id === p.id ? p : x)))
            setEditing(null)
            onSaved()
            toast.success(`«${p.name}» сохранён`)
          }}
        />
      )}
    </div>
  )
}

function ProductRow({
  product: p,
  patch,
  onEdit,
}: {
  product: Product
  patch: (id: number, data: Record<string, unknown>, okMsg?: string) => Promise<void>
  onEdit: () => void
}) {
  const [priceEdit, setPriceEdit] = useState(false)
  const [price, setPrice] = useState(String(p.price))

  const savePrice = () => {
    const n = Math.round(Number(price.replace(/\D/g, "")) || 0)
    setPriceEdit(false)
    if (n !== p.price && n > 0) {
      setPrice(String(n))
      void patch(p.id, { price: n }, `«${p.name}»: цена ${money(n)}`)
    } else {
      setPrice(String(p.price))
    }
  }

  return (
    <tr className={`border-b border-border/50 ${!p.inStock ? "bg-powder/15" : ""} hover:bg-accent/30`}>
      <td className="px-3 py-2.5">
        <div className="flex items-center gap-3">
          <span className="relative h-12 w-9 shrink-0 overflow-hidden rounded-md bg-secondary">
            {p.photos[0] && <Image src={p.photos[0]} alt="" fill sizes="36px" className="object-cover" />}
          </span>
          <span className="min-w-0">
            <span className="block max-w-[220px] truncate font-medium text-foreground">{p.name}</span>
            <span className="block max-w-[220px] truncate text-[11.5px] text-muted-foreground">
              {p.line === "classic" ? "классика" : "премиум"} · {p.tags.slice(0, 3).join(", ") || "без тегов"}
            </span>
          </span>
        </div>
      </td>
      <td className="px-3 py-2.5">
        {priceEdit ? (
          <span className="flex items-center gap-1">
            <input
              value={price}
              onChange={(e) => setPrice(e.target.value.replace(/[^\d]/g, ""))}
              onKeyDown={(e) => e.key === "Enter" && savePrice()}
              onBlur={savePrice}
              autoFocus
              inputMode="numeric"
              aria-label="Новая цена"
              className="h-9 w-24 rounded-lg border border-pine/50 px-2 text-right font-grotesk font-bold outline-none tnum min-h-[44px]"
            />
            <Check className="h-4 w-4 text-grass" aria-hidden />
          </span>
        ) : (
          <button
            onClick={() => setPriceEdit(true)}
            title="Клик — быстрая правка цены"
            className="rounded-lg px-2 py-1 font-grotesk font-bold text-foreground hover:bg-accent tnum min-h-[44px]"
          >
            {money(p.price)}
          </button>
        )}
      </td>
      <td className="px-3 py-2.5 text-muted-foreground tnum">{p.oldPrice ? money(p.oldPrice) : "—"}</td>
      <td className="px-3 py-2.5">
        <button
          onClick={() => void patch(p.id, { inStock: !p.inStock }, p.inStock ? `«${p.name}» скрыт с витрины` : `«${p.name}» снова в наличии`)}
          role="switch"
          aria-checked={p.inStock}
          aria-label={`Наличие: ${p.name}`}
          className={`relative h-7 w-12 rounded-full transition-colors min-h-[44px] min-w-[44px] ${p.inStock ? "bg-grass" : "bg-muted-foreground/30"}`}
        >
          <span
            className={`absolute top-1/2 h-5 w-5 -translate-y-1/2 rounded-full bg-white shadow transition-all ${
              p.inStock ? "left-6" : "left-1"
            }`}
          />
        </button>
      </td>
      <td className="px-3 py-2.5">
        <button
          onClick={() => void patch(p.id, { visible: !p.visible })}
          role="switch"
          aria-checked={p.visible}
          aria-label={`Показывать на витрине: ${p.name}`}
          title={p.visible ? "Показывается на витрине" : "Скрыт с витрины полностью"}
          className={`grid h-9 w-9 place-items-center rounded-full min-h-[44px] min-w-[44px] ${
            p.visible ? "bg-pine/10 text-pine" : "bg-secondary text-muted-foreground"
          }`}
        >
          {p.visible ? <Eye className="h-4 w-4" aria-hidden /> : <EyeOff className="h-4 w-4" aria-hidden />}
        </button>
      </td>
      <td className="px-3 py-2.5">
        <button
          onClick={() => void patch(p.id, { popular: !p.popular })}
          role="switch"
          aria-checked={p.popular}
          aria-label={`Популярный: ${p.name}`}
          className={`grid h-9 w-9 place-items-center rounded-full min-h-[44px] min-w-[44px] ${
            p.popular ? "bg-hit/25 text-[#8a6a00]" : "bg-secondary text-muted-foreground"
          }`}
        >
          <Star className="h-4 w-4" aria-hidden />
        </button>
      </td>
      <td className="px-3 py-2.5 text-right">
        <button
          onClick={onEdit}
          aria-label={`Редактировать ${p.name}`}
          className="grid h-9 w-9 place-items-center rounded-full bg-secondary text-foreground hover:bg-accent min-h-[44px] min-w-[44px]"
        >
          <Pencil className="h-4 w-4" aria-hidden />
        </button>
      </td>
    </tr>
  )
}

function ProductForm({
  product,
  onClose,
  onCreated,
  onUpdated,
}: {
  product: Product | null
  onClose: () => void
  onCreated: (p: Product) => void
  onUpdated: (p: Product) => void
}) {
  const [name, setName] = useState(product?.name || "")
  const [sub, setSub] = useState(product?.sub || "")
  const [price, setPrice] = useState(String(product?.price ?? ""))
  const [oldPrice, setOldPrice] = useState(String(product?.oldPrice ?? ""))
  const [size, setSize] = useState(product?.size || "")
  const [badge, setBadge] = useState(product?.badge || "")
  const [composition, setComposition] = useState((product?.composition || []).join("\n"))
  const [tags, setTags] = useState((product?.tags || []).join(", "))
  const [description, setDescription] = useState(product?.description || "")
  const [photos, setPhotos] = useState<string[]>(product?.photos || [])
  const [line, setLine] = useState(product?.line || "premium")
  const [busy, setBusy] = useState(false)
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const upload = async (files: FileList | null) => {
    if (!files?.length) return
    setUploading(true)
    try {
      for (const file of Array.from(files).slice(0, 5)) {
        const fd = new FormData()
        fd.append("file", file)
        const r = await fetch("/api/admin/upload", { method: "POST", body: fd })
        const d = await r.json()
        if (!r.ok) throw new Error(d.error || "Ошибка загрузки")
        setPhotos((prev) => [...prev, d.url])
      }
      toast.success("Фото загружено")
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка загрузки")
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ""
    }
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    try {
      const body = {
        name,
        sub,
        price: Number(price.replace(/\D/g, "")) || 0,
        oldPrice: oldPrice ? Number(oldPrice.replace(/\D/g, "")) : null,
        size,
        badge,
        composition: composition.split("\n").map((s) => s.trim()).filter(Boolean),
        tags: tags.split(",").map((s) => s.trim().toLowerCase()).filter(Boolean),
        description,
        photos,
        line,
      }
      const r = await fetch(product ? `/api/admin/products/${product.id}` : "/api/admin/products", {
        method: product ? "PATCH" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      })
      const d = await r.json()
      if (!r.ok) throw new Error(d.error || "Ошибка сохранения")
      if (product) {
        onUpdated(parseProductRow(d.product))
      } else {
        onCreated(d.product)
      }
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка")
    } finally {
      setBusy(false)
    }
  }

  const inputCls =
    "h-12 w-full rounded-xl border border-input bg-white px-3.5 text-[14px] outline-none focus:border-pine/50 min-h-[44px]"

  return (
    <div className="fixed inset-0 z-[80] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="Форма букета">
      <div className="absolute inset-0 bg-pine-deep/50" onClick={onClose} />
      <form
        onSubmit={submit}
        className="relative z-10 max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-background p-5 shadow-2xl nice-scroll sm:max-w-2xl sm:rounded-3xl sm:p-7"
      >
        <div className="flex items-start justify-between gap-3">
          <h3 className="font-display text-2xl text-foreground">{product ? "Редактировать букет" : "Новый букет"}</h3>
          <button type="button" onClick={onClose} aria-label="Закрыть" className="grid h-11 w-11 place-items-center rounded-full hover:bg-accent min-h-[44px] min-w-[44px]">
            <X className="h-5 w-5" aria-hidden />
          </button>
        </div>

        <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label className="block sm:col-span-2">
            <span className={labelCls}>Название*</span>
            <input value={name} onChange={(e) => setName(e.target.value)} required minLength={2} className={inputCls} placeholder="Пионовый шёпот" />
          </label>
          <label className="block sm:col-span-2">
            <span className={labelCls}>Подзаголовок — краткий состав</span>
            <input value={sub} onChange={(e) => setSub(e.target.value)} className={inputCls} placeholder="51 эквадорская роза Premium" />
          </label>
          <label>
            <span className={labelCls}>Цена, ₽*</span>
            <input value={price} onChange={(e) => setPrice(e.target.value.replace(/[^\d]/g, ""))} required inputMode="numeric" className={`${inputCls} font-grotesk font-bold`} placeholder="9800" />
          </label>
          <label>
            <span className={labelCls}>Старая цена (для скидки)</span>
            <input value={oldPrice} onChange={(e) => setOldPrice(e.target.value.replace(/[^\d]/g, ""))} inputMode="numeric" className={`${inputCls} font-grotesk`} placeholder="11200" />
          </label>
          <label>
            <span className={labelCls}>Размер</span>
            <input value={size} onChange={(e) => setSize(e.target.value)} className={inputCls} placeholder="60×45 см / Ø 22 см" />
          </label>
          <label>
            <span className={labelCls}>Бейдж на карточке</span>
            <input value={badge} onChange={(e) => setBadge(e.target.value)} className={inputCls} placeholder="Хит / Новинка / Lux" />
          </label>
          <label className="block sm:col-span-2">
            <span className={labelCls}>Состав — по одному на строку</span>
            <textarea
              value={composition}
              onChange={(e) => setComposition(e.target.value)}
              rows={4}
              className={`${inputCls} h-auto resize-none py-2.5`}
              placeholder={"Роза Freedom 60 см\nЭвкалипт цинерея"}
            />
          </label>
          <label className="block sm:col-span-2">
            <span className={labelCls}>Теги для фильтров — через запятую</span>
            <input value={tags} onChange={(e) => setTags(e.target.value)} className={inputCls} placeholder="пионы, гортензии, свидание, стойкие цветы" />
          </label>
          <label className="block sm:col-span-2">
            <span className={labelCls}>Описание для карточки</span>
            <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={4} className={`${inputCls} h-auto resize-none py-2.5`} />
          </label>
          <label>
            <span className={labelCls}>Коллекция</span>
            <select value={line} onChange={(e) => setLine(e.target.value)} className={inputCls}>
              <option value="premium">Премиум (авторская)</option>
              <option value="classic">Классика</option>
            </select>
          </label>
        </div>

        {/* Фото */}
        <div className="mt-5">
          <span className={labelCls}>Фото букета (первое — главное, второе — ракурс для hover)</span>
          <div className="mt-2 flex flex-wrap items-center gap-3">
            {photos.map((ph, i) => (
              <span key={ph + i} className="group relative h-24 w-[72px] overflow-hidden rounded-xl bg-secondary ring-1 ring-border">
                <Image src={ph} alt={`Фото ${i + 1}`} fill sizes="72px" className="object-cover" />
                {i === 0 && (
                  <span className="absolute left-1 top-1 rounded-full bg-pine px-1.5 py-0.5 text-[9px] font-bold text-cream">главное</span>
                )}
                <button
                  type="button"
                  onClick={() => setPhotos((prev) => prev.filter((_, j) => j !== i))}
                  aria-label="Удалить фото"
                  className="absolute inset-0 grid place-items-center bg-pine-deep/60 opacity-0 transition-opacity group-hover:opacity-100"
                >
                  <Trash2 className="h-5 w-5 text-white" aria-hidden />
                </button>
              </span>
            ))}
            <button
              type="button"
              onClick={() => fileRef.current?.click()}
              disabled={uploading}
              className="flex h-24 w-[72px] flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-border text-muted-foreground transition-colors hover:border-pine/40 hover:text-pine"
            >
              {uploading ? <span className="text-[10px]">…</span> : (
                <>
                  <Upload className="h-5 w-5" aria-hidden />
                  <span className="text-[10px]">загрузить</span>
                </>
              )}
            </button>
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => void upload(e.target.files)} />
          </div>
          <p className="mt-1.5 text-[11.5px] text-muted-foreground">
            JPG/PNG/WebP до 8 МБ. Порядок меняется удалением и повторной загрузкой.
          </p>
        </div>

        <div className="mt-6 flex gap-3">
          <button
            type="submit"
            disabled={busy || uploading}
            className="h-12 flex-1 rounded-full bg-pine font-grotesk font-bold text-primary-foreground hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
          >
            {busy ? "Сохраняем…" : product ? "Сохранить букет" : "Добавить в каталог"}
          </button>
          <button type="button" onClick={onClose} className="h-12 rounded-full border border-border bg-white px-6 font-grotesk font-bold text-foreground hover:bg-accent min-h-[44px]">
            Отмена
          </button>
        </div>
      </form>
    </div>
  )
}

const labelCls = "mb-1.5 block text-[12.5px] font-semibold text-foreground"
