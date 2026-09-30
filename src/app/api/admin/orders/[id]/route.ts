import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"

const ALLOWED = new Set(["new", "photo", "assembly", "courier", "done", "canceled"])

// Изменение заказа: статус (drag-and-drop канбана), поля, удаление
export async function PATCH(req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const orderId = Number(id)
  if (!Number.isFinite(orderId)) return Response.json({ error: "bad id" }, { status: 400 })
  const body = (await req.json()) as Record<string, unknown>

  const data: Record<string, unknown> = {}
  if (typeof body.status === "string" && ALLOWED.has(body.status)) data.status = body.status
  if (typeof body.comment === "string") data.comment = body.comment || null
  if (typeof body.address === "string") data.address = body.address || null
  if (typeof body.deliveryDate === "string") data.deliveryDate = body.deliveryDate || null
  if (typeof body.deliverySlot === "string") data.deliverySlot = body.deliverySlot || null
  if (typeof body.cardText === "string") data.cardText = body.cardText || null
  if (typeof body.recipientName === "string") data.recipientName = body.recipientName || null
  if (typeof body.recipientPhone === "string") data.recipientPhone = body.recipientPhone || null

  if (!Object.keys(data).length) return Response.json({ error: "nothing to update" }, { status: 400 })

  const order = await db.order.update({
    where: { id: orderId },
    data,
    include: { items: true, zone: true },
  })
  return Response.json({ ok: true, order })
}

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  if (!(await isAdmin())) return unauthorized()
  const { id } = await params
  const orderId = Number(id)
  if (!Number.isFinite(orderId)) return Response.json({ error: "bad id" }, { status: 400 })
  await db.order.delete({ where: { id: orderId } })
  return Response.json({ ok: true })
}
