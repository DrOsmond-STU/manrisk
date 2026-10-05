// Uji CRUD lewat UI nyata: officer buat risiko → submit; owner & manager setujui; action plan progres; KRI; insiden; unit; pengguna
import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8010';
const browser = await chromium.launch();
const errors = []; const log = (m) => console.log('✔', m);
const mk = async () => { const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } }); const page = await ctx.newPage(); page.on('pageerror', (e) => errors.push(`[pageerror] ${page.url()} ${e.message}`)); page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); }); return page; };
const login = async (page, email) => { await page.goto(`${BASE}/login`); await page.fill('#email', email); await page.fill('#pass', 'ManRisk#2026'); await page.click('button[type=submit]'); await page.waitForURL(/dashboard/, { timeout: 15000 }); };
const toast = async (page, re = /./) => { const t = page.locator('.toast', { hasText: re }).last(); await t.waitFor({ timeout: 8000 }); return t.textContent(); };
const root = async (page) => ((await page.locator('.modal').count()) ? page.locator('.modal').last() : page);
const sel = async (page, label, text) => { const s = (await root(page)).locator(`.field:has(label:text-is("${label}")) select`).first(); await s.selectOption({ label: text }); };
const selFirst = async (page, label, idx = 1) => { const s = (await root(page)).locator(`.field:has(label:text-is("${label}")) select`).first(); const v = await s.locator('option').nth(idx).getAttribute('value'); await s.selectOption(v); };
const fill = async (page, label, val) => { await (await root(page)).locator(`.field:has(label:text-is("${label}")) :is(input,textarea)`).first().fill(val); };

// ---- Risk Officer: buat risiko lewat wizard
const officer = await mk(); await login(officer, 'officer@manrisk.id');
await officer.goto(`${BASE}/risks/create`);
await fill(officer, 'Nama risiko', 'Uji E2E kebocoran data pelanggan');
await selFirst(officer, 'Unit kerja pemilik'); await selFirst(officer, 'Risk owner'); await selFirst(officer, 'Kategori (taksonomi)', 2);
await fill(officer, 'Penyebab (karena…)', 'akses basis data tidak dibatasi'); await fill(officer, 'Peristiwa risiko (mungkin terjadi…)', 'kebocoran data pelanggan'); await fill(officer, 'Dampak (yang berdampak pada…)', 'sanksi regulator dan reputasi');
await officer.click('text=Berikutnya ›');
await officer.locator('.card:has-text("Inheren") .hm .cell').nth(4).click(); // L5 I5
await officer.locator('.card:has-text("Residual") .hm .cell').nth(8).click(); // L4 I4 = 16
await officer.locator('.card:has-text("Target") .hm .cell').nth(21).click(); // L1 I2
await officer.click('text=Berikutnya ›'); await officer.click('text=Berikutnya ›');
await officer.click('button:has-text("Simpan draft risiko")');
await officer.waitForURL(/\/risks\/\d+$/); const riskUrl = officer.url(); const riskId = riskUrl.split('/').pop();
await toast(officer, /tersimpan/); log(`risiko dibuat ${riskUrl} (${(await officer.locator('h1').textContent()).trim()})`);
// submit approval
await officer.click('button:has-text("Ajukan persetujuan")'); await officer.fill('.modal textarea', 'Mohon persetujuan'); await officer.click('.modal button:has-text("Ajukan")'); await toast(officer, /diajukan/); log('risiko diajukan');
// edit terkunci
await officer.reload(); const locked = await officer.locator('.alert-box:has-text("menunggu persetujuan")').count(); if (!locked) throw new Error('banner pending tidak muncul'); log('edit terkunci saat pending');

