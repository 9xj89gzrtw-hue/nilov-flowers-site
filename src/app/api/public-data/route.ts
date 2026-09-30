import { db } from "@/lib/db"
import { clientKey, rateLimit } from "@/lib/rate-limit"
import { parseProductRow } from "@/lib/types"

// Публичные данные витрины: продукты (visible), зоны, апселлы, настройки
// Раунд 1 (критик 5, P2): короткий браузерный кэш смягчает фокус-рефреши
export async function GET() {
  const [products, zones, upsells, settingsRows, categories] = await Promise.all([
    db.product.findMany({
      where: { visible: true },
      orderBy: { sort: "asc" },
      include: { category: true },
    }),
    db.deliveryZone.findMany({ orderBy: { sort: "asc" } }),
    db.upsell.findMany({ where: { active: true }, orderBy: { sort: "asc" } }),
    db.setting.findMany(),
    db.category.findMany({ orderBy: { sort: "asc" } }),
  ])

  const settings: Record<string, string> = {}
  for (const s of settingsRows) {
    if (s.key.startsWith("admin_")) continue // секреты наружу не отдаём
    settings[s.key] = s.value
  }

  return Response.json(
    {
      products: products.map(parseProductRow),
      zones,
      upsells,
      settings,
      categories,
    },
    { headers: { "Cache-Control": "public, max-age=30, stale-while-revalidate=60" } },
  )
}
