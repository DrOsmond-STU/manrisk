# Deploy ManRisk ERM ke cPanel (Domainesia)

Subdomain produksi: `manrisk.semestateknologiutama.com` · aplikasi di `/home/semestat/manrisk-app`, docroot `/home/semestat/manrisk-app/public`.

## Strategi

Server cPanel tidak menyediakan Composer, sehingga `vendor/` dan hasil build Vite (`public/build/`) ikut dikirim lewat cabang `deploy/hosting`. Cabang `main` tetap hanya berisi sumber.

```bash
# dari mesin pengembang / CI setelah merge ke main
git checkout -B deploy/hosting main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
git add -f vendor public/build && git commit -m "deploy: vendor + build"
git push -f origin deploy/hosting
```

Git Version Control cPanel (deployment `manrisk.semestateknologiutama.com` → `/home/semestat/manrisk-app`, cabang `deploy/hosting`) → **Pull or Deploy**. Berkas `.cpanel.yml` menjalankan langkah pasca-deploy (cache config/route/view, migrasi).

## Konfigurasi sekali

1. **Docroot** subdomain diarahkan ke `.../public`.
2. **Basis data** MariaDB: `semestat_manrisk` + pengguna dengan hak penuh; isi `DB_*` di `.env`.
3. **`.env`** (lihat `.env.example`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://manrisk.semestateknologiutama.com`, `APP_KEY` (dari `php artisan key:generate --show`), `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync`, `MAIL_*` SMTP, `MR_SEED_MUST_CHANGE=true`.
4. **Migrasi & seed**: `php artisan migrate --force && php artisan db:seed --force` (sekali).
5. **Izin**: `storage/` dan `bootstrap/cache/` dapat ditulis oleh PHP.
6. **Cron**: `* * * * * cd /home/semestat/manrisk-app && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
7. **AutoSSL** aktif untuk subdomain; HSTS dikirim otomatis saat HTTPS.

## Setelah setiap deploy

`php artisan migrate --force && php artisan optimize` (dijalankan oleh `.cpanel.yml`). Bila halaman tampak lama karena cache proxy, hard refresh; HTML/JSON sudah dikirim dengan `Cache-Control: no-store`.

## Hardening produksi (sudah diterapkan)

- `.env` produksi: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_COOKIE=__Host-manrisk_session`, `TRUSTED_PROXIES` hanya alamat lokal/privat, izin berkas `0600`.
- Sandi bawaan seed diganti sandi acak per akun dengan `php artisan manrisk:rotate-passwords --only=<email> ... --disable-others --out=<berkas>`; semua akun wajib mengganti sandi saat masuk pertama. Hapus berkas keluaran setelah sandi dibagikan.
- Docroot hanya `public/`; `.htaccess` menolak dotfile dan eksekusi PHP selain `index.php`, memaksa HTTPS.
- Antivirus unggahan opsional: isi `MR_CLAMAV_PATH` (mis. `/usr/bin/clamscan`) bila tersedia di server.
- Verifikasi penyelesaian action plan oleh Risk Owner: `MR_PLAN_VERIFICATION=true` (bawaan).

## Email (SMTP) & verifikasi dua langkah (MFA)

1. Buat akun email pengirim di cPanel → *Email Accounts* (mis. `noreply@semestateknologiutama.com`).
2. Masuk sebagai Super Admin → **Administrasi → Email & SMTP**: host `mail.semestateknologiutama.com`, port `465` (SSL/TLS) atau `587` (STARTTLS), nama pengguna = alamat email lengkap, sandi akun email, alamat pengirim = akun yang sama. Simpan, lalu **Kirim email uji**. Sandi SMTP disimpan terenkripsi dengan `APP_KEY` (bila `APP_KEY` diganti, isi ulang sandi SMTP). Pengaturan ini menggantikan `MAIL_*` di `.env`; bila dinonaktifkan, `MAIL_*` yang berlaku.
3. **Pengaturan Organisasi → Verifikasi dua langkah**: pilih peran yang wajib MFA (disarankan minimal Super Admin & Risk Administrator) dan masa perangkat tepercaya (0/7/30 hari). Kebijakan hanya dapat diaktifkan bila email siap mengirim; email juga tidak dapat dimatikan selama MFA dipakai.
4. Pengguna lain dapat mengaktifkan MFA sendiri di **Profil**, dan setiap pengguna MFA sebaiknya membuat **kode pemulihan** (10 kode sekali pakai) di Profil.
5. Alur login: sandi benar → kode 6 digit dikirim ke email (berlaku `MR_MFA_CODE_TTL` menit, bawaan 10) → maks. 5 percobaan per kode, 10 kegagalan/15 menit per akun mengakhiri proses login, maks. 5 kode/15 menit per akun, kirim ulang setelah 60 detik.
6. Pemulihan darurat: Super Admin dapat **Reset MFA** di halaman Pengguna; bila Super Admin sendiri terkunci, jalankan di server `php artisan manrisk:mfa-reset <email>`. Bila pengguna kehilangan akses email, ubah alamat emailnya (perangkat tepercaya otomatis dicabut).

## Upgrade rilis

1. Bangun ulang cabang `deploy/hosting` dari `main` (vendor tanpa paket dev, `installed.json` tanpa paket dev, build Vite).
2. Git Version Control → Pull or Deploy.
3. Di server: `php artisan package:discover && php artisan migrate --force && php artisan optimize`.
