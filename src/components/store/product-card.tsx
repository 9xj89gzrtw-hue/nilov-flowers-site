"use client"

import { useState } from "react"
import Image from "next/image"
import { motion } from "framer-motion"
import { Eye, ShoppingBag, Zap } from "lucide-react"
import { toast } from "sonner"
import type { Product } from "@/lib/types"
import { money, moneyShort, splitPrice } from "@/lib/types"
import { useStore } from "@/lib/store"

export function ProductCard({ product, index, onOpen }: { product: Product; index: number; onOpen: () => void }) {
  const addProduct = useStore((s) => s.addProduct)
  const setOneClick = useStore((s) => s.setOneClick)
  const [hover, setHover] = useState(false)

  const photos = product.photos.length ? product.photos : ["/img/products/gen1.webp"]
  const second = photos[1] || null
  const discount = product.oldPrice && product.oldPrice > product.price
    ? Math.round((1 - product.price / product.oldPrice) * 100)
    : 0
  const isSturdy = product.tags.some((t) => t.toLowerCase().includes("стойк"))

  // Раунд 1 (критики 1 и 3, P1): бейджи-«−N%»/«До N ₽» дублируют вычисляемую скидку и чипсы цены — не рендерим
  const isJunkBadge = (b: string) => /^−?\d+\s*%$/.test(b) || /^до\s+\d/i.test(b)

  const badges = [
    discount > 0 && { text: `−${discount}%`, cls: "bg-powder text-berry" },
    (product.badge === "Хит" || product.tags.includes("хит")) && { text: "Хит", cls: "bg-hit text-pine-deep" },
    product.badge && !isJunkBadge(product.badge) && !["Хит"].includes(product.badge) && { text: product.badge, cls: "bg-white/92 text-pine border border-pine/20" },
    isSturdy && { text: "Стойкие до 14 дней", cls: "bg-pine/92 text-cream" },
  ].filter(Boolean) as { text: string; cls: string }[]

  return (
    <motion.article
      initial={{ opacity: 0, y: 14 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "40px" }}
      transition={{ duration: 0.4, delay: Math.min(index * 0.03, 0.24) }}
      className="group flex flex-col"
      aria-label={`Букет ${product.name}, ${money(product.price)}`}
    >
      <button
        onClick={onOpen}
        onMouseEnter={() => setHover(true)}
        onMouseLeave={() => setHover(false)}
        className="relative block w-full overflow-hidden rounded-2xl bg-secondary text-left"
        aria-label={`Открыть букет ${product.name}`}
      >
        <span className="relative block aspect-[3/4]">
          <Image
            src={photos[0]}
            alt={`Букет «${product.name}» — ${product.sub || "состав уточните у флориста"}`}
            fill
            sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 20vw"
            className={`object-cover transition-all duration-500 ease-out group-hover:scale-[1.05] ${
              second && hover ? "opacity-0" : "opacity-100"
            }`}
          />
          {second && (
            <Image
              src={second}
              alt=""
              aria-hidden
              fill
              sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 20vw"
              className={`object-cover transition-all duration-500 ease-out group-hover:scale-[1.05] ${
                hover ? "opacity-100" : "opacity-0"
              }`}
            />
          )}
          {badges.length > 0 && (
            <span className="absolute left-2.5 top-2.5 flex max-w-[calc(100%-20px)] flex-wrap gap-1.5">
              {badges.slice(0, 2).map((b, i) => (
                <span
                  key={i}
                  className={`rounded-full px-2.5 py-1 text-[11px] font-grotesk font-bold leading-none ${b.cls}`}
                >
                  {b.text}
                </span>
              ))}
            </span>
          )}
          <span className="absolute bottom-2.5 right-2.5 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-pine opacity-0 shadow-md transition-all duration-300 group-hover:opacity-100 md:h-10 md:w-10">
            <Eye className="h-4 w-4" aria-hidden />
          </span>
        </span>
      </button>

      <div className="flex flex-1 flex-col pt-3">
        <h3 className="text-[15px] font-medium leading-snug text-foreground sm:min-h-[44px]">{product.name}</h3>
        <p className="mt-1 line-clamp-1 text-[12.5px] text-muted-foreground">
          {product.size ? `${product.size} · ` : ""}
          {product.composition[0] || product.sub || "Состав уточняется"}
        </p>

        <div className="mt-2.5 flex items-baseline gap-2">
          <span className="font-grotesk text-[19px] font-extrabold leading-none text-foreground tnum sm:text-[21px]">
            {money(product.price)}
          </span>
          {product.oldPrice && product.oldPrice > product.price && (
            <span className="font-grotesk text-[13px] text-muted-foreground line-through tnum">
              {moneyShort(product.oldPrice)}
            </span>
          )}
        </div>
        <p className="mt-1 text-[12px] font-medium text-grass">
          Сплит: от {money(splitPrice(product.price))}/мес
        </p>

        <div className="mt-3 flex flex-col gap-2 sm:mt-auto sm:pt-3">
          <button
            onClick={() => {
              addProduct({ id: product.id, name: product.name, price: product.price, photo: photos[0] })
              toast.success(`«${product.name}» — в корзине`)
            }}
            className="h-11 w-full rounded-full bg-pine font-grotesk text-[13.5px] font-bold text-primary-foreground transition-colors hover:bg-pine-deep min-h-[44px]"
          >
            <span className="inline-flex items-center gap-2">
              <ShoppingBag className="h-4 w-4" aria-hidden />
              В корзину
            </span>
          </button>
          <button
            onClick={() => setOneClick({ id: product.id, name: product.name, price: product.price, photo: photos[0] })}
            className="h-11 w-full rounded-full border border-pine/25 bg-white font-grotesk text-[13.5px] font-bold text-pine transition-colors hover:bg-powder hover:border-berry/40 hover:text-berry min-h-[44px]"
          >
            <span className="inline-flex items-center gap-2">
              <Zap className="h-4 w-4" aria-hidden />
              Купить в 1 клик
            </span>
          </button>
        </div>
      </div>
    </motion.article>
  )
}
