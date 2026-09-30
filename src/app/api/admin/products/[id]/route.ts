import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"
import { parseProductRow } from "@/lib/types"

// Правка товара: тумблер наличия, инлайн-цена, полная форма
export async function PATCH(req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const pid = Number(id)
  if (!Number.isFinite(pid)) return Response.json({ error: "bad id" }, { status: 400 })
  const b = (await req.json()) as Record<string, unknown>

  const data: Record<string, unknown> = {}
  if (typeof b.inStock === "boolean") data.inStock = b.inStock
  if (typeof b.visible === "boolean") data.visible = b.visible
  if (typeof b.popular === "boolean") data.popular = b.popular
  if (b.price !== undefined) data.price = Math.max(0, Math.round(Number(b.price) || 0))
  if (b.oldPrice !== undefined) {
    const n = Number(b.oldPrice)
    data.oldPrice = Number.isFinite(n) && n > 0 ? Math.round(n) : null
  }
  if (typeof b.name === "string" && b.name.trim().length >= 2) data.name = b.name.trim()
  if (typeof b.sub === "string") data.sub = b.sub.trim() || null
  if (typeof b.description === "string") data.description = b.description.trim() || null
  if (typeof b.size === "string") data.size = b.size.trim() || null
  if (typeof b.badge === "string") data.badge = b.badge.trim() || null
  if (b.composition !== undefined) data.composition = JSON.stringify(Array.isArray(b.composition) ? b.composition : [])
  if (b.photos !== undefined) data.photos = JSON.stringify(Array.isArray(b.photos) ? b.photos : [])
  if (b.tags !== undefined) data.tags = JSON.stringify(Array.isArray(b.tags) ? b.tags : [])
  if (b.categoryId !== undefined) {
    const n = Number(b.categoryId)
    data.categoryId = Number.isFinite(n) && n > 0 ? n : null
  }
  if (b.sort !== undefined) data.sort = Math.round(Number(b.sort) || 100)

  if (!Object.keys(data).length) return Response.json({ error: "nothing to update" }, { status: 400 })

  const product = await db.product.update({ where: { id: pid }, data })
  return Response.json({ ok: true, product: parseProductRow(product) })
}

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const pid = Number(id)
  if (!Number.isFinite(pid)) return Response.json({ error: "bad id" }, { status: 400 })
  await db.product.delete({ where: { id: pid } })
  return Response.json({ ok: true })
}
