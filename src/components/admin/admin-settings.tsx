"use client"

import { useState } from "react"
import { Megaphone, Phone, Save, Sparkles } from "lucide-react"
import { toast } from "sonner"

// No-code настройки: контакты, баннеры, тексты — всё сразу на витрине
export function AdminSettings({
  settings,
  onSaved,
  onRelogin,
}: {
  settings: Record<string, string>
  onSaved: () => void
  onRelogin: () => void
}) {
  const [form, setForm] = useState<Record<string, string>>({ ...settings })
  const [busy, setBusy] = useState(false)

  const set = (k: string, v: string) => setForm((f) => ({ ...f, [k]: v }))

  const save = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    try {
      const r = await fetch("/api/admin/settings", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form),
      })
      const d = await r.json()
      if (!r.ok) throw new Error(d.error || "Ошибка сохранения")
      toast.success("Сохранено — витрина уже обновилась")
      onSaved()
      if (form.admin_password !== settings.admin_password) {
        toast.info("Пароль изменён — войдите заново")
        onRelogin()
      }
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Ошибка")
    } finally {
      setBusy(false)
    }
  }

  const inputCls =
    "h-12 w-full rounded-xl border border-input bg-white px-3.5 text-[14px] outline-none focus:border-pine/50 min-h-[44px]"
  const labelCls = "mb-1.5 block text-[12.5px] font-semibold text-foreground"

  return (
    <form onSubmit={save}>
      <h2 className="font-display text-xl text-foreground">Управление сайтом</h2>
      <p className="mt-0.5 text-[12.5px] text-muted-foreground">
        Изменения появляются на витрине сразу после сохранения — без правок в коде
      </p>

      {/* Контакты */}
      <section className="mt-5 rounded-2xl bg-white p-5 shadow-sm">
        <h3 className="flex items-center gap-2 font-grotesk text-[14px] font-bold text-foreground">
          <Phone className="h-4 w-4 text-pine" aria-hidden />
          Контакты
        </h3>
        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label>
            <span className={labelCls}>Телефон</span>
            <input value={form.shop_phone || ""} onChange={(e) => set("shop_phone", e.target.value)} className={inputCls} placeholder="+7 (812) 565-09-09" />
          </label>
          <label>
            <span className={labelCls}>Подпись под телефоном</span>
            <input value={form.shop_phone_note || ""} onChange={(e) => set("shop_phone_note", e.target.value)} className={inputCls} placeholder="Круглосуточно, без выходных" />
          </label>
          <label>
            <span className={labelCls}>WhatsApp (ссылка)</span>
            <input value={form.whatsapp || ""} onChange={(e) => set("whatsapp", e.target.value)} className={inputCls} placeholder="https://wa.me/78125650909" />
          </label>
          <label>
            <span className={labelCls}>Telegram (ссылка)</span>
            <input value={form.telegram || ""} onChange={(e) => set("telegram", e.target.value)} className={inputCls} placeholder="https://t.me/nilovflowers" />
          </label>
          <label>
            <span className={labelCls}>Email</span>
            <input value={form.shop_email || ""} onChange={(e) => set("shop_email", e.target.value)} className={inputCls} placeholder="hello@nilov-flowers.ru" />
          </label>
          <label>
            <span className={labelCls}>График работы</span>
            <input value={form.working_hours || ""} onChange={(e) => set("working_hours", e.target.value)} className={inputCls} placeholder="Ежедневно 08:00 — 23:00" />
          </label>
          <label className="block sm:col-span-2">
            <span className={labelCls}>Адрес студии</span>
            <input value={form.shop_address || ""} onChange={(e) => set("shop_address", e.target.value)} className={inputCls} placeholder="наб. реки Мойки, 82 · вход со двора" />
          </label>
        </div>
      </section>

      {/* Баннеры и тексты */}
      <section className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 className="flex items-center gap-2 font-grotesk text-[14px] font-bold text-foreground">
          <Megaphone className="h-4 w-4 text-pine" aria-hidden />
          Баннеры и объявления
        </h3>
        <div className="mt-4 grid grid-cols-1 gap-4">
          <label>
            <span className={labelCls}>Верхняя полоса преимуществ (разделитель — •)</span>
            <textarea
              value={form.top_banner || ""}
              onChange={(e) => set("top_banner", e.target.value)}
              rows={2}
              className={`${inputCls} h-auto resize-none py-2.5`}
              placeholder="Доставка цветов по СПб от 60 минут • Фото букета перед отправкой в WhatsApp • Бесплатная открытка с вашим текстом"
            />
          </label>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label>
              <span className={labelCls}>Плашка рейтинга</span>
              <input value={form.rating_badge || ""} onChange={(e) => set("rating_badge", e.target.value)} className={inputCls} placeholder="5.0 на Яндекс Картах" />
            </label>
            <label>
              <span className={labelCls}>Бесплатная доставка от, ₽</span>
              <input
                value={form.free_delivery_from || ""}
                onChange={(e) => set("free_delivery_from", e.target.value.replace(/[^\d]/g, ""))}
                inputMode="numeric"
                className={`${inputCls} font-grotesk font-bold`}
                placeholder="5000"
              />
            </label>
          </div>
          <label>
            <span className={labelCls}>Строка оплаты частями</span>
            <input value={form.split_text || ""} onChange={(e) => set("split_text", e.target.value)} className={inputCls} placeholder="Оплата частями: Яндекс Сплит / Долями — цена ÷ 4 без переплат" />
          </label>
        </div>
      </section>

      {/* Промо-блоки главной */}
      <section className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 className="flex items-center gap-2 font-grotesk text-[14px] font-bold text-foreground">
          <Sparkles className="h-4 w-4 text-pine" aria-hidden />
          Первый экран
        </h3>
        <div className="mt-4 grid grid-cols-1 gap-4">
          <label>
            <span className={labelCls}>Заголовок (Playfair, крупно)</span>
            <textarea value={form.hero_title || ""} onChange={(e) => set("hero_title", e.target.value)} rows={2} className={`${inputCls} h-auto resize-none py-2.5 font-display text-[16px]`} />
          </label>
          <label>
            <span className={labelCls}>Подзаголовок</span>
            <textarea value={form.hero_lead || ""} onChange={(e) => set("hero_lead", e.target.value)} rows={2} className={`${inputCls} h-auto resize-none py-2.5`} />
          </label>
        </div>
      </section>

      {/* Пароль */}
      <section className="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 className="font-grotesk text-[14px] font-bold text-foreground">Пароль администратора</h3>
        <label className="mt-4 block sm:max-w-xs">
          <span className={labelCls}>Новый пароль (минимум 6 символов)</span>
          <input
            type="password"
            value={form.admin_password || ""}
            onChange={(e) => set("admin_password", e.target.value)}
            autoComplete="new-password"
            className={inputCls}
            placeholder="••••••••"
          />
        </label>
        <p className="mt-2 text-[11.5px] text-muted-foreground">
          После смены пароля текущая сессия завершится — это нормально.
        </p>
      </section>

      <div className="sticky bottom-4 mt-5 flex justify-end">
        <button
          type="submit"
          disabled={busy}
          className="inline-flex h-12 items-center gap-2 rounded-full bg-pine px-7 font-grotesk text-[14px] font-bold text-primary-foreground shadow-lg shadow-pine/25 hover:bg-pine-deep disabled:opacity-60 min-h-[44px]"
        >
          <Save className="h-4 w-4" aria-hidden />
          {busy ? "Сохраняем…" : "Опубликовать на витрине"}
        </button>
      </div>
    </form>
  )
}
