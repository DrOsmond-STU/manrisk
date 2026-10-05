# Deploy ManRisk ERM ke cPanel (Domainesia)

Subdomain produksi: `manrisk.semestateknologiutama.com` · docroot `/home/semestat/manrisk.semestateknologiutama.com/public`.

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

Git Version Control cPanel (repo `manrisk.semestateknologiutama.com`, cabang `deploy/hosting`) → **Pull or Deploy**. Berkas `.cpanel.yml` menjalankan langkah pasca-deploy (cache config/route/view, migrasi).

## Konfigurasi sekali

1. **Docroot** subdomain diarahkan ke `.../public`.
2. **Basis data** MariaDB: `semestat_manrisk` + pengguna dengan hak penuh; isi `DB_*` di `.env`.
3. **`.env`** (lihat `.env.example`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://manrisk.semestateknologiutama.com`, `APP_KEY` (dari `php artisan key:generate --show`), `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync`, `MAIL_*` SMTP, `MR_SEED_MUST_CHANGE=true`.
4. **Migrasi & seed**: `php artisan migrate --force && php artisan db:seed --force` (sekali).
5. **Izin**: `storage/` dan `bootstrap/cache/` dapat ditulis oleh PHP.
6. **Cron**: `* * * * * cd /home/semestat/manrisk.semestateknologiutama.com && /usr/local/bin/php artisan schedule:run >> storage/logs/schedule.log 2>&1`
7. **AutoSSL** aktif untuk subdomain; HSTS dikirim otomatis saat HTTPS.

## Setelah setiap deploy

`php artisan migrate --force && php artisan optimize` (dijalankan oleh `.cpanel.yml`). Bila halaman tampak lama karena cache proxy, hard refresh; HTML/JSON sudah dikirim dengan `Cache-Control: no-store`.
