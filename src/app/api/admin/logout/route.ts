import { clearSession } from "@/lib/admin-auth"

export async function POST() {
  await clearSession()
  return Response.json({ ok: true })
}
