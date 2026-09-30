/**
 * Seed «живой БД» Nilov Flowers S2.
 * Источники (только реальные данные репозитория):
 *  - v2026/content.json — премиальная коллекция S1 (20 букетов, контакты, промокоды)
 *  - includes/db.php — классический каталог и зоны доставки СПб PHP-эры
 *  - img/products/* — реальные фото (соответствие проверено VLM)
 * Запуск: bun scripts/seed.ts
 */
import { PrismaClient } from '@prisma/client'
import fs from 'node:fs'
import path from 'node:path'

const db = new PrismaClient()
const ROOT = path.resolve(import.meta.dir, '..')
const content = JSON.parse(fs.readFileSync(path.join(ROOT, 'v2026/content.json'), 'utf8'))

// ---------- утилиты ----------
const FILLERS = ['эвкалипт', 'рускус', 'гипсофила', 'монстера', 'солидаго', 'лагурус', 'злак', 'пампасн', 'писташ']
const isFiller = (f: string) => FILLERS.some(k => f.toLowerCase().includes(k))

function mainFlowers(flowers: string[]): string[] {
  return flowers.filter(f => !isFiller(f))
}

function tagsFor(p: any, catSlug: string): string[] {
  const t = new Set<string>()
  const flowers: string[] = p.flowers || []
  const text = ((p.desc || '') + ' ' + flowers.join(' ')).toLowerCase()
  if (flowers.some(f => f.toLowerCase().includes('роза'))) t.add('розы')
  if (flowers.some(f => f.toLowerCase().includes('пион')) || catSlug === 'peonies') t.add('пионы')
  if (text.includes('гортенз')) t.add('гортензии')
  if (catSlug === 'box') t.add('шляпные коробки')
  if (text.includes('трёх недель') || text.includes('трех недель') || text.includes('14 дней') || flowers.some(f => /альстромер|орхид|фаленопсис|калл/.test(f.toLowerCase()))) t.add('стойкие цветы')
  if (mainFlowers(flowers).length <= 1) t.add('монобукеты')
  if ((p.badge || '') === 'Хит' || (p.badge || '') === 'Топ') t.add('хит')
  return [...t]
}

// Кураторские теги поверх правил (на основе occ/цены/состава):
const MANUAL_TAGS: Record<string, string[]> = {
  p01: ['свидание'],
  p06: ['свидание', 'французские розы'],
  p09: ['свидание'],
  p10: ['свидание', 'французские розы', 'пионы'],
  p11: ['свидание'],
  p16: ['свидание'],
  p02: ['французские розы'],
  p03: ['французские розы'],
  p12: ['французские розы'],
  p17: ['французские розы'],
  p18: ['французские розы'],
}

