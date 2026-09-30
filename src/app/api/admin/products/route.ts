import { db } from "@/lib/db"
import { isAdmin, unauthorized } from "@/lib/admin-auth"
import { parseProductRow } from "@/lib/types"

// Каталог для админки: ВСЕ товары (включая скрытые)
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const products = await db.product.findMany({ orderBy: { sort: "asc" }, include: { category: true } })
  return Response.json({ products: products.map(parseProductRow) })
}

// Создание товара
export async function POST(req: Request) {
  if (!(await isAdmin())) return unauthorized()
  try {
    const b = (await req.json()) as Record<string, unknown>
    const name = String(b.name || "").trim()
    if (name.length < 2) return Response.json({ error: "Название обязательно" }, { status: 400 })
    const price = Math.max(0, Math.round(Number(b.price) || 0))
    const slugBase =
      String(b.slug || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, "-")
        .replace(/^-+|-+$/g, "") || `buket-${Date.now().toString(36)}`
    let slug = slugBase
    let n = 1
    while (await db.product.findUnique({ where: { slug } })) slug = `${slugBase}-${++n}`

    const product = await db.product.create({
      data: {
        slug,
        name,
        sub: strOrNull(b.sub),
        line: b.line === "classic" ? "classic" : "premium",
        price,
        oldPrice: numOrNull(b.oldPrice),
        composition: JSON.stringify(arr(b.composition)),
        description: strOrNull(b.description),
        size: strOrNull(b.size),
        photos: JSON.stringify(arr(b.photos)),
        badge: strOrNull(b.badge),
        categoryId: numOrNull(b.categoryId),
        tags: JSON.stringify(arr(b.tags)),
        inStock: b.inStock !== false,
        visible: b.visible !== false,
        popular: !!b.popular,
        sort: Math.round(Number(b.sort) || 999),
      },
    })
    return Response.json({ ok: true, product: parseProductRow(product) })
  } catch (e) {
    console.error("product create", e)
    return Response.json({ error: "Не удалось создать товар" }, { status: 500 })
  }
}

function strOrNull(v: unknown): string | null {
  const s = String(v ?? "").trim()
  return s.length ? s : null
}
function numOrNull(v: unknown): number | null {
  const n = Number(v)
  return Number.isFinite(n) && n > 0 ? Math.round(n) : null
}
function arr(v: unknown): unknown[] {
  return Array.isArray(v) ? v.filter((x) => String(x ?? "").trim().length > 0) : []
}
