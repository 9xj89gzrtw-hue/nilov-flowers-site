import { checkPassword, issueSession } from "@/lib/admin-auth"

export async function POST(req: Request) {
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
