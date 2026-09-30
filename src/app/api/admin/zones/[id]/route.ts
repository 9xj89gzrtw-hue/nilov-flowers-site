import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

export async function PATCH(req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const zid = Number(id)
  if (!Number.isFinite(zid)) return Response.json({ error: "bad id" }, { status: 400 })
  const b = (await req.json()) as Record<string, unknown>
  const data: Record<string, unknown> = {}
  if (typeof b.name === "string" && b.name.trim().length >= 2) data.name = b.name.trim()
  if (b.price !== undefined) data.price = Math.max(0, Math.round(Number(b.price) || 0))
  if (typeof b.eta === "string") data.eta = b.eta.trim() || null
  if (typeof b.note === "string") data.note = b.note.trim() || null
  if (b.sort !== undefined) data.sort = Math.round(Number(b.sort)) || 100
  if (!Object.keys(data).length) return Response.json({ error: "nothing to update" }, { status: 400 })
  const zone = await db.deliveryZone.update({ where: { id: zid }, data })
  return Response.json({ ok: true, zone })
}

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const zid = Number(id)
  if (!Number.isFinite(zid)) return Response.json({ error: "bad id" }, { status: 400 })
  const used = await db.order.count({ where: { zoneId: zid } })
  if (used > 0) return Response.json({ error: "К зоне привязаны заказы — удаление запрещено" }, { status: 400 })
  await db.deliveryZone.delete({ where: { id: zid } })
  return Response.json({ ok: true })
}
