import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

// Допродажи (открытка, Кризал, аквабокс, сладости)
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const upsells = await db.upsell.findMany({ orderBy: { sort: "asc" } })
  return Response.json({ upsells })
}

export async function POST(req: Request) {
  if (!(await isAdmin())) return unauthorized()
  const b = (await req.json()) as Record<string, unknown>
  const name = String(b.name || "").trim()
  if (name.length < 2) return Response.json({ error: "Название обязательно" }, { status: 400 })
  const slugBase =
    String(b.slug || name)
      .toLowerCase()
      .replace(/[^a-z0-9-]+/g, "-")
      .replace(/^-+|-+$/g, "") || `upsell-${Date.now().toString(36)}`
  let slug = slugBase
  let n = 1
  while (await db.upsell.findUnique({ where: { slug } })) slug = `${slugBase}-${++n}`
  const maxSort = await db.upsell.aggregate({ _max: { sort: true } })
  const upsell = await db.upsell.create({
    data: {
      slug,
      name,
      description: String(b.description || "").trim() || null,
      price: Math.max(0, Math.round(Number(b.price) || 0)),
      photo: String(b.photo || "").trim() || null,
      active: b.active !== false,
      sort: Math.round(Number(b.sort)) || (maxSort._max.sort ?? 0) + 10,
    },
  })
  return Response.json({ ok: true, upsell })
}
