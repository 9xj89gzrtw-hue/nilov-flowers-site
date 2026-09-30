import { db } from "@/lib/db"
import { checkPassword, issueSession } from "@/lib/admin-auth"
import { clientKey, rateLimit } from "@/lib/rate-limit"

export async function POST(req: Request) {
  // Раунд 2 (критик 10, P1): антибрутфорс на логин
  if (!rateLimit(`login:${clientKey(req)}`, 10)) {
    return Response.json({ error: "Слишком много попыток входа — подождите минуту" }, { status: 429 })
  }
  try {
    const { password } = (await req.json()) as { password?: string }
    if (!password) return Response.json({ error: "Введите пароль" }, { status: 400 })
    const ok = await checkPassword(password)
    if (!ok) {
      await new Promise((r) => setTimeout(r, 600)) // защита от перебора
      return Response.json({ error: "Неверный пароль" }, { status: 401 })
    }
    await issueSession()
    return Response.json({ ok: true })
  } catch {
    return Response.json({ error: "Ошибка запроса" }, { status: 500 })
  }
}
