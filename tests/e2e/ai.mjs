// Uji UI AI Assistant (panel di setiap halaman) dan AI Analitik lewat browser nyata.
// node tests/e2e/ai.mjs [folder-tangkapan]
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = 'http://127.0.0.1:8010';
const OUT = process.argv[2] || '/tmp';
const browser = await chromium.launch();
const errors = [];
const log = (m) => console.log('✔', m);
const mk = async (viewport = { width: 1400, height: 900 }) => {
  const ctx = await browser.newContext({ viewport });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`[pageerror] ${page.url()} ${e.message}`));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|ERR_CERT/.test(m.text())) errors.push(`[console] ${page.url()} ${m.text().slice(0, 200)}`); });
  page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); });
  return page;
};
const login = async (page, email) => { await page.goto(`${BASE}/login`); await page.fill('#email', email); await page.fill('#pass', 'ManRisk#2026'); await page.click('button[type=submit]'); await page.waitForURL(/dashboard/, { timeout: 15000 }); };
const lastAnswer = (page) => page.locator('.ai-msg.assistant .ai-answer').last();
// /ai/assist dibatasi 20 permintaan/menit per pengguna
const pace = () => new Promise((r) => setTimeout(r, 3100));
const askFirst = async (page) => {
  await pace();
  await page.locator('.ai-fab').click();
  await page.locator('.ai-intro .ai-chip').first().waitFor({ timeout: 10000 });
  const label = await page.locator('.ai-drawer .drawer-h h3').textContent();
  await page.locator('.ai-intro .ai-chip').first().click();
  await page.locator('.ai-msg.assistant .ai-meta').last().waitFor({ timeout: 20000 });
  const txt = await lastAnswer(page).textContent();
  if (!txt || txt.length < 40) throw new Error(`jawaban kosong di ${page.url()}`);
  return label;
};

const page = await mk();
await login(page, 'manager@manrisk.id');

// 1) Risk Register: aksi cepat + pertanyaan bebas + tautan kode
await page.goto(`${BASE}/risks`);
log(`panel ${await askFirst(page)} di /risks`);
await pace();
await page.fill('.ai-input textarea', 'Unit mana yang paling perlu perhatian?');
await page.keyboard.press('Enter');
await page.locator('.ai-msg.assistant .ai-meta').nth(1).waitFor({ timeout: 20000 });
const links = await page.locator('.ai-answer .md a[href^="/risks/"]').count();
log(`pertanyaan bebas dijawab; ${links} kode risiko tertaut`);
await page.screenshot({ path: `${OUT}/ai-panel-risks.png` });
await page.keyboard.press('Escape');

// 2) Halaman detail: risiko, action plan, kontrol, insiden
// id objek pertama diambil dari props Inertia halaman daftar (baris tabel memakai klik, bukan tautan)
for (const [list, prop] of [['/risks', 'risks'], ['/action-plans', 'plans'], ['/controls', 'controls'], ['/incidents', 'incidents']]) {
  await page.goto(BASE + list);
  const id = await page.evaluate((k) => { const p = JSON.parse(document.getElementById('app').dataset.page).props; const v = p[k]; return (v?.data || v || [])[0]?.id; }, prop);
  const href = id ? `${list}/${id}` : null;
  if (!href) { errors.push(`tidak ada tautan detail di ${list}`); continue; }
  await page.goto(BASE + href);
  log(`panel "${await askFirst(page)}" di ${href}`);
  if (list === '/risks') await page.screenshot({ path: `${OUT}/ai-panel-risk-detail.png` });
}

// 3) Halaman lain: setiap panel menjawab aksi pertamanya
const pages = ['/dashboard', '/dashboard/executive', '/kris', '/alerts', '/context', '/criteria', '/risks/matrix', '/risks/evaluation', '/risks/residual', '/reviews', '/improvements', '/incidents/losses',
  '/organization/objectives', '/organization/units', '/framework', '/rmi', '/approvals', '/reports', '/documents', '/admin/audit', '/risks/create'];
for (const p of pages) {
  await page.goto(BASE + p);
  log(`panel "${await askFirst(page)}" di ${p}`);
}

// 4) AI Analitik: contoh → dashboard, ubah jenis grafik, tambah widget, simpan, buka ulang
await page.goto(`${BASE}/ai/analytics`);
await page.locator('.ai-chip', { hasText: 'Dashboard risiko eksekutif' }).click();
await page.locator('.ai-w').first().waitFor({ timeout: 20000 });
await page.waitForTimeout(800);
const n = await page.locator('.ai-w').count();
const canvases = await page.locator('.ai-w canvas').count();
log(`dashboard eksekutif: ${n} widget, ${canvases} grafik`);
await page.screenshot({ path: `${OUT}/ai-analytics-exec.png`, fullPage: true });
const firstChart = page.locator('.ai-w:has(canvas)').first();
await firstChart.locator('select.sel').selectOption('bar');
await page.waitForTimeout(800);
log('jenis grafik diubah');
await page.fill('textarea[aria-label="Permintaan dashboard"]', 'tren insiden per bulan 12 bulan terakhir dan action plan terlambat per unit');
await page.locator('button', { hasText: 'Tambahkan ke dashboard ini' }).click();
await page.waitForFunction((c) => document.querySelectorAll('.ai-w').length > c, n, { timeout: 20000 });
log(`widget ditambahkan: ${await page.locator('.ai-w').count()}`);
await page.fill('.ai-title', 'Dashboard uji E2E');
await page.locator('label:has-text("Bagikan ke organisasi") input').check();
await page.locator('button', { hasText: 'Simpan dashboard' }).click();
await page.locator('.toast', { hasText: 'Dashboard disimpan' }).waitFor({ timeout: 8000 });
const url = page.url();
log(`disimpan: ${url}`);
await page.goto(url);
await page.locator('.ai-w canvas').first().waitFor({ timeout: 20000 });
log(`dibuka ulang dari tautan: ${await page.locator('.ai-w').count()} widget`);
await page.fill('textarea[aria-label="Permintaan dashboard"]', '10 risiko dengan skor residual tertinggi');
await page.locator('button', { hasText: 'Buat dashboard' }).click();
await page.locator('.ai-w table').first().waitFor({ timeout: 20000 });
log(`tabel daftar: ${await page.locator('.ai-w table tbody tr').count()} baris`);
await page.screenshot({ path: `${OUT}/ai-analytics-table.png`, fullPage: true });

// 5) Ponsel: panel tetap terbaca
const m = await mk({ width: 390, height: 844 });
await login(m, 'officer@manrisk.id');
await m.goto(`${BASE}/action-plans`);
log(`ponsel: panel "${await askFirst(m)}" (officer)`);
await m.screenshot({ path: `${OUT}/ai-panel-mobile.png` });
const overflow = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
if (overflow) errors.push('ponsel: halaman menggulir horizontal');

// 6) Auditor: tidak ada panel
const a = await mk();
await login(a, 'auditor@manrisk.id');
await a.goto(`${BASE}/risks`);
if (await a.locator('.ai-fab').count()) errors.push('auditor melihat tombol AI');
else log('auditor tanpa panel AI');

await browser.close();
if (errors.length) { console.log('\n✘ GALAT:\n' + errors.join('\n')); process.exit(1); }
console.log('\nSemua langkah UI AI lolos.');
