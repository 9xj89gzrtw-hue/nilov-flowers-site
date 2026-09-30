/**
 * Bun static + content API server for nilov-flowers-site (v2026).
 * Port 8123. Replaces the PHP built-in server for the local sandbox
 * (PHP is unavailable in this sandbox; Bun is the runtime).
 *
 * Routes:
 *   GET  /                        -> v2026/index.html
 *   GET  /v2026/admin.html        -> v2026/admin.html
 *   GET  /v2026/*                 -> v2026/* static files (css, js, content.json)
 *   GET  /img/*                   -> img/* (repo root images)
 *   GET  /css|js|fonts|partials/* -> repo-root static folders
 *   GET  /api/content             -> content.json
 *   POST /api/content             -> write content.json (admin, token-guarded)
 *   POST /api/upload              -> image upload to img/uploads/ (admin)
 *   GET  /api/health              -> {ok:true}
 *
 * Admin token: read from env ADMIN_TOKEN or file db/admin-token.txt.
 * Requests with header X-Admin-Token matching the token may write.
 */
import { exists, mkdir, writeFile, readFile } from "node:fs/promises";
import { createReadStream, statSync } from "node:fs";
import { join, extname, normalize } from "node:path";
import { createHash, randomBytes } from "node:crypto";

const ROOT = "/home/z/nilov-flowers-site";
const V2026 = join(ROOT, "v2026");
const PORT = 8123;
const CONTENT = join(V2026, "content.json");
const UPLOAD_DIR = join(ROOT, "img", "uploads");

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".mjs": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".webp": "image/webp",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".gif": "image/gif",
  ".avif": "image/avif",
  ".ico": "image/x-icon",
  ".woff": "font/woff",
  ".woff2": "font/woff2",
  ".ttf": "font/ttf",
  ".otf": "font/otf",
  ".txt": "text/plain; charset=utf-8",
  ".xml": "application/xml; charset=utf-8",
  ".webmanifest": "application/manifest+json",
  ".map": "application/json; charset=utf-8",
};

async function adminToken() {
  // Prefer content.json admin.password as the write token (so admin.html login
  // = enter password -> compare to content.json -> use as X-Admin-Token).
  try {
    const j = JSON.parse(await readFile(CONTENT, "utf8"));
    if (j && j.admin && j.admin.password) return String(j.admin.password);
  } catch {}
  // fallback: env or file
  try {
    const t = process.env.ADMIN_TOKEN;
    if (t) return t.trim();
  } catch {}
  try {
    const f = join(ROOT, "db", "admin-token.txt");
    const s = (await readFile(f, "utf8")).trim();
    if (s) return s;
  } catch {}
  // first-run bootstrap: create a token
  const tok = randomBytes(18).toString("hex");
  try {
    await mkdir(join(ROOT, "db"), { recursive: true });
    await writeFile(join(ROOT, "db", "admin-token.txt"), tok, "utf8");
    console.log("[admin] bootstrapped token -> db/admin-token.txt  (", tok, ")");
  } catch (e) {
    console.error("[admin] token bootstrap failed", e);
  }
  return tok;
}

function safePath(base, rel) {
  // rel is the path REMAINDER (already stripped of the route prefix), e.g. "/editorial/x.webp"
  let p = decodeURIComponent((rel || "").split("?")[0]);
  // strip leading slashes so join treats it as relative
  p = p.replace(/^\/+/, "");
  // block traversal
  if (p.includes("..")) return null;
  const full = normalize(join(base, p));
  if (!full.startsWith(normalize(base))) return null;
  return full;
}

async function serveStatic(req, base, urlPath, fallback) {
  const f = safePath(base, urlPath);
  if (!f) return new Response("Forbidden", { status: 403 });
  try {
    const s = statSync(f);
    if (s.isDirectory()) {
      const idx = join(f, "index.html");
      try { statSync(idx); return await fileResponse(idx); } catch {}
      return new Response("403", { status: 403 });
    }
    return await fileResponse(f);
  } catch {
    if (fallback) return fallback();
    return new Response("Not Found", { status: 404 });
  }
}

async function fileResponse(f) {
  const ext = extname(f).toLowerCase();
  const mime = MIME[ext] || "application/octet-stream";
  const buf = await readFile(f);
  const headers = { "Content-Type": mime, "Cache-Control": "no-cache" };
  return new Response(buf, { headers });
}