const exists = (rel: string) => {
  const clean = rel.replace(/^\//, '')
  return fs.existsSync(path.join(ROOT, 'public', clean)) || fs.existsSync(path.join(ROOT, clean))
}

async function main() {
  console.log('→ Очистка таблиц')
  await db.orderItem.deleteMany()
  await db.order.deleteMany()
  await db.product.deleteMany()
  await db.category.deleteMany()
  await db.deliveryZone.deleteMany()
  await db.upsell.deleteMany()
  await db.setting.deleteMany()
  await db.promoCode.deleteMany()

  // ---------- категории ----------
  console.log('→ Категории')
  const cats = content.categories as any[]
  const catMap = new Map<string, number>()
  for (const [i, c] of cats.entries()) {
    if (c.id === 'all') continue
    const row = await db.category.create({ data: { slug: c.id, name: c.label, sort: (i + 1) * 10 } })
    catMap.set(c.id, row.id)
  }

  // ---------- премиальная коллекция (v2026) ----------
  console.log('→ Премиальная коллекция (v2026/content.json)')
  const hashPhotoForP01 = '/img/products/8dd0d077a7e65d88.webp' // VLM: ярко-красные розы, монобукет (замена дубля p01/p05)
  const broken: string[] = []
  // Раунд 1 (критик 1, P1): hover-«второй ракурс» у премиум-линии был чужой заглушкой
  // (заимствованные gen-фото других букетов). Честный выбор: один ракурс — zoom-only hover;
  // два ракурса остаются только там, где это ФАКТИЧЕСКИ тот же букет (классика: b1-buket-iz-roz + roz2).
  const SECOND_ANGLE_OK = new Set(['buket-iz-roz'])
  // Бейджи-мусор из v2026: «−12%» дублирует вычисляемую скидку, «До N ₽» дублирует чипсы цены
  const BADGE_SKIP = (b: string) => /^−?\d+\s*%$/.test(b) || /^до\s+\d/i.test(b)
  for (const [i, p] of (content.products as any[]).entries()) {
    const photos: string[] = []
    const img = p.id === 'p01' ? hashPhotoForP01 : p.img
    if (img) photos.push(img)
    if (p.img2 && SECOND_ANGLE_OK.has(p.id)) photos.push(p.img2)
    for (const ph of photos) if (!exists(ph)) broken.push(`${p.id}: ${ph}`)
    let tags = tagsFor(p, p.cat)
    for (const mt of MANUAL_TAGS[p.id] || []) tags.push(mt)
    tags = [...new Set(tags)]
    const badge = p.badge && !BADGE_SKIP(p.badge) ? p.badge : null
    await db.product.create({
      data: {
        slug: p.id,
        name: p.name,
        sub: p.sub || null,
        line: 'premium',
        price: p.price,
        oldPrice: p.old || null,
        composition: JSON.stringify(p.flowers || []),
        description: p.desc || null,
        size: p.size || null,
        photos: JSON.stringify(photos),
        badge,
        categoryId: catMap.get(p.cat) || null,
        tags: JSON.stringify(tags),
        visible: p.visible !== false,
        popular: !!p.popular,
        sort: (i + 1) * 10,
      },
    })
  }

  // ---------- классическая линия (includes/db.php seed, фото верифицированы VLM) ----------
  console.log('→ Классическая линия (PHP seed)')
  const classic = [
    {
      slug: 'buket-iz-roz', name: 'Утренние розы', sub: '15 роз из утренней поставки',
      price: 2500, oldPrice: null, cat: 'roses',
      flowers: ['Роза эквадорская 50 см', 'Крафт-упаковка с атласной лентой'],
      desc: '15 роз тёплого красного оттенка из утренней поставки, собранные в плотный круглый букет. Размер — около 40–45 см в высоту, упаковка — крафт с атласной лентой. Подходит для свидания, дня рождения и благодарности без повода. В вазе стоит 7–10 дней: подрежьте стебли под углом, меняйте воду раз в сутки и держите букет вдали от солнца и батарей.',
      size: '40–45 см', photos: ['/img/products/b1-buket-iz-roz-2.webp', '/img/products/roz2.webp'],
      badge: 'Хит', tags: ['розы', 'монобукеты', 'хит', 'свидание'], popular: true,
    },
    {
      slug: 'vesennij-etyud', name: 'Весенний этюд', sub: 'Сезонный микс нежных оттенков',
      price: 2100, oldPrice: 2500, cat: 'author',
      flowers: ['Сезонные цветы нежных оттенков', 'Свежая зелень'],
      desc: 'Сборный букет в весенней палитре: сезонные цветы нежных оттенков и свежая зелень. Размер — около 40–45 см, упаковка — крафт с атласной лентой. Повод: 8 Марта, день рождения, визит в гости. Свежесть до 7 дней — подрезайте стебли и меняйте воду ежедневно, увядшие бутоны убирайте сразу.',
      size: '40–45 см', photos: ['/img/products/p2.webp'],
      badge: null, tags: ['сезонные'], popular: false,
    },
    {
      slug: 'buket-belaya-nezhnost', name: 'Белая нежность', sub: 'Белые и кремовые розы с эвкалиптом',
      price: 3500, oldPrice: null, cat: 'roses',
      flowers: ['Роза белая и кремовая', 'Эвкалипт'],
      desc: 'Белые и кремовые цветы с эвкалиптом — спокойная элегантность без ярких акцентов. Размер — около 40–45 см, флорист подберёт упаковку в тон букета. Подходит для поздравления коллеги, выписки из роддома и цветов «на стол». Стоит в вазе до 7 дней: эвкалипт ароматен, воду меняйте раз в сутки.',
      size: '40–45 см', photos: ['/img/products/d12030981795a28f.webp'],
      badge: null, tags: ['розы'], popular: false,
    },
    {
      slug: '25-roz-gortenzii-korobka', name: 'Белые ночи', sub: '25 роз и гортензии в шляпной коробке',
      price: 9500, oldPrice: null, cat: 'box',
      flowers: ['Роза белая', 'Гортензия', 'Шляпная коробка с атласной лентой'],
      desc: '25 роз и гортензии в круглой шляпной коробке с атласной лентой — крупный подарок без вазы и упаковки. Диаметр — около 35 см. Повод: юбилей, годовщина, важное поздравление. Свежесть 7–10 дней: доливайте воду в коробку каждые 1–2 дня.',
      size: 'Ø 35 см', photos: ['/img/products/b1-25-roz-gortenzii-korobka-2.webp'],
      badge: null, tags: ['шляпные коробки', 'гортензии', 'розы'], popular: true,
    },
  ]
  for (const [i, c] of classic.entries()) {
    for (const ph of c.photos) if (!exists(ph)) broken.push(`${c.slug}: ${ph}`)
    await db.product.create({
      data: {
        slug: c.slug, name: c.name, sub: c.sub, line: 'classic',
        price: c.price, oldPrice: c.oldPrice, composition: JSON.stringify(c.flowers),
        description: c.desc, size: c.size, photos: JSON.stringify(c.photos), badge: c.badge,
        categoryId: catMap.get(c.cat) || null, tags: JSON.stringify(c.tags),
        visible: true, popular: c.popular, sort: 500 + (i + 1) * 10,
      },
    })
  }

  // ---------- зоны доставки СПб (12 реальных районов из db.php + пригороды) ----------
  console.log('→ Зоны доставки')
  const zones = [
    // Раунд 1 (критик 3, P1): примечание зоны «бесплатно от 3 000 ₽» конфликтовало
    // с единым глобальным порогом free_delivery_from=5000 — убрано, правило одно для всех.
    ['Центральный (и Петроградка)', 300, '60–90 мин', ''],
    ['Василеостровский', 300, '75–100 мин', ''],
    ['Адмиралтейский', 300, '60–90 мин', ''],
    ['Выборгский', 350, '90–120 мин', ''],
    ['Калининский', 350, '90–120 мин', ''],
    ['Приморский', 400, '100–140 мин', ''],
    ['Московский', 400, '90–120 мин', ''],
    ['Фрунзенский', 400, '120–160 мин', ''],
    ['Невский', 450, '100–140 мин', ''],
    ['Красногвардейский', 450, '90–120 мин', ''],
    ['Кировский (и Красносельский)', 450, '120–160 мин', ''],
    ['Пушкин (Павловск, Петергоф)', 500, '150–180 мин', ''],
    ['Кудрово (Заневский)', 500, '150–180 мин', ''],
    ['Мурино (Девяткино)', 500, '150–180 мин', ''],
  ] as const
  for (const [i, z] of zones.entries()) {
    await db.deliveryZone.create({ data: { name: z[0], price: z[1], eta: z[2], note: z[3], sort: (i + 1) * 10 } })
  }

  // ---------- апселлы ----------
  console.log('→ Апселлы и подарки')
  const upsells = [
    ['card', 'Фирменная открытка', 'Бесплатная открытка с вашим текстом — флорист напишет от руки каллиграфическим почерком', 0, null, 10],
    ['chrysal', 'Кризал — питание для цветов', 'Средство для продления жизни цветов: +5–7 дней свежести, меняйте воду с Кризалом раз в 2 дня', 0, null, 20],
    ['aquabox', 'Аквабокс', 'Водный резервуар для безопасной транспортировки: букет живёт до 3 дней без вазы', 350, null, 30],
    ['strawberry-mini', 'Клубника в шоколаде (мини)', 'Свежие ягоды в тёмной и молочной глазури бельгийского шоколада, ~200 г, порция на двоих. Хранить при +2…+6 °C, срок 24 часа', 990, '/img/products/gen12.webp', 40],
    ['strawberry-macarons', 'Клубника и макаруны', 'Клубника в шоколаде и макаруны в розовой коробке, ~250 г. Хранить при +2…+6 °C, срок 24 часа', 1490, '/img/products/gen20.webp', 50],
  ] as const
  for (const u of upsells) {
    await db.upsell.create({ data: { slug: u[0], name: u[1], description: u[2], price: u[3], photo: u[4], sort: u[5], active: true } })
  }

  // ---------- настройки (no-code, из content.json contacts + бриф S2) ----------
  console.log('→ Настройки витрины')
  const ct = content.contacts
  const settings: Record<string, string> = {
    shop_name: 'Nilov Flowers',
    shop_city: 'Санкт-Петербург',
    shop_phone: ct.phone,
    shop_phone_note: ct.phoneNote,
    shop_email: ct.email,
    shop_address: ct.address,
    working_hours: ct.hours,
    whatsapp: ct.whatsapp,
    telegram: ct.telegram,
    instagram: ct.instagram,
    top_banner: 'Доставка цветов по СПб от 60 минут • Фото букета перед отправкой в WhatsApp • Бесплатная открытка с вашим текстом',
    rating_badge: '5.0 на Яндекс Картах',
    split_text: 'Оплата частями: Яндекс Сплит / Долями — цена ÷ 4 без переплат',
    free_delivery_from: '5000',
    split_months: '4',
    hero_title: 'Букеты, которые приезжают раньше, чем остывает признание',
    hero_lead: 'Свежий срез каждое утро · фото готового букета до отправки · доставка по Петербургу от 60 минут',
    founder_name: 'Артём Нилов',
    admin_login: 'admin',
    admin_password: 'nilov2026',
  }
  for (const [k, v] of Object.entries(settings)) await db.setting.create({ data: { key: k, value: v } })

  // ---------- промокоды (content.json promos) ----------
  for (const p of (content.promos as any[])) {
    await db.promoCode.create({
      data: { code: p.code, type: p.type, value: p.value, min: p.min, label: p.label, active: !!p.active },
    })
  }

  const products = await db.product.count()
  const zoneCount = await db.deliveryZone.count()
  const upsellCount = await db.upsell.count()
  console.log(`\n✔ Готово: ${products} букетов, ${zoneCount} зон, ${upsellCount} апселлов`)
  if (broken.length) {
    console.log('⚠ БИТЫЕ ПУТИ К ФОТО:')
    broken.forEach(b => console.log('  ' + b))
    process.exitCode = 1
  } else {
    console.log('✔ Все пути к фото существуют')
  }
}

main().catch(e => { console.error(e); process.exit(1) }).finally(() => db.$disconnect())
