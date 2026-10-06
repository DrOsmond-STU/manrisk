// Uji E2E keterkaitan antarmenu: setiap angka/tautan membawa ke daftar atau detail yang benar,
// dan angka di kartu sama dengan jumlah di halaman tujuan. node tests/e2e/journey.mjs [email] [sandi]
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = 'http://127.0.0.1:8010';
const [email = 'admin@manrisk.id', pass = 'ManRisk#2026'] = process.argv.slice(2);
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const errors = []; const steps = [];
page.on('pageerror', (e) => errors.push(`[js] ${page.url()} ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error' && !/ERR_CERT/.test(m.text())) errors.push(`[console] ${page.url()} ${m.text().slice(0, 160)}`); });
page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); });
process.on('exit', () => { console.log(steps.join('\n')); console.log(`\nLANGKAH: ${steps.filter((s) => s.startsWith('PASS')).length}/${steps.length} lulus · ERRORS: ${errors.length}`); errors.slice(0, 20).forEach((e) => console.log(' ', e)); });
const ok = (name, cond, extra = '') => steps.push(`${cond ? 'PASS' : 'FAIL'}  ${name}${extra ? ' — ' + extra : ''}`);
const go = async (u) => { await page.goto(BASE + u, { waitUntil: 'networkidle' }); };
const click = async (loc) => { await Promise.all([page.waitForLoadState('networkidle'), loc.first().click()]); await page.waitForLoadState('networkidle'); };
const path = () => decodeURIComponent(new URL(page.url()).pathname + new URL(page.url()).search);
const total = async () => Number((await page.locator('.page-h p, .ph-sub, header p').first().textContent().catch(() => '')).match(/(\d+) risiko terdaftar/)?.[1] ?? NaN);
const kpiValue = async (label) => Number((await page.locator('.kpi', { hasText: label }).first().locator('.k-v').textContent()).replace(/[^\d]/g, ''));

await go('/login'); await page.fill('#email', email); await page.fill('#pass', pass); await page.click('button[type=submit]');
await page.waitForURL(/dashboard|verify|password/, { timeout: 15000 });

// 1. Dashboard → register: angka KPI = jumlah daftar
await go('/dashboard');
for (const [label, expect] of [['Risiko aktif', 'status=active'], ['Tinggi & sangat tinggi', 'level=high,very_high'], ['Dalam penanganan', 'status=treating'], ['Risiko tanpa kontrol', 'no_controls=1']]) {
  await go('/dashboard');
  const v = await kpiValue(label);
  await click(page.locator('a.plain', { hasText: label }));
  const n = await total();
  ok(`Dashboard "${label}" → register`, path().includes(expect) && n === v, `kartu ${v}, daftar ${n}`);
}
await go('/dashboard');
await click(page.locator('a.plain', { hasText: 'Insiden terbuka' }));
ok('Dashboard "Insiden terbuka" → insiden status=open', path().includes('/incidents') && path().includes('status=open'));
await go('/dashboard');
await click(page.locator('a.plain', { hasText: 'KRI melewati ambang' }));
ok('Dashboard "KRI melewati ambang" → KRI tersaring', path().includes('/kris') && path().includes('status=breach'));
await go('/dashboard');
await click(page.locator('a.plain', { hasText: 'Action plan terlambat' }));
ok('Dashboard "Action plan terlambat" → AP overdue', path().includes('/action-plans') && path().includes('status=overdue'));

// 2. Register → detail → atribut menaut ke register tersaring
await go('/risks?status=active');
await click(page.locator('tbody tr.click'));
const riskUrl = path();
ok('Register → detail risiko', /^\/risks\/\d+/.test(riskUrl), riskUrl);
const riskId = riskUrl.match(/\/risks\/(\d+)/)[1];
for (const [dt, param] of [['Unit', 'unit_id='], ['Kategori', 'category_id='], ['Risk owner', 'owner_id=']]) {
  await go(riskUrl);
  await click(page.locator('dl.kv dt', { hasText: new RegExp(`^${dt}$`) }).locator('xpath=following-sibling::dd[1]').locator('a'));
  ok(`Detail risiko: ${dt} → register tersaring`, path().startsWith('/risks?') && path().includes(param));
}
// 3. Tab detail risiko → modul terkait (tersaring ke risiko ini)
for (const [tab, btn, expect] of [['Action plan', 'Buka di Action Plan', `/action-plans?risk_id=${riskId}`], ['Kontrol', 'Buka di Kontrol', `/controls?risk_id=${riskId}`], ['KRI', 'Kelola KRI risiko ini', `/kris?risk_id=${riskId}`], ['Insiden', 'Semua insiden risiko ini', `/incidents?risk_id=${riskId}`], ['Perbaikan', 'Buka di Perbaikan', `/improvements?risk_id=${riskId}`], ['Review', 'Buka di Risk Review', `/reviews?risk_id=${riskId}`], ['Dokumen', 'Buka di Dokumen', `/documents?subject_kind=risk&subject_id=${riskId}`], ['Versi & persetujuan', 'Buka di Persetujuan', `/approvals?risk_id=${riskId}`]]) {
  await go(riskUrl);
  await page.locator('.tabs button', { hasText: tab }).first().click();
  await click(page.locator('a', { hasText: btn }));
  ok(`Detail risiko tab ${tab} → ${expect.split('?')[0]}`, path() === expect, path());
}
// 4. Matriks dengan filter unit → register membawa filter yang sama
await go('/risks/matrix');
const unitOpt = await page.locator('.filters select').first().locator('option').nth(1).getAttribute('value');
await page.locator('.filters select').first().selectOption(unitOpt); await page.waitForLoadState('networkidle'); await page.waitForTimeout(400);
const cell = page.locator('.heat .cell:not(.zero), .cell:not(.zero)').first();
if (await cell.count()) {
  await cell.click();
  await click(page.locator('a', { hasText: 'Buka di register' }));
  ok('Matriks (unit) → sel → register dengan filter unit', path().includes(`unit_id=${unitOpt}`) && /l=\d&i=\d/.test(path()), path());
} else ok('Matriks (unit) → sel', true, 'unit tanpa risiko aktif');
// 5. Struktur organisasi → jumlah risiko = register (termasuk sub-unit)
await go('/organization/units');
const unitLink = page.locator('.tnode a.pill.run').first();
const unitCount = Number((await unitLink.textContent()).match(/\d+/)[0]);
await click(unitLink);
ok('Struktur organisasi → register unit (termasuk sub-unit)', path().includes('unit_id=') && (await total()) === unitCount, `kartu ${unitCount}, daftar ${await total()}`);
// 6. Pemetaan sasaran → +Risiko → form terisi sasaran
await go('/organization/objectives');
const objLink = page.locator('tbody tr').first().locator('a.code-link');
const objCount = Number(await objLink.textContent());
await click(objLink);
ok('Sasaran → register tersaring', path().includes('objective_id=') && (await total()) === objCount, `kartu ${objCount}, daftar ${await total()}`);
await go('/organization/objectives');
await click(page.locator('a', { hasText: '+ Risiko' }));
ok('Sasaran → "+ Risiko" → form identifikasi', path().startsWith('/risks/create?objective_id='));
// 7. Executive → kartu "Perlu eskalasi" → register
await go('/dashboard/executive');
const esc = await kpiValue('Perlu eskalasi');
await click(page.locator('a.plain', { hasText: 'Perlu eskalasi' }));
ok('Executive "Perlu eskalasi" → register', path().includes('evaluation=escalate,critical') && (await total()) === esc, `kartu ${esc}, daftar ${await total()}`);
// 8. Peringatan → Buka → halaman subjek (bukan tetap di /alerts)
await go('/alerts');
const openBtn = page.locator('a', { hasText: /^Buka$/ });
if (await openBtn.count()) {
  await click(openBtn);
  ok('Peringatan → Buka → halaman subjek', !path().startsWith('/alerts'), path());
} else ok('Peringatan → Buka', true, 'tidak ada peringatan aktif');
// 9. Kerangka ISO → modul aplikasi
await go('/framework');
await click(page.locator('td a.pill.run'));
ok('Kerangka ISO 31000 → modul', !path().startsWith('/framework'), path());
// 10. Persetujuan → kode → fokus pengajuan
await go('/approvals');
await page.locator('.tabs button', { hasText: 'Riwayat' }).click().catch(() => {});
const ap = page.locator('a.code-link').first();
if (await ap.count()) { await click(ap); ok('Persetujuan → kode → fokus', path().startsWith('/approvals?id=')); }
await browser.close();
