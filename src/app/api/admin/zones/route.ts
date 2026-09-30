import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

// Зоны доставки (no-code: таблица районов с ценой и сроками)
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const zones = await db.deliveryZone.findMany({ orderBy: { sort: "asc" } })
  return Response.json({ zones })
}

export async function POST(req: Request) {
  if (!(await isAdmin())) return unauthorized()
  const b = (await req.json()) as Record<string, unknown>
  const name = String(b.name || "").trim()
  if (name.length < 2) return Response.json({ error: "Название района обязательно" }, { status: 400 })
  const maxSort = await db.deliveryZone.aggregate({ _max: { sort: true } })
  const zone = await db.deliveryZone.create({
    data: {
      name,
      price: Math.max(0, Math.round(Number(b.price) || 0)),
      eta: String(b.eta || "").trim() || null,
      note: String(b.note || "").trim() || null,
      sort: Math.round(Number(b.sort)) || (maxSort._max.sort ?? 0) + 10,
    },
  })
  return Response.json({ ok: true, zone })
}
