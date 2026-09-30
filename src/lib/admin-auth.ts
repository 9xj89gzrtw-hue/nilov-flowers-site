import { createHash, timingSafeEqual } from "node:crypto"
import { cookies } from "next/headers"
import { db } from "@/lib/db"

const COOKIE = "nf_admin"
const SECRET = "nilov-flowers-s2-admin-session"

export async function getAdminPassword(): Promise<string> {
  const row = await db.setting.findUnique({ where: { key: "admin_password" } })
  return row?.value || "nilov2026"
}

function tokenFor(password: string): string {
  return createHash("sha256").update(password + SECRET).digest("hex")
}

export async function checkPassword(password: string): Promise<boolean> {
  const expected = await getAdminPassword()
  const a = Buffer.from(password)
  const b = Buffer.from(expected)
  if (a.length !== b.length) return false
  return timingSafeEqual(a, b)
}

export async function issueSession(): Promise<void> {
  const jar = await cookies()
  jar.set(COOKIE, tokenFor(await getAdminPassword()), {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: 60 * 60 * 24 * 30,
  })
}

export async function clearSession(): Promise<void> {
  const jar = await cookies()
  jar.delete(COOKIE)
}

export async function isAdmin(): Promise<boolean> {
  const jar = await cookies()
  const token = jar.get(COOKIE)?.value
  if (!token) return false
  return token === tokenFor(await getAdminPassword())
}

export function unauthorized() {
  return Response.json({ error: "unauthorized" }, { status: 401 })
}
