// E2E CRUD lewat UI untuk setiap modul (buat → ubah → aksi khusus → hapus), sebagai Super Admin.
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8010';
const PASS = process.env.PASS || 'ManRisk#2026';
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
const page = await ctx.newPage();
const errors = []; let ok = 0;
page.on('pageerror', (e) => errors.push(`[pageerror] ${page.url()} ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error' && !/ERR_TOO_MANY_RETRIES|ERR_CERT_AUTHORITY_INVALID|status of 403|fonts\.g/.test(m.text())) errors.push(`[console] ${page.url()} ${m.text().slice(0, 160)}`); });
page.on('response', (r) => { if (r.status() >= 500) errors.push(`[http ${r.status()}] ${r.url()}`); });
page.on('dialog', (d) => d.accept(d.type() === 'prompt' ? 'Alasan uji E2E' : undefined));
const log = (m) => { ok++; console.log('✔', m); };
const root = async () => ((await page.locator('.modal').count()) ? page.locator('.modal').last() : page);
const fld = async (label) => (await root()).locator(`.field:has(> label:text-is("${label}"))`).first();
const fill = async (label, v) => (await fld(label)).locator('input,textarea').first().fill(String(v));
const pick = async (label, text) => { const s = (await fld(label)).locator('select'); if (text === undefined) { const v = await s.locator('option').nth(1).getAttribute('value'); await s.selectOption(v); } else await s.selectOption({ label: text }); };
const save = async () => (await root()).locator('.modal-f .btn.c-green, .modal-f .btn.c-teal, .modal-f .btn.c-orange').last().click();
const toast = async (re) => { await page.locator('.toast', { hasText: re }).last().waitFor({ timeout: 10000 }); };
const go = async (p) => { await page.goto(BASE + p, { waitUntil: 'networkidle' }); };
const row = (text) => page.locator('tbody tr, .tnode, .kri', { hasText: text }).first();
const step = async (name, fn) => { try { await fn(); log(name); } catch (e) { errors.push(`[step] ${name}: ${e.message.split('\n')[0]}`); console.log('✘', name, e.message.split('\n')[0]); await page.keyboard.press('Escape').catch(() => {}); } };

await go('/login'); await page.fill('#email', 'admin@manrisk.id'); await page.fill('#pass', PASS); await page.click('button[type=submit]'); await page.waitForURL(/dashboard|password/);

// ---- Organisasi
await step('unit: tambah', async () => { await go('/organization/units'); await page.click('button:has-text("Tambah unit")'); await fill('Nama unit', 'Unit Uji E2E'); await fill('Kode', 'UJI-E2E'); await pick('Jenis', 'Biro'); await save(); await toast(/ditambahkan/); });
await step('unit: ubah', async () => { await row('Unit Uji E2E').locator('button:has-text("Ubah")').first().click(); await fill('Nama unit', 'Unit Uji E2E 2'); await save(); await toast(/diperbarui/); });
await step('unit: hapus', async () => { await row('Unit Uji E2E 2').locator('.btn.danger').first().click(); await toast(/dihapus/); });
await step('sasaran: tambah/ubah/hapus', async () => { await go('/organization/objectives'); await page.click('.page-h button:has-text("Tambah")'); await fill('Kode', 'SS-E2E'); await fill('Sasaran strategis', 'Sasaran uji E2E'); await save(); await toast(/ditambahkan/);
  await row('Sasaran uji E2E').locator('button:has-text("Ubah")').click(); await fill('Indikator kinerja', 'IK uji'); await save(); await toast(/diperbarui/); await row('Sasaran uji E2E').locator('.btn.danger').click(); await toast(/dihapus/); });
await step('program: tambah/hapus', async () => { await page.click('.tabs button:has-text("Program")'); await page.click('.page-h button:has-text("Tambah")'); await fill('Nama program', 'Program E2E'); await save(); await toast(/ditambahkan/); await row('Program E2E').locator('.btn.danger').click(); await toast(/dihapus/); });
await step('proses: tambah/ubah/hapus', async () => { await page.click('.tabs button:has-text("Proses bisnis")'); await page.click('.page-h button:has-text("Tambah")'); await fill('Nama proses bisnis', 'Proses E2E'); await save(); await toast(/ditambahkan/);
  await row('Proses E2E').locator('button:has-text("Ubah")').click(); await fill('Deskripsi', 'uji'); await save(); await toast(/diperbarui/); await row('Proses E2E').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Konteks
await step('ruang lingkup/faktor/konsultasi: CRUD', async () => { await go('/context'); await page.click('button:has-text("Ruang lingkup")'); await fill('Nama ruang lingkup', 'Lingkup E2E'); await save(); await toast(/disimpan/);
  await page.click('.page-h button:has-text("Faktor konteks")'); await fill('Faktor', 'Faktor E2E'); await fill('Kondisi / uraian', 'kondisi uji'); await pick('Sifat', 'Ancaman'); await save(); await toast(/ditambahkan/);
  await row('Faktor E2E').locator('button:has-text("Ubah")').first().click(); await fill('Kondisi / uraian', 'kondisi diubah'); await save(); await toast(/diperbarui/); await row('Faktor E2E').locator('.btn.danger').first().click(); await toast(/dihapus/);
  await page.click('.page-h button:has-text("Konsultasi")'); await fill('Judul kegiatan', 'Rapat E2E'); await fill('Tanggal', '2026-10-01'); await save(); await toast(/disimpan/); await row('Rapat E2E').locator('.btn.danger').first().click(); await toast(/dihapus/);
  await page.locator('.card:has-text("Lingkup E2E")').locator('.btn.danger').first().click(); await toast(/dihapus/); });

// ---- Kriteria & kategori
await step('kategori: tambah/ubah/hapus', async () => { await go('/criteria'); await page.click('.tabs button:has-text("Taksonomi")'); await page.click('.page-h button:has-text("Kategori")'); await fill('Nama kategori', 'Kategori E2E'); await fill('Risk appetite (skor)', 5); await fill('Risk tolerance (skor)', 8); await save(); await toast(/ditambahkan/);
  await row('Kategori E2E').locator('button:has-text("Ubah")').click(); await fill('Risk tolerance (skor)', 10); await save(); await toast(/diperbarui/); await row('Kategori E2E').locator('.btn.danger').click(); await toast(/dihapus/); });
await step('kriteria: versi baru & aktifkan versi lama', async () => { await page.click('.tabs button:has-text("Kriteria")'); await page.click('button:has-text("Versi kriteria baru")'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/versi 2/);
  await page.locator('.row:has-text("Versi 1") button:has-text("Aktifkan")').click(); await toast(/diaktifkan/); });

// ---- Kontrol
await step('kontrol: tambah/ubah/uji/hapus', async () => { await go('/controls'); await page.click('button:has-text("Kontrol baru")'); await fill('Nama kontrol', 'Kontrol E2E'); await save(); await toast(/ditambahkan/);
  await row('Kontrol E2E').locator('button:has-text("Ubah")').click(); await fill('Deskripsi pelaksanaan', 'diubah'); await save(); await toast(/diperbarui/);
  await page.locator('tr:has-text("Kontrol E2E") td').nth(1).click(); await page.waitForURL(/controls\/\d+/); await page.click('button:has-text("Catat pengujian")'); await save(); await toast(/dicatat/);
  await go('/controls?q=E2E'); await row('Kontrol E2E').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Action plan
await step('action plan: tambah/ubah/progres/batal/hapus', async () => { await go('/action-plans'); await page.click('.page-h button:has-text("Action plan")'); await pick('Risiko'); await fill('Judul rencana', 'AP E2E'); await fill('Tenggat', '2026-12-31'); await save(); await toast(/ditambahkan/);
  await go('/action-plans?q=AP%20E2E'); await row('AP E2E').locator('button:has-text("Ubah")').click(); await fill('Uraian', 'diubah'); await save(); await toast(/diperbarui/);
  await row('AP E2E').locator('button:has-text("Progres")').click(); await page.fill('.modal input[type=number]', '40'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/Progres/);
  await row('AP E2E').locator('button:has-text("Batalkan")').click(); await toast(/dibatalkan/); await row('AP E2E').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- KRI
await step('KRI: tambah/ubah/nilai/hapus', async () => { await go('/kris'); await page.click('button:has-text("KRI baru")'); await fill('Nama indikator', 'KRI E2E'); await fill('Ambang waspada', 5); await fill('Ambang kritis', 9); await save(); await toast(/ditambahkan/);
  const k = page.locator('.kri', { hasText: 'KRI E2E' }); await k.locator('button:has-text("Ubah")').click(); await fill('Satuan', 'kali'); await save(); await toast(/diperbarui/);
  await k.locator('button:has-text("Input nilai")').click(); await page.locator('.modal input[type=number]').fill('3'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/Nilai KRI/);
  await k.locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Insiden & kerugian
await step('insiden: lapor/ubah/lesson/hapus', async () => { await go('/incidents'); await page.click('button:has-text("Laporkan insiden")'); await fill('Judul insiden', 'Insiden E2E'); await page.locator('.modal .field:has(> label:text-is("Waktu kejadian")) input').fill('2026-10-01T09:00'); await save(); await page.waitForURL(/incidents\/\d+/); await toast(/dilaporkan/);
  await page.click('button:has-text("Ubah / perbarui status")'); await pick('Status', 'Investigasi'); await save(); await toast(/diperbarui/);
  await page.click('.page-h button:has-text("Lesson learned")'); await page.fill('.modal textarea', 'pelajaran E2E'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/Lesson/);
  await page.locator('.page-h .btn.c-red').click(); await page.waitForURL(/incidents$/); });
await step('loss event: tambah/ubah/hapus', async () => { await go('/incidents/losses'); await page.click('button:has-text("Tambah kerugian")'); await fill('Nilai kerugian (Rp)', 1000000); await fill('Risiko', 'Risiko E2E'); await fill('Peristiwa', 'Peristiwa E2E'); await save(); await toast(/ditambahkan/);
  await row('Peristiwa E2E').locator('button:has-text("Ubah")').click(); await fill('Nilai kerugian (Rp)', 2000000); await save(); await toast(/diperbarui/); await row('Peristiwa E2E').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Review
await step('review: catat & berita acara', async () => { await go('/reviews'); await page.click('.page-h button:has-text("Catat review")'); await pick('Risiko'); await fill('Periode', '2026-E2E'); await page.locator('.modal .modal-f .btn.c-teal').click(); await toast(/dicatat/);
  await page.selectOption('.card-h select', '2026-E2E'); await page.waitForURL(/period=2026-E2E/); const d = page.waitForEvent('download'); await page.click('a:has-text("Berita acara PDF")'); const f = await d; if (!(await f.suggestedFilename()).endsWith('.pdf')) throw new Error('bukan pdf');
  await row('2026-E2E').locator('.btn.danger').first().click(); await toast(/dihapus/); });

// ---- Dokumen
await step('dokumen: unggah/versi baru/riwayat/ubah/hapus', async () => { await go('/documents'); await page.click('.page-h button:has-text("Unggah dokumen")'); await fill('Judul', 'Dokumen E2E');
  await page.setInputFiles('.modal input[type=file]', { name: 'e2e.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF') }); await page.locator('.modal .modal-f .btn.c-orange').click(); await toast(/diunggah/);
  await go('/documents?q=Dokumen%20E2E'); await row('Dokumen E2E').locator('button:has-text("Versi baru")').click(); await page.setInputFiles('.modal input[type=file]', { name: 'e2e2.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%v2\n%%EOF') }); await page.locator('.modal .modal-f .btn.c-orange').click(); await toast(/diunggah/);
  await row('Dokumen E2E').locator('button:has-text("Riwayat")').click(); await page.waitForURL(/history=/); if ((await page.locator('tbody tr', { hasText: 'Dokumen E2E' }).count()) !== 2) throw new Error('riwayat versi tidak 2');
  await page.locator('tbody tr', { hasText: 'v2' }).locator('button:has-text("Ubah")').click(); await pick('Status', 'Disetujui'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/diperbarui/);
  await page.locator('tbody tr', { hasText: 'v2' }).locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Perbaikan & kerangka
await step('improvement & lesson: CRUD', async () => { await go('/improvements'); await page.click('.page-h button:has-text("Tindakan perbaikan")'); await fill('Tindakan perbaikan', 'Perbaikan E2E'); await save(); await toast(/ditambahkan/);
  await row('Perbaikan E2E').locator('button:has-text("Ubah")').click(); await pick('Status', 'Selesai'); await save(); await toast(/diperbarui/); await row('Perbaikan E2E').locator('.btn.danger').click(); await toast(/dihapus/);
  await page.click('.page-h button:has-text("Lesson learned")'); await page.fill('.modal textarea', 'Lesson E2E'); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/disimpan/); await page.locator('.alert-box', { hasText: 'Lesson E2E' }).locator('.btn').click(); await toast(/dihapus/); });
await step('kerangka ISO: nilai butir', async () => { await go('/framework'); await page.locator('tbody tr', { hasText: '4.a' }).locator('button:has-text("Nilai")').click(); await fill('Skor (0–100)', 95); await page.locator('.modal .modal-f .btn.c-green').click(); await toast(/diperbarui/); });

// ---- Peringatan
await step('peringatan: tandai dibaca & selesai', async () => { await go('/alerts'); const a = page.locator('.alert-box').first(); await a.locator('button:has-text("Selesai ditangani")').click(); await toast(/selesai/); await page.click('button:has-text("Tandai semua dibaca")'); await toast(/dibaca/); });

// ---- Laporan (semua jenis × format)
await step('laporan: semua jenis & format + jadwal', async () => { await go('/reports'); const types = await page.locator('.field:has(> label:text-is("Jenis laporan")) select option').evaluateAll((os) => os.map((o) => o.value).filter(Boolean));
  for (const t of types) { await page.locator('.field:has(> label:text-is("Jenis laporan")) select').selectOption(t); const fmts = await page.locator('.field:has(> label:text-is("Format")) select option').first().evaluateAll((os) => os.map((o) => o.value).filter(Boolean));
    for (const fm of fmts) { await page.locator('.field:has(> label:text-is("Format")) select').first().selectOption(fm); const d = page.waitForEvent('download', { timeout: 30000 }); await page.locator('.card:has-text("Buat laporan") .btn.block').click(); await d; } }
  await page.locator('.card:has-text("Laporan terjadwal") input.inp').last().fill('direksi@contoh.go.id'); await page.click('button:has-text("Jadwalkan")'); await toast(/Jadwal/); await row('direksi@contoh.go.id').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Impor Excel risiko (templat → unggah → pratinjau → simpan)
await step('impor risiko: templat → pratinjau → simpan', async () => { await go('/import/risk'); const d = page.waitForEvent('download'); await page.click('a:has-text("Unduh templat")'); const f = await d; const p = await f.path(); const buf = (await import('node:fs')).readFileSync(p);
  await page.setInputFiles('input[type=file]', { name: 'templat.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: buf }); await page.click('button:has-text("Periksa berkas")'); await page.locator('.stat-row').waitFor(); const txt = await page.locator('.stat-row').textContent(); if (!/Total baris\s*1/.test(txt)) throw new Error(txt); });

// ---- AI: kandidat risiko mengisi wizard
await step('AI: kandidat risiko mengisi wizard', async () => { await go('/risks/create'); await page.click('button:has-text("Saran risiko AI")'); await page.fill('.modal input.inp', 'pengadaan barang'); await page.click('.modal button:has-text("Cari kandidat")'); await page.locator('.modal button:has-text("Gunakan")').first().click(); await toast(/Kandidat/);
  const nm = await page.locator('.field:has(> label:text-is("Nama risiko")) input').inputValue(); if (!nm) throw new Error('nama tidak terisi'); });

// ---- Pengguna
await step('pengguna: tambah/ubah/reset/nonaktif/hapus', async () => { await go('/admin/users'); await page.click('button:has-text("Tambah pengguna")'); await fill('Nama lengkap', 'Pengguna Modul E2E'); await fill('Email (untuk masuk)', 'modul.e2e@manrisk.id'); await pick('Peran', 'Auditor'); await save();
  await page.locator('.modal:has-text("Kata sandi sementara") code').waitFor(); await page.click('button:has-text("Sudah saya catat")');
  await page.fill('.filters input', 'modul.e2e'); await page.waitForTimeout(800); await row('modul.e2e@manrisk.id').locator('button:has-text("Ubah")').click(); await fill('Jabatan', 'Penguji'); await save(); await toast(/diperbarui/);
  await row('modul.e2e@manrisk.id').locator('button:has-text("Reset sandi")').click(); await page.locator('.modal:has-text("Kata sandi sementara") code').waitFor(); await page.click('button:has-text("Sudah saya catat")');
  await row('modul.e2e@manrisk.id').locator('button:has-text("Nonaktifkan")').click(); await toast(/dinonaktifkan/); await row('modul.e2e@manrisk.id').locator('.btn.danger').click(); await toast(/dihapus/); });

// ---- Profil & audit
await step('profil: simpan preferensi', async () => { await go('/profile'); await page.locator('button:has-text("Simpan")').click(); await toast(/Profil disimpan/); });
await step('audit trail: ekspor CSV', async () => { await go('/admin/audit'); const d = page.waitForEvent('download'); await page.click('a:has-text("Ekspor CSV")'); const f = await d; if (!(await f.suggestedFilename()).endsWith('.csv')) throw new Error('bukan csv'); });

console.log(`\nLANGKAH OK: ${ok}`); console.log('ERRORS:', errors.length); errors.forEach((e) => console.log(' ', e));
await browser.close();
process.exit(errors.length ? 1 : 0);
