import { isAdmin, unauthorized } from "@/lib/admin-auth"
import { readdir } from "node:fs/promises"
import path from "node:path"

// Библиотека изображений для админки (загруженные + каталог товаров)
export async function GET() {
  if (!(await isAdmin())) return unauthorized()
  const urls: string[] = []

  const uploadsDir = path.join(process.cwd(), "img", "uploads")
  try {
    for (const f of await readdir(uploadsDir)) {
      if (/\.(jpe?g|png|webp)$/i.test(f)) urls.push(`/img/uploads/${f}`)
    }
  } catch {}

  const productsDir = path.join(process.cwd(), "img", "products")
  try {
    for (const f of await readdir(productsDir)) {
      if (f.endsWith(".webp")) urls.push(`/img/products/${f}`)
    }
  } catch {}

  return Response.json({ images: urls })
}