// ---- Risk Owner menyetujui tahap 1, Manager tahap 2, Management tahap 3 (residual 16)
for (const [email, who] of [['owner@manrisk.id', 'owner'], ['manager@manrisk.id', 'manager'], ['management@manrisk.id', 'management']]) {
  const p = await mk(); await login(p, email); await p.goto(`${BASE}/approvals`);
  const card = p.locator('.card:has-text("Uji E2E kebocoran")').first(); await card.locator('button:has-text("Setujui")').click();
  await p.fill('.modal textarea', 'OK ' + who); await p.locator('.modal .btn.c-green').click(); await toast(p, /disetujui/); log(`disetujui oleh ${who}`); await p.context().close();
}
await officer.goto(riskUrl); const st = await officer.locator('.page-h .pill').first().textContent(); if (!/Dalam Penanganan/.test(st)) throw new Error('status: ' + st); log('status risiko → Dalam Penanganan');

// ---- action plan dari halaman risiko + progres
await officer.click('.tabs button:has-text("Action plan")'); await officer.click('button:has-text("Tambah action plan")');
await fill(officer, 'Judul rencana', 'Terapkan enkripsi kolom'); await fill(officer, 'Tenggat', '2026-12-31'); await officer.locator('.modal .btn.c-green').click(); await toast(officer, /ditambahkan/); log('action plan dibuat');
await officer.goto(`${BASE}/action-plans?q=enkripsi`); await officer.locator('tr:has-text("Terapkan enkripsi") button:has-text("Progres")').click();
await officer.fill('.modal input[type=number]', '50'); await officer.fill('.modal textarea', 'setengah jalan'); await officer.locator('.modal .btn.c-green').click(); await toast(officer, /Progres dicatat/); log('progres 50% dicatat');

// ---- KRI: buat + input nilai kritis
await officer.goto(`${BASE}/kris`); await officer.click('button:has-text("KRI baru")');
await fill(officer, 'Nama indikator', 'Jumlah akses tidak sah'); await fill(officer, 'Satuan', 'kejadian'); await fill(officer, 'Ambang waspada', '2'); await fill(officer, 'Ambang kritis', '5');
await officer.locator('.modal .btn.c-green').click(); await toast(officer, /ditambahkan/); log('KRI dibuat');
const kri = officer.locator('.kri:has-text("Jumlah akses tidak sah")'); await kri.locator('button:has-text("Input nilai")').click();
await officer.locator('.modal input[type=number]').fill('7'); await officer.locator('.modal .btn.c-green').click(); await toast(officer, /Nilai KRI/);
await officer.reload(); const pill = await officer.locator('.kri:has-text("Jumlah akses tidak sah") .pill').first().textContent(); if (!/Kritis/.test(pill)) throw new Error('KRI status: ' + pill); log('KRI kritis + peringatan');

// ---- Insiden
await officer.goto(`${BASE}/incidents`); await officer.click('button:has-text("Laporkan insiden")');
await fill(officer, 'Judul insiden', 'Uji E2E percobaan akses tidak sah'); await officer.locator('.field:has(label:text-is("Waktu kejadian")) input').fill('2026-10-04T10:00'); await fill(officer, 'Kerugian (Rp)', '1500000');
await officer.locator('.modal .btn.c-green').click(); await officer.waitForURL(/\/incidents\/\d+$/); await toast(officer, /dilaporkan/); log('insiden dilaporkan: ' + officer.url());

// ---- Dokumen unggah ke risiko
await officer.goto(riskUrl); await officer.click('.tabs button:has-text("Dokumen")'); await officer.click('button:has-text("Unggah")');
await fill(officer, 'Judul dokumen', 'Bukti E2E'); await officer.setInputFiles('.modal input[type=file]', { name: 'bukti.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF') });
await officer.locator('.modal .btn.c-orange').click(); await toast(officer, /diunggah/); log('dokumen diunggah'); await officer.goto(BASE + '/dashboard'); await officer.goto(riskUrl + '#docs'); await officer.waitForLoadState('networkidle'); await officer.screenshot({ path: '/tmp/docs.png' });
const dl = await officer.request.get(`${BASE}/documents/${await officer.locator('tr:has-text("Bukti E2E") a.btn').getAttribute('href').then((h) => h.split('/')[2])}/download`); if (dl.status() !== 200) throw new Error('download ' + dl.status()); log('dokumen dapat diunduh');

