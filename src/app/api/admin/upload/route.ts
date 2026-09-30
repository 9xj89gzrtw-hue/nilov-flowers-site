import { isAdmin, unauthorized } from "@/lib/admin-auth"
import { randomUUID } from "node:crypto"
import { writeFile, mkdir } from "node:fs/promises"
import path from "node:path"

// Загрузка фото товара (formData: file)
const MAX_SIZE = 8 * 1024 * 1024
const ALLOWED: Record<string, string> = {
  "image/jpeg": "jpg",
  "image/png": "png",
  "image/webp": "webp",
}

export async function POST(req: Request) {
  if (!(await isAdmin())) return unauthorized()
  try {
    const form = await req.formData()
    const file = form.get("file")
    if (!(file instanceof File)) return Response.json({ error: "Файл не передан" }, { status: 400 })
    if (file.size > MAX_SIZE) return Response.json({ error: "Файл больше 8 МБ" }, { status: 400 })
    const ext = ALLOWED[file.type]
    if (!ext) return Response.json({ error: "Только JPG, PNG или WebP" }, { status: 400 })

    const dir = path.join(process.cwd(), "img", "uploads")
    await mkdir(dir, { recursive: true })
    const name = `${randomUUID().slice(0, 8)}.${ext}`
    await writeFile(path.join(dir, name), Buffer.from(await file.arrayBuffer()))
    return Response.json({ ok: true, url: `/img/uploads/${name}` })
  } catch (e) {
    console.error("upload", e)
    return Response.json({ error: "Не удалось загрузить файл" }, { status: 500 })
  }
}
