import { db } from "@/lib/db"
import { clientKey, rateLimit } from "@/lib/rate-limit"

// Валидация промокода
export async function POST(req: Request) {
  // Раунд 1 (критик 5, P2): rate-limit от спама и 400 вместо 500 на битом JSON
  if (!rateLimit(`promo:${clientKey(req)}`, 20)) {
    return Response.json({ error: "Слишком много попыток — подождите минуту" }, { status: 429 })
  }
  let body: { code?: string; sum?: number }
  try {
    body = await req.json()
  } catch {
    return Response.json({ error: "Некорректный запрос" }, { status: 400 })
  }
  try {
    const { code, sum } = body
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