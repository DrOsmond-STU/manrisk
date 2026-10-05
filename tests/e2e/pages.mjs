// Uji E2E: login, buka tiap halaman, tangkap error konsol/JS, dan uji alur CRUD utama lewat UI
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = 'http://127.0.0.1:8010';
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(`[pageerror] ${page.url()} ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error') errors.push(`[console] ${page.url()} ${m.text().slice(0, 200)}`); });
page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); });
const login = async (email, pass) => { await page.goto(`${BASE}/login`); await page.fill('#email', email); await page.fill('#pass', pass); await page.click('button[type=submit]'); await page.waitForURL(/dashboard|password/, { timeout: 15000 }); };
await login(process.argv[2] || 'admin@manrisk.id', process.argv[3] || 'ManRisk#2026');
const urls = ['/import/risk', '/import/kri', '/documents?history=1', '/action-plans?mine=1', '/risks?mode=residual&l=3&i=3', '/risks?no_controls=1', '/dashboard', '/dashboard/executive', '/organization/units', '/organization/objectives', '/context', '/criteria', '/risks', '/risks/create', '/risks/1', '/risks/1/edit', '/risks/matrix', '/risks/evaluation', '/risks/residual', '/approvals', '/controls', '/controls/1', '/action-plans', '/action-plans/1', '/kris', '/incidents', '/incidents/1', '/incidents/losses', '/reviews', '/documents', '/improvements', '/framework', '/alerts', '/reports', '/ai', '/profile', '/settings/organization', '/admin/users', '/admin/audit', '/password'];
const results = [];
for (const u of urls) {
  const before = errors.length;
  const res = await page.goto(BASE + u, { waitUntil: 'networkidle' });
  const h1 = await page.locator('h1').first().textContent().catch(() => '');
  results.push(`${u.padEnd(26)} ${res.status()} ${(h1 || '').trim().slice(0, 50)} ${errors.length > before ? '⚠ ' + (errors.length - before) + ' err' : ''}`);
}
console.log(results.join('\n'));
if (process.argv[4] === 'shots') { for (const u of ['/dashboard', '/risks', '/risks/1', '/risks/matrix', '/kris', '/approvals', '/admin/users']) { await page.goto(BASE + u, { waitUntil: 'networkidle' }); await page.screenshot({ path: `/tmp/shot${u.replace(/\//g, '_')}.png`, fullPage: false }); } }
console.log('ERRORS:', errors.length); errors.slice(0, 20).forEach((e) => console.log(' ', e));
await browser.close();