// ---- Auditor hanya baca
const aud = await mk(); await login(aud, 'auditor@manrisk.id'); await aud.goto(riskUrl); const btns = await aud.locator('.page-h button, .page-h a.btn').count(); if (btns) throw new Error('auditor melihat tombol tulis: ' + btns); log('auditor read-only'); const r403 = await aud.goto(`${BASE}/admin/users`); if (r403.status() !== 403) throw new Error('auditor /admin/users ' + r403.status()); log('auditor dilarang ke admin'); await aud.context().close();

// ---- Super admin: pengguna baru + unit + reset sandi
const admin = await mk(); await login(admin, 'admin@manrisk.id'); await admin.goto(`${BASE}/admin/users`); await admin.click('button:has-text("Tambah pengguna")');
await fill(admin, 'Nama lengkap', 'Pengguna E2E'); await fill(admin, 'Email (untuk masuk)', 'e2e@manrisk.id'); await sel(admin, 'Peran', 'Risk Officer'); await admin.locator('.modal .btn.c-green').click();
await admin.locator('.modal:has-text("Kata sandi sementara") code').waitFor({ timeout: 8000 }); const pw = await admin.locator('.modal code').textContent(); log('pengguna dibuat, sandi sementara ' + pw.length + ' karakter'); await admin.click('button:has-text("Sudah saya catat")');
// login pengguna baru → wajib ganti sandi
const nu = await mk(); await nu.goto(`${BASE}/login`); await nu.fill('#email', 'e2e@manrisk.id'); await nu.fill('#pass', pw); await nu.click('button[type=submit]'); await nu.waitForURL(/password/); log('pengguna baru diarahkan ke ganti sandi');
await nu.fill('.field:has(label:text-is("Kata sandi saat ini")) input', pw); await nu.fill('.field:has(label:text-is("Kata sandi baru")) input', 'SandiBaru#2026x'); await nu.fill('.field:has(label:text-is("Ulangi kata sandi baru")) input', 'SandiBaru#2026x'); await nu.click('button:has-text("Simpan kata sandi")'); await nu.waitForURL(/dashboard/); log('ganti sandi wajib berhasil'); await nu.context().close();
await admin.goto(`${BASE}/organization/units`); await admin.click('button:has-text("Tambah unit")'); await fill(admin, 'Nama unit', 'Unit E2E'); await fill(admin, 'Kode', 'E2E-1'); await admin.locator('.modal .btn.c-green').click(); await toast(admin, /ditambahkan/); log('unit dibuat');
admin.once('dialog', (d) => d.accept()); await admin.locator('.tnode:has-text("Unit E2E") .btn.danger').click(); await toast(admin, /dihapus/); log('unit dihapus');
// laporan excel
const rep = await admin.request.post(`${BASE}/reports`, { headers: { 'X-CSRF-TOKEN': await admin.locator('meta[name=csrf-token]').getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' }, data: { type: 'register', format: 'xlsx' } }); if (rep.status() !== 200 || !/spreadsheet/.test(rep.headers()['content-type'])) throw new Error('report ' + rep.status() + ' ' + rep.headers()['content-type']); log('laporan Excel ' + (await rep.body()).length + ' byte');
const pdf = await admin.request.post(`${BASE}/reports`, { headers: { 'X-CSRF-TOKEN': await admin.locator('meta[name=csrf-token]').getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' }, data: { type: 'executive', format: 'pdf' } }); if (pdf.status() !== 200 || !/pdf/.test(pdf.headers()['content-type'])) throw new Error('pdf ' + pdf.status()); log('laporan PDF ' + (await pdf.body()).length + ' byte');
// audit trail memuat risiko E2E
await admin.goto(`${BASE}/admin/audit?q=Uji%20E2E`); const n = await admin.locator('tbody tr').count(); if (n < 1) throw new Error('audit kosong'); log(`audit trail: ${n} baris untuk risiko E2E`);
// theme & logout
await admin.click('button[aria-label="Ganti tema terang/gelap"]'); await admin.click('.user.u-btn'); await admin.click('.um-i.danger'); await admin.waitForURL(/login/); log('logout ok');
console.log('ERRORS:', errors.length); errors.forEach((e) => console.log(' ', e));
await browser.close();
