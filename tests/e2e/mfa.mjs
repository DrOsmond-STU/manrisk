// Uji E2E MFA via email: konfigurasi SMTP, email uji, kebijakan wajib MFA, login dua langkah,
// perangkat tepercaya, kode pemulihan, aktivasi/nonaktif dari Profil.
// Jalankan bersama server SMTP penampung yang menulis ke berkas: node tests/e2e/mfa.mjs <mail.log> <smtp-port>
import { readFileSync, writeFileSync } from 'node:fs';
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = 'http://127.0.0.1:8010';
const MAILLOG = process.argv[2];
const SMTP_PORT = process.argv[3] || '2526';
const PASS = process.argv[4] || 'ManRisk#2026';
const browser = await chromium.launch();
const errors = [];
const steps = [];
process.on('exit', () => { console.log(steps.join('\n')); console.log('ERRORS:', errors.length); errors.slice(0, 20).forEach((e) => console.log(' ', e)); });
const ok = (name, cond, extra = '') => { steps.push(`${cond ? 'PASS' : 'FAIL'}  ${name}${extra ? ' — ' + extra : ''}`); };
const newPage = async () => {
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`[pageerror] ${page.url()} ${e.message}`));
  page.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('ERR_CERT')) errors.push(`[console] ${page.url()} ${m.text().slice(0, 200)}`); });
  page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); });
  return page;
};
let base = 0;
const allMails = () => { try { return readFileSync(MAILLOG, 'utf8').split('=====END=====').filter((m) => m.trim()); } catch { return []; } };
const mails = () => allMails().slice(base);
const decode = (m) => m.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
const lastCode = (to) => { const m = allMails().map(decode).reverse().find((x) => x.includes(`To: ${to}`) && /letter-spacing:8px/.test(x)); return m?.match(/>(\d{6})<\/span>/)?.[1]; };
const field = (page, label) => page.locator('.field', { hasText: label }).locator('input, select').first();
const flash = async (page) => (await page.locator('.toast, .alert-box, [role=status], [role=alert]').allTextContents()).join(' | ');
const login = async (page, email) => { await page.goto(`${BASE}/login`); await page.fill('#email', email); await page.fill('#pass', PASS); await page.click('button[type=submit]'); await page.waitForURL(/dashboard|password|login\/verify/, { timeout: 15000 }); };
const loginFull = async (page, email) => { await login(page, email); if (page.url().includes('/login/verify')) { await page.fill('#code', lastCode(email)); await page.click('button:has-text("Verifikasi")'); await page.waitForURL(/dashboard|password/, { timeout: 15000 }); } };
const logout = async (page) => { await page.evaluate(async () => { const t = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''); await fetch('/logout', { method: 'POST', headers: { 'X-XSRF-TOKEN': t } }); }); };

base = allMails().length;
// 1. Super Admin mengatur SMTP
const a = await newPage();
await loginFull(a, 'admin@manrisk.id');
await a.goto(`${BASE}/admin/mail`, { waitUntil: 'networkidle' });
await a.locator('label.chk input[type=checkbox]').check();
await field(a, 'Host SMTP').fill('127.0.0.1');
await a.locator('.chips-sel label', { hasText: 'Tanpa enkripsi' }).click();
await field(a, 'Port').fill(SMTP_PORT);
await field(a, 'Nama pengguna SMTP').fill('noreply@manrisk.id');
await field(a, 'Sandi SMTP').fill('SandiSmtp#1');
await field(a, 'Alamat pengirim').fill('noreply@manrisk.id');
await a.click('button:has-text("Simpan")');
await a.waitForLoadState('networkidle');
ok('Simpan pengaturan SMTP', (await a.locator('.pill', { hasText: 'Siap mengirim' }).count()) > 0, await flash(a));
await field(a, 'Kirim ke').fill('uji@manrisk.id');
await a.click('button:has-text("Kirim email uji")');
await a.waitForTimeout(1500);
ok('Email uji terkirim lewat SMTP', mails().some((m) => m.includes('To: uji@manrisk.id') && m.includes('Email uji konfigurasi SMTP')), await flash(a));

// 2. Kebijakan: wajib MFA untuk Super Admin & Risk Administrator, perangkat tepercaya 30 hari
await a.goto(`${BASE}/settings/organization`, { waitUntil: 'networkidle' });
const mfaCard = a.locator('form.card', { hasText: 'Verifikasi dua langkah' });
for (const r of ['Super Admin', 'Risk Administrator']) { const l = mfaCard.locator('.chips-sel label', { hasText: new RegExp(`^\\s*${r}\\s*$`) }); if (!(await l.getAttribute('class'))?.includes('on')) await l.click(); }
await mfaCard.locator('select').selectOption('30');
await mfaCard.locator('button:has-text("Simpan kebijakan")').click();
await a.waitForLoadState('networkidle');
ok('Kebijakan MFA disimpan', (await mfaCard.locator('.pill', { hasText: 'Wajib untuk 2 peran' }).count()) > 0, await flash(a));

