# Petunjuk Deployment ke aaPanel

## 1. Persiapan Server
- **PHP Manager**: Install PHP 8.2 atau 8.3.
- **Extensions**: Pastikan extension berikut aktif:
  - `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo_pgsql` (jika pakai Postgres), `xml`, `redis`, `pcntl`, `posix`.
- **Composer**: Pastikan Composer sudah terinstall.

## 2. Setup Site di aaPanel
- Tambahkan situs baru.
- Set **Site Directory** ke root project.
- Set **Running Directory** ke `/public`.
- Pilih **URL Rewrite** -> Pilih template `laravel5` (atau copy isi `deploy/nginx.conf`).

## 3. Database
- Buat database di aaPanel.
- Sesuaikan konfigurasi di file `.env`.

## 4. Background Services (Supervisor)
Gunakan aplikasi **Supervisor Manager** di aaPanel untuk menjalankan command berikut:

### Laravel Queue Worker
- **Name**: `laravel-worker`
- **Command**: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- **User**: `www`

### MQTT Listener
- **Name**: `mqtt-listener`
- **Command**: `php artisan mqtt:listen`
- **User**: `www`

### Scheduler (Cron)
- Tambahkan di menu **Cron** aaPanel:
  - **Type**: `Shell Script`
  - **Name**: `Laravel Scheduler`
  - **Period**: `Every Minute`
  - **Script**: `cd /www/wwwroot/path-ke-project && php artisan schedule:run >> /dev/null 2>&1`

## 5. SSL
- Gunakan **Let's Encrypt** di tab SSL aaPanel untuk mengaktifkan HTTPS.
