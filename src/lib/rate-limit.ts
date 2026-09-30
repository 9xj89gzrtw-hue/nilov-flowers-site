// Простой in-memory rate-limit для публичных POST-роутов (критик 5, P2).
// Достаточно для одного процесса Node ≤1 ГБ RAM; окно 60 секунд.
const buckets = new Map<string, number[]>()

export function rateLimit(key: string, limit: number, windowMs = 60_000): boolean {
  const now = Date.now()
  const hits = (buckets.get(key) || []).filter((t) => now - t < windowMs)
  if (hits.length >= limit) {
    buckets.set(key, hits)
    return false
  }
  hits.push(now)
  buckets.set(key, hits)
  // чистка «мёртвых» корзин, чтобы Map не тек
  if (buckets.size > 5000) {
    for (const [k, v] of buckets) {
      if (v.every((t) => now - t > windowMs)) buckets.delete(k)
    }
  }
  return true
}

export function clientKey(req: Request): string {
  const fwd = req.headers.get("x-forwarded-for") || ""
  return fwd.split(",")[0].trim() || "local"
}
