import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"
import { parseOrderRow } from "@/lib/types"

// Список заказов для рабочего стола флориста
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const orders = await db.order.findMany({
    orderBy: { createdAt: "desc" },
    include: { items: true, zone: true },
    take: 500,
  })
  return Response.json({ orders: orders.map(parseOrderRow) })
}
