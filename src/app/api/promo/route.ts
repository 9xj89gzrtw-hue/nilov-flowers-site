import { db } from "@/lib/db"

// Валидация промокода
export async function POST(req: Request) {
  try {
    const { code, sum } = (await req.json()) as { code?: string; sum?: number }
    const clean = (code || "").trim().toUpperCase()
    if (!clean) return Response.json({ error: "Введите промокод" }, { status: 400 })
    const promo = await db.promoCode.findFirst({
      where: { code: clean, active: true },
    })
    if (!promo) return Response.json({ error: "Такого промокода нет" }, { status: 404 })
    const basketSum = sum ?? 0
    if (basketSum < promo.min) {
      return Response.json(
        { error: `Промокод действует от ${new Intl.NumberFormat("ru-RU").format(promo.min)} ₽` },
        { status: 400 },
      )
    }
    return Response.json({
      promo: { code: promo.code, type: promo.type, value: promo.value, label: promo.label },
    })
  } catch {
    return Response.json({ error: "Ошибка запроса" }, { status: 500 })
  }
}
