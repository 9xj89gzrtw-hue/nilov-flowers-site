import { db } from "@/lib/db"
import { parseProductRow } from "@/lib/types"
import { StoreApp } from "@/components/store/store-app"
import fs from "node:fs"

// Витрина собирается на сервере из живой БД (Prisma/SQLite).
// Отзывы и FAQ — реальный контент сайта (v2026/content.json).
export default async function Home() {
  const [products, zones, upsells, settingsRows, categories] = await Promise.all([
    db.product.findMany({ where: { visible: true, inStock: true }, orderBy: { sort: "asc" } }),
    db.deliveryZone.findMany({ orderBy: { sort: "asc" } }),
    db.upsell.findMany({ where: { active: true }, orderBy: { sort: "asc" } }),
    db.setting.findMany(),
    db.category.findMany({ orderBy: { sort: "asc" } }),
  ])

  const settings: Record<string, string> = {}
  for (const s of settingsRows) {
    if (s.key.startsWith("admin_")) continue
    settings[s.key] = s.value
  }

  // Контентные блоки (единый источник контента сайта)
  let reviews: { n: string; d: string; src: string; r: number; t: string }[] = []
  let rating = { value: 4.96, count: 2418 }
  let faq: { q: string; a: string }[] = []
  try {
    const content = JSON.parse(fs.readFileSync("v2026/content.json", "utf8"))
    reviews = content.reviews?.items || []
    rating = content.reviews?.rating || rating
    faq = content.faq?.items || content.faq || []
  } catch {}

  return (
    <StoreApp
      initialData={{
        products: products.map(parseProductRow),
        zones,
        upsells,
        settings,
        categories,
      }}
      editorial={{ reviews, rating, faq }}
    />
  )
}
