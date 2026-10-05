# ManRisk ERM

Aplikasi manajemen risiko terintegrasi berbasis **ISO 31000:2018** untuk PT Semesta Teknologi Utama.
Stack: **Laravel 13 (PHP 8.3) · MySQL/MariaDB · Inertia.js 2 + Vue 3 · Vite · ECharts**.

Spesifikasi lengkap: [`docs/SPESIFIKASI-PENGEMBANGAN.md`](docs/SPESIFIKASI-PENGEMBANGAN.md) · Panduan deploy: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) · Purwarupa UI (HTML statis): [`prototype/`](prototype/).

## Modul

| Area | Modul |
| --- | --- |
| Dashboard | Risk Dashboard, Executive Dashboard, KRI & Early Warning, Peringatan/notifikasi |
| Manajemen risiko | Konteks & konsultasi (SWOT), Kriteria berversi & taksonomi (appetite/tolerance), Risk Register (wizard identifikasi → analisis → evaluasi → treatment), Peta risiko 5×5, Evaluasi, Monitoring residual (snapshot bulanan), Risk Review |
| Penanganan & kontrol | Action plan (progres, kanban, pembatalan, proyeksi skor), Kontrol & efektivitas (pengujian desain/operasi), Continual improvement & lesson learned |
| Pemantauan | Insiden (otomatis ke loss database & peringatan), Loss event database |
| Tata kelola | Pemetaan sasaran/program/proses, Struktur organisasi (hierarki unit), Kerangka ISO 31000 (self-assessment), Persetujuan berjenjang (Risk Owner → Risk Manager → Management, SLA) |
| Pelaporan | Laporan PDF/Excel (register, eksekutif, action plan, kontrol, KRI, insiden), Dokumen & bukti (upload aman), AI Risk Assistant |
| Administrasi | Pengguna & akun (7 peran, cakupan unit), Audit trail append-only, log autentikasi, pengaturan organisasi |

## Keamanan

- Login server-side; kunci 15 menit setelah 5 kali gagal; sesi idle 30 menit / maksimum 8 jam; akun nonaktif langsung diputus.
- Kebijakan sandi (min. 10 karakter, kompleks, 5 riwayat), sandi sementara wajib diganti saat login pertama.
- RBAC per peran + cakupan unit (Policy di setiap modul), peran baca-saja (Management, Auditor).
- Multi-tenant: setiap query dibatasi `organization_id` pengguna (global scope), `organization_id` selalu dipaksa dari sesi.
- CSRF, header CSP/HSTS/nosniff/frame-ancestors, cookie HttpOnly + SameSite, `Cache-Control: no-store` untuk HTML.
- Unggahan: validasi MIME berdasarkan isi (finfo) + ekstensi + ukuran, nama acak, disimpan di luar web root, unduh lewat otorisasi, hash SHA-256.
- Audit trail (nilai lama → baru) untuk create/update/delete/persetujuan/unduh/ekspor; log autentikasi.
- Validasi FormRequest di seluruh endpoint, mass-assignment terkontrol, rate limit login/laporan/AI.

## Menjalankan secara lokal

```bash
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # atau isi DB_* MySQL di .env
php artisan migrate --seed              # kriteria, taksonomi, kerangka ISO, 7 akun peran, data demo
npm install && npm run build            # atau npm run dev
php artisan serve
```

Akun awal (sandi `MR_SEED_PASSWORD`, bawaan `ManRisk#2026`): `admin@manrisk.id` (Super Admin), `riskadmin@manrisk.id`, `manager@manrisk.id`, `officer@manrisk.id`, `owner@manrisk.id`, `management@manrisk.id`, `auditor@manrisk.id`.

Penjadwal (pengingat harian & snapshot bulanan): `* * * * * php artisan schedule:run`.

## Pengujian

```bash
php vendor/phpunit/phpunit/phpunit        # 41 uji fitur/unit: keamanan, RBAC, alur persetujuan, CRUD semua modul
php artisan serve --port=8010 & node tests/e2e/pages.mjs && node tests/e2e/crud.mjs   # Playwright: semua halaman + alur CRUD lintas peran
```
