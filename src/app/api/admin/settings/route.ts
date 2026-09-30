import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

// No-code настройки сайта: контакты, баннеры, тексты витрины
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const rows = await db.setting.findMany({ orderBy: { key: "asc" } })
  const settings: Record<string, string> = {}
  for (const r of rows) settings[r.key] = r.value
  return Response.json({ settings })
}

export async function PATCH(req: Request) {
  if (!(await isAdmin())) return unauthorized()
  const b = (await req.json()) as Record<string, string | number>
  const writable = new Set([
    "shop_name",
    "shop_city",
    "shop_phone",
    "shop_phone_note",
    "shop_email",
    "shop_address",
    "working_hours",
    "whatsapp",
    "telegram",
    "instagram",
    "top_banner",
    "rating_badge",
    "split_text",
    "free_delivery_from",
    "split_months",
    "hero_title",
    "hero_lead",
    "founder_name",
    "admin_password",
  ])
  const updates = Object.entries(b).filter(([k]) => writable.has(k))
  if (!updates.length) return Response.json({ error: "nothing to update" }, { status: 400 })
  for (const [key, value] of updates) {
    const v = String(value).trim()
    if (key === "admin_password" && v.length < 6) {
      return Response.json({ error: "Пароль администратора — минимум 6 символов" }, { status: 400 })
    }
    if (key === "free_delivery_from") {
      const n = Math.round(Number(v) || 0)
      await db.setting.upsert({ where: { key }, update: { value: String(Math.max(0, n)) }, create: { key, value: String(Math.max(0, n)) } })
      continue
    }
    await db.setting.upsert({ where: { key }, update: { value: v }, create: { key, value: v } })
  }
  return Response.json({ ok: true })
}