const server = Bun.serve({
  port: PORT,
  development: true,
  async fetch(req) {
    const url = new URL(req.url);
    const p = url.pathname;
    const method = req.method;
    const TOKEN = await adminToken();

    // CORS + preflight
    if (method === "OPTIONS") {
      return new Response(null, {
        status: 204,
        headers: {
          "Access-Control-Allow-Origin": "*",
          "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
          "Access-Control-Allow-Headers": "Content-Type, X-Admin-Token",
        },
      });
    }

    // ---- API ----
    if (p === "/api/health") {
      return Response.json({ ok: true, port: PORT, t: Date.now() });
    }

    if (p === "/api/content") {
      if (method === "GET") {
        try {
          const j = await readFile(CONTENT, "utf8");
          return new Response(j, { headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-cache" } });
        } catch (e) {
          return Response.json({ error: "no content" }, { status: 500 });
        }
      }
      if (method === "POST") {
        const tok = req.headers.get("x-admin-token");
        if (tok !== TOKEN) return Response.json({ error: "unauthorized" }, { status: 401 });
        try {
          const body = await req.text();
          JSON.parse(body); // validate
          // backup
          try {
            const prev = await readFile(CONTENT, "utf8");
            await writeFile(join(V2026, "content.json.bak"), prev, "utf8");
          } catch {}
          await writeFile(CONTENT, body, "utf8");
          return Response.json({ ok: true, size: body.length });
        } catch (e) {
          return Response.json({ error: String(e) }, { status: 400 });
        }
      }
    }

    if (p === "/api/upload") {
      if (method !== "POST") return Response.json({ error: "POST only" }, { status: 405 });
      const tok = req.headers.get("x-admin-token");
      if (tok !== TOKEN) return Response.json({ error: "unauthorized" }, { status: 401 });
      try {
        const form = await req.formData();
        const file = form.get("file");
        if (!file || typeof file === "string") return Response.json({ error: "no file" }, { status: 400 });
        const ext = (extname(file.name) || "").toLowerCase() || ".webp";
        const allowed = [".webp", ".jpg", ".jpeg", ".png", ".avif", ".gif"];
        if (!allowed.includes(ext)) return Response.json({ error: "bad ext" }, { status: 400 });
        await mkdir(UPLOAD_DIR, { recursive: true });
        const name = "up-" + Date.now() + "-" + randomBytes(3).toString("hex") + ext;
        const dest = join(UPLOAD_DIR, name);
        const buf = await file.arrayBuffer();
        await writeFile(dest, Buffer.from(buf));
        return Response.json({ ok: true, url: "/img/uploads/" + name, name, size: buf.byteLength });
      } catch (e) {
        return Response.json({ error: String(e) }, { status: 500 });
      }
    }

    if (p === "/api/img-list") {
      // list all images in img/ (recursive, shallow-ish) for the admin library picker
      try {
        const { readdirSync } = await import("node:fs");
        const base = join(ROOT, "img");
        const out = [];
        const dirs = ["editorial", "uploads", "products", "icons"];
        for (const d of dirs) {
          try {
            const files = readdirSync(join(base, d));
            for (const f of files) {
              if (/\.(webp|jpg|jpeg|png|avif|gif|svg)$/i.test(f)) {
                out.push({ url: "/img/" + d + "/" + f, name: f, dir: d });
              }
            }
          } catch {}
        }
        return Response.json({ images: out });
      } catch (e) {
        return Response.json({ images: [], error: String(e) });
      }
    }

    if (p.startsWith("/api/")) {
      return Response.json({ error: "unknown api" }, { status: 404 });
    }

    // ---- Static routes ----
    // Admin
    if (p === "/v2026/admin" || p === "/admin" || p === "/v2026/admin.html") {
      const f = join(V2026, "admin.html");
      try { statSync(f); return await fileResponse(f); } catch {
        return new Response("admin.html not built yet", { status: 404, headers: { "Content-Type": "text/plain; charset=utf-8" } });
      }
    }

    // v2026/* (css, js, content.json, admin.html)
    if (p.startsWith("/v2026/")) {
      return await serveStatic(req, V2026, p.slice(6)); // strip "/v2026"
    }

    // root index -> v2026/index.html
    if (p === "/" || p === "/index.html" || p === "/index.php") {
      const f = join(V2026, "index.html");
      try { statSync(f); return await fileResponse(f); } catch {
        return new Response("v2026/index.html not found", { status: 404 });
      }
    }

    // content.json at root (storefront fetches 'content.json' relative to /)
    // -> serve v2026/content.json so admin edits reflect live on the storefront.
    if (p === "/content.json" || p === "/v2026/content.json") {
      try {
        const j = await readFile(CONTENT, "utf8");
        return new Response(j, { headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-cache" } });
      } catch {
        return new Response("{}", { status: 500 });
      }
    }

    // img/* (repo root images: editorial, uploads, favicon, etc.)
    if (p.startsWith("/img/")) {
      return await serveStatic(req, join(ROOT, "img"), p.slice(4)); // strip "/img"
    }
    // css, js, fonts, partials, includes (repo-root folders)
    if (p.startsWith("/css/") || p.startsWith("/js/") || p.startsWith("/fonts/") || p.startsWith("/partials/") || p.startsWith("/includes/")) {
      return await serveStatic(req, ROOT, p);
    }
    // favicon / robots / manifest / sw.js / sitemap
    if (["/favicon.svg", "/favicon.ico", "/robots.txt", "/manifest.webmanifest", "/sw.js", "/sitemap.xml", "/sitemap.php", "/offline.html"].includes(p)) {
      return await serveStatic(req, ROOT, p);
    }

    // SPA fallback for client-side routes (product, category, etc. if used)
    return new Response("Not Found: " + p, { status: 404, headers: { "Content-Type": "text/plain; charset=utf-8" } });
  },
  error(err) {
    console.error("[server] error", err);
    return new Response("Server Error: " + String(err), { status: 500 });
  },
});

console.log(`[nilov-site] Bun server on http://127.0.0.1:${PORT}  (root=${ROOT})`);
export default server;