// 3. Buat kode pemulihan dari Profil
await a.goto(`${BASE}/profile`, { waitUntil: 'networkidle' });
await a.click('button:has-text("Buat kode pemulihan"), button:has-text("Buat ulang kode pemulihan")');
await field(a, 'Konfirmasi kata sandi').fill(PASS);
await a.click('button:has-text("Lanjutkan")');
await a.waitForSelector('.codes span');
const codes = await a.locator('.codes span').allTextContents();
ok('Kode pemulihan ditampilkan sekali', codes.length === 10, codes[0]);
await a.reload({ waitUntil: 'networkidle' });
ok('Kode pemulihan tidak tampil lagi setelah dimuat ulang', (await a.locator('.codes span').count()) === 0);
await logout(a);

// 4. Login dua langkah dengan kode email
await login(a, 'admin@manrisk.id');
ok('Login berhenti di halaman verifikasi', a.url().includes('/login/verify'));
const code = lastCode('admin@manrisk.id');
ok('Kode OTP diterima via SMTP', /^\d{6}$/.test(code || ''), code);
const dash = await a.goto(`${BASE}/dashboard`); ok('Dashboard tertutup sebelum verifikasi', a.url().includes('/login'));
await login(a, 'admin@manrisk.id');
await a.fill('#code', code === '000000' ? '111111' : '000000');
await a.click('button:has-text("Verifikasi")'); await a.waitForLoadState('networkidle');
ok('Kode salah ditolak', a.url().includes('/login/verify') && (await flash(a)).includes('salah'), await flash(a));
// Login ulang tidak kirim kode baru dalam 60 dtk → kode sebelumnya tetap berlaku
await a.fill('#code', lastCode('admin@manrisk.id'));
await a.check('input[type=checkbox]');
await a.click('button:has-text("Verifikasi")');
await a.waitForURL(/dashboard/, { timeout: 15000 });
ok('Kode benar → masuk + percayai perangkat', a.url().includes('/dashboard'));
await logout(a);
await login(a, 'admin@manrisk.id');
ok('Perangkat tepercaya melewati kode', a.url().includes('/dashboard'));
await logout(a);

// 5. Peramban lain + kode pemulihan
const b = await newPage();
await login(b, 'admin@manrisk.id');
ok('Peramban baru tetap diminta kode', b.url().includes('/login/verify'));
await b.click('button:has-text("Pakai kode pemulihan")');
await b.fill('#code', codes[0].toLowerCase());
await b.click('button:has-text("Verifikasi")');
await b.waitForURL(/dashboard/, { timeout: 15000 });
ok('Masuk dengan kode pemulihan', b.url().includes('/dashboard'));
await b.waitForTimeout(500);
ok('Email pemberitahuan kode pemulihan', mails().some((m) => m.includes('To: admin@manrisk.id') && m.includes('Kode pemulihan dipakai')));
await logout(b);
await login(b, 'admin@manrisk.id');
await b.click('button:has-text("Pakai kode pemulihan")');
await b.fill('#code', codes[0]);
await b.click('button:has-text("Verifikasi")'); await b.waitForLoadState('networkidle');
ok('Kode pemulihan bekas ditolak', b.url().includes('/login/verify'), await flash(b));
await b.click('button:has-text("Batal")'); await b.waitForURL(/login$/);

// 6. Pengguna biasa mengaktifkan MFA sendiri dari Profil
const c = await newPage();
await login(c, 'officer@manrisk.id');
ok('Officer (tidak wajib) masuk tanpa kode', c.url().includes('/dashboard'));
await c.goto(`${BASE}/profile`, { waitUntil: 'networkidle' });
await c.click('button:has-text("Aktifkan")');
await c.waitForSelector('text=Kode dari email');
const oc = lastCode('officer@manrisk.id');
await field(c, 'Kode dari email').fill(oc);
await c.click('button:has-text("Konfirmasi & aktifkan")');
await c.waitForSelector('.codes span');
ok('Officer mengaktifkan MFA + dapat kode pemulihan', (await c.locator('.codes span').count()) === 10);
await c.waitForTimeout(500);
ok('Email pemberitahuan MFA aktif', mails().some((m) => m.includes('To: officer@manrisk.id') && m.includes('diaktifkan')));
await logout(c);
await login(c, 'officer@manrisk.id');
ok('Officer kini diminta kode saat login', c.url().includes('/login/verify'));
await c.fill('#code', lastCode('officer@manrisk.id'));
await c.click('button:has-text("Verifikasi")');
await c.waitForURL(/dashboard/, { timeout: 15000 });
await c.goto(`${BASE}/profile`, { waitUntil: 'networkidle' });
await c.click('button:has-text("Nonaktifkan")');
await field(c, 'Konfirmasi kata sandi').fill(PASS);
await c.click('button:has-text("Lanjutkan")');
await c.locator('.card-h .pill', { hasText: 'Nonaktif' }).waitFor({ timeout: 10000 }).catch(() => {});
ok('Officer menonaktifkan MFA', (await c.locator('.card-h .pill', { hasText: 'Nonaktif' }).count()) > 0, await flash(c));

// 7. Halaman admin pengguna menampilkan status MFA
const d = await newPage();
await loginFull(d, 'admin@manrisk.id');
await d.goto(`${BASE}/admin/users`, { waitUntil: 'networkidle' });
ok('Daftar pengguna menandai MFA', (await d.locator('.pill', { hasText: 'MFA' }).count()) > 0);

await browser.close();
