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

## Upgrade rilis

1. Bangun ulang cabang `deploy/hosting` dari `main` (vendor tanpa paket dev, `installed.json` tanpa paket dev, build Vite).
2. Git Version Control → Pull or Deploy.
3. Di server: `php artisan package:discover && php artisan migrate --force && php artisan optimize`.
