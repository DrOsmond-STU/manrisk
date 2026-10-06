// Crawler tautan: masuk sebagai peran tertentu, ikuti setiap tautan internal (BFS) dari dashboard,
// catat status HTTP, error JS/konsol, dan halaman yang tidak punya tautan keluar ke modul lain.
// node tests/e2e/crawl.mjs <email> <sandi> [maks]
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = 'http://127.0.0.1:8010';
const [email, pass = 'ManRisk#2026', maxArg = '600'] = process.argv.slice(2);
const MAX = Number(maxArg);
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 }, acceptDownloads: true });
const page = await ctx.newPage();
let cur = '';
const errors = [];
page.on('pageerror', (e) => errors.push(`[js] ${cur} ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error' && !/ERR_CERT|favicon/.test(m.text())) errors.push(`[console] ${cur} ${m.text().slice(0, 160)}`); });
await page.goto(`${BASE}/login`); await page.fill('#email', email); await page.fill('#pass', pass); await page.click('button[type=submit]');
await page.waitForURL(/dashboard|password|verify/, { timeout: 15000 });
const SKIP = /\/logout|\/login|\/password$|reset-password|\/download|\/export|format=|\/template|\.csv|\/minutes|\/pdf/;
const norm = (u) => { const x = new URL(u, BASE); if (x.origin !== BASE) return null; x.hash = ''; x.searchParams.delete('page'); return x.pathname + (x.search || ''); };
// Kelompok "modul" = segmen pertama path, agar halaman tanpa tautan ke modul lain terdeteksi
const mod = (p) => p.split('?')[0].split('/').filter(Boolean).slice(0, p.startsWith('/organization') || p.startsWith('/admin') || p.startsWith('/settings') || p.startsWith('/dashboard') ? 2 : 1).join('/');
const seen = new Set(['/dashboard']); const queue = ['/dashboard']; const bad = []; const deadEnds = []; const edges = new Map();
while (queue.length && seen.size <= MAX) {
  const url = queue.shift(); cur = url;
  let status = 0;
  try { const r = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 30000 }); status = r?.status() ?? 0; }
  catch (e) { if (/Download is starting/.test(e.message)) continue; bad.push(`${url} → ${e.message.split('\n')[0]}`); continue; }
  if (status >= 400) { bad.push(`${url} → HTTP ${status}`); continue; }
  const finalUrl = norm(page.url());
  if (finalUrl && finalUrl !== url && /\/login/.test(finalUrl)) { bad.push(`${url} → redirect ke login`); break; }
  const hrefs = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
  const out = new Set();
  for (const h of hrefs) { if (!h || h.startsWith('#') || h.startsWith('mailto:') || h.startsWith('javascript')) continue; const n = norm(h); if (!n || SKIP.test(n)) continue; out.add(n); }
  const myMod = mod(url); const other = [...out].filter((o) => mod(o) !== myMod);
  // tautan di luar sidebar navigasi (konten halaman)
  const content = await page.$$eval('main.content a[href]', (as) => as.map((a) => a.getAttribute('href'))).catch(() => []);
  const contentOther = [...new Set(content.map(norm).filter(Boolean))].filter((o) => mod(o) !== myMod && !SKIP.test(o));
  edges.set(url, contentOther.length);
  if (!/\?/.test(url) && contentOther.length === 0) deadEnds.push(url);
  for (const o of out) { const key = o.replace(/\/\d+(?=\/|$|\?)/g, '/{id}').replace(/=\d+/g, '={n}'); if (!seen.has(o) && ![...seen].some((s) => s.replace(/\/\d+(?=\/|$|\?)/g, '/{id}').replace(/=\d+/g, '={n}') === key && countKey(key) >= 3)) { seen.add(o); queue.push(o); } }
}
function countKey(key) { let n = 0; for (const s of seen) if (s.replace(/\/\d+(?=\/|$|\?)/g, '/{id}').replace(/=\d+/g, '={n}') === key) n++; return n; }
console.log(`ROLE ${email}: dikunjungi ${seen.size}, rusak ${bad.length}, error ${errors.length}`);
bad.forEach((b) => console.log('  BAD', b));
[...new Set(errors)].slice(0, 30).forEach((e) => console.log('  ERR', e));
console.log('  Halaman tanpa tautan konten ke modul lain:', deadEnds.join(', ') || '-');
await browser.close();
