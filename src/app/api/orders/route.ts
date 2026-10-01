import { db } from "@/lib/db"
import { clientKey, rateLimit } from "@/lib/rate-limit"

type IncomingItem = {
  kind: "product" | "upsell"
  productId?: number
  upsellSlug?: string
  qty: number
}

type OrderBody = {
  customerName?: string
  customerPhone?: string
  customerEmail?: string
  comment?: string
  recipientName?: string
  recipientPhone?: string
  surprise?: boolean
  pickup?: boolean
  knowAddress?: boolean
  zoneId?: number | null
  address?: string
  deliveryDate?: string
  deliverySlot?: string
  cardText?: string
  paymentMethod?: string
  promoCode?: string | null
  items?: IncomingItem[]
}

// Создание заказа (витрина + «Купить в 1 клик»). Цены всегда пересчитываются на сервере.
export async function POST(req: Request) {
  // Раунд 1 (критик 5, P2): анти-спам заказов
  if (!rateLimit(`orders:${clientKey(req)}`, 8)) {
    return Response.json({ error: "Слишком много заказов подряд — позвоните нам: поможем" }, { status: 429 })
  }
  let b: OrderBody
  try {
    b = (await req.json()) as OrderBody
  } catch {
    return Response.json({ error: "Некорректный запрос" }, { status: 400 })
  }

  try {
    const name = (b.customerName || "").trim()
    const phone = (b.customerPhone || "").trim()
    if (name.length < 2) return Response.json({ error: "Укажите имя (минимум 2 буквы)" }, { status: 400 })
    if (phone.replace(/\D/g, "").length < 10) {
      return Response.json({ error: "Укажите телефон в формате +7 (___) ___-__-__" }, { status: 400 })
    }

    const incoming = (b.items || []).filter((i) => i.qty > 0 && (i.productId || i.upsellSlug))
    if (!incoming.length) return Response.json({ error: "Корзина пуста" }, { status: 400 })

    // Пересчёт цен по живой БД
    const productIds = incoming.filter((i) => i.kind === "product").map((i) => i.productId!)
    const upsellSlugs = incoming.filter((i) => i.kind === "upsell").map((i) => i.upsellSlug!)
    const [products, upsells] = await Promise.all([
      productIds.length ? db.product.findMany({ where: { id: { in: productIds } } }) : [],
      upsellSlugs.length ? db.upsell.findMany({ where: { slug: { in: upsellSlugs } } }) : [],
    ])

    const lines: { productId: number | null; title: string; price: number; qty: number; photo: string | null }[] = []
    for (const item of incoming) {
      if (item.kind === "product") {
        const p = products.find((x) => x.id === item.productId)
        if (!p || !p.inStock || !p.visible) continue
        lines.push({
          productId: p.id,
          title: p.name,
          price: p.price,
          qty: Math.min(item.qty, 99),
          photo: safeFirstPhoto(p.photos),
        })
      } else {
        const u = upsells.find((x) => x.slug === item.upsellSlug)
        if (!u || !u.active) continue
        lines.push({ productId: null, title: u.name, price: u.price, qty: Math.min(item.qty, 99), photo: u.photo })
      }
    }
    if (!lines.length) {
      return Response.json({ error: "Товары недоступны — обновите корзину" }, { status: 400 })
    }

    const itemsTotal = lines.reduce((s, l) => s + l.price * l.qty, 0)

    // Доставка
    let zoneId: number | null = null
    let deliveryPrice = 0
    let zone: { id: number; price: number } | null = null
    if (!b.pickup && b.zoneId) {
      zone = await db.deliveryZone.findUnique({ where: { id: b.zoneId } })
      if (zone) {
        zoneId = zone.id
        const freeFrom = Number((await db.setting.findUnique({ where: { key: "free_delivery_from" } }))?.value || 5000)
        deliveryPrice = itemsTotal >= freeFrom ? 0 : zone.price
      }
    }

    // Промокод
    let discount = 0
    let promoCode: string | null = null
    if (b.promoCode) {
      const promo = await db.promoCode.findFirst({ where: { code: b.promoCode.trim().toUpperCase(), active: true } })
      if (promo && itemsTotal >= promo.min) {
        promoCode = promo.code
        discount = promo.type === "percent" ? Math.round((itemsTotal * promo.value) / 100) : Math.min(promo.value, itemsTotal)
      }
    }

    const total = Math.max(0, itemsTotal - discount) + deliveryPrice

    const order = await db.$transaction(async (tx) => {
      const created = await tx.order.create({
        data: {
          number: `NF-${Date.now().toString(36).toUpperCase()}`, // временный, заменяется в транзакции
          status: "new",
          customerName: name,
          customerPhone: phone,
          customerEmail: b.customerEmail?.trim() || null,
          comment: b.comment?.trim() || null,
          recipientName: b.recipientName?.trim() || null,
          recipientPhone: b.recipientPhone?.trim() || null,
          surprise: !!b.surprise,
          pickup: !!b.pickup,
          knowAddress: !!b.knowAddress,
          zoneId,
          address: b.pickup ? null : b.address?.trim() || null,
          deliveryDate: b.pickup ? null : b.deliveryDate || null,
          deliverySlot: b.pickup ? null : b.deliverySlot || null,
          cardText: b.cardText?.trim() || null,
          paymentMethod: b.paymentMethod === "cash" ? "cash" : "online",
          deliveryPrice,
          itemsTotal,
          total,
          items: {
            create: lines.map((l) => ({
              productId: l.productId,
              title: l.title,
              price: l.price,
              qty: l.qty,
              photo: l.photo,
            })),
          },
        },
        include: { items: true, zone: true },
      })
      // Раунд 3 (критик 15, P2): номер заказа присваивается в той же транзакции — без race
      return tx.order.update({
        where: { id: created.id },
        data: { number: `NF-${1000 + created.id}` },
        include: { items: true, zone: true },
      })
    })

    return Response.json({ ok: true, order })
  } catch (e) {
    console.error("order create error", e)
    return Response.json({ error: "Не удалось оформить заказ. Позвоните нам — поможем." }, { status: 500 })
  }
}

function safeFirstPhoto(photosJson: string): string | null {
  try {
    const arr = JSON.parse(photosJson)
    return Array.isArray(arr) && arr.length ? arr[0] : null
  } catch {
    return null
  }
}
