import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

export async function PATCH(req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const uid = Number(id)
  if (!Number.isFinite(uid)) return Response.json({ error: "bad id" }, { status: 400 })
  const b = (await req.json()) as Record<string, unknown>
  const data: Record<string, unknown> = {}
  if (typeof b.name === "string" && b.name.trim().length >= 2) data.name = b.name.trim()
  if (typeof b.description === "string") data.description = b.description.trim() || null
  if (b.price !== undefined) data.price = Math.max(0, Math.round(Number(b.price) || 0))
  if (typeof b.photo === "string") data.photo = b.photo.trim() || null
  if (typeof b.active === "boolean") data.active = b.active
  if (b.sort !== undefined) data.sort = Math.round(Number(b.sort)) || 100
  if (!Object.keys(data).length) return Response.json({ error: "nothing to update" }, { status: 400 })
  const upsell = await db.upsell.update({ where: { id: uid }, data })
  return Response.json({ ok: true, upsell })
}

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const uid = Number(id)
  if (!Number.isFinite(uid)) return Response.json({ error: "bad id" }, { status: 400 })
  await db.upsell.delete({ where: { id: uid } })
  return Response.json({ ok: true })
}
