# Supervisor Setup Guide

Panduan menjalankan **MQTT Listener**, **Laravel Scheduler**, dan **Queue Worker** secara otomatis menggunakan Supervisor di server Linux.

---

## 1. Install Supervisor

```bash
# Ubuntu / Debian
sudo apt update && sudo apt install -y supervisor

# CentOS / AlmaLinux
sudo yum install -y supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
```

---

## 2. Sesuaikan Path di supervisor.conf

Buka `deploy/supervisor.conf` dan ganti path jika berbeda:

| Placeholder | Ganti dengan |
|---|---|
| `/var/www/smarthom` | Absolute path ke folder project Laravel |
| `www-data` | User yang menjalankan PHP (cek dengan `ps aux \| grep php-fpm`) |

Contoh jika menggunakan aaPanel dengan PHP 8.2:
```ini
command=php8.2 /www/wwwroot/smarthom/artisan mqtt:listen
user=www
```

---

## 3. Install & Aktifkan Config

```bash
# Copy config ke direktori supervisor
sudo cp /path/to/project/deploy/supervisor.conf /etc/supervisor/conf.d/smarthom.conf

# Baca config baru
sudo supervisorctl reread

# Update (apply perubahan)
sudo supervisorctl update

# Start semua process dalam group
sudo supervisorctl start smarthom:*
```

---

## 4. Perintah Supervisor Umum

```bash
# Cek status semua process
sudo supervisorctl status

# Restart semua sekaligus (setelah deploy)
sudo supervisorctl restart smarthom:*

# Restart satu process
sudo supervisorctl restart laravel-mqtt
sudo supervisorctl restart laravel-scheduler
sudo supervisorctl restart laravel-queue

# Stop semua
sudo supervisorctl stop smarthom:*

# Lihat log realtime
sudo tail -f /var/log/supervisor/laravel-mqtt.log
sudo tail -f /var/log/supervisor/laravel-scheduler.log
sudo tail -f /var/log/supervisor/laravel-queue.log
```

---

## 5. Troubleshooting

### MQTT Listener terus crash / restart

1. Cek log: `sudo tail -f /var/log/supervisor/laravel-mqtt.log`
2. Pastikan variabel ENV `MQTT_HOST`, `MQTT_PORT`, `MQTT_USERNAME`, `MQTT_PASSWORD` benar
3. Coba jalankan manual untuk debug:
   ```bash
   cd /var/www/smarthom && php artisan mqtt:listen
   ```

### Scheduler tidak trigger

1. Pastikan `schedule:work` jalan: `sudo supervisorctl status laravel-scheduler`
2. Cek log scheduler: `sudo tail -f /var/log/supervisor/laravel-scheduler.log`
3. Verifikasi timezone di `.env`:
   ```
   APP_TIMEZONE=Asia/Jakarta
   ```

### Queue tidak memproses jobs

1. Cek: `sudo supervisorctl status laravel-queue`
2. Pastikan `QUEUE_CONNECTION=database` di `.env`
3. Pastikan tabel `jobs` ada: `php artisan queue:table && php artisan migrate`

---

## 6. Update deploy.sh

Setelah deploy, jalankan:
```bash
sudo supervisorctl restart smarthom:*
```

Ini sudah otomatis ditambahkan ke `deploy/deploy.sh`.

---

## 7. aaPanel (BT Panel) — GUI Alternative

Jika menggunakan **aaPanel**, bisa manage Supervisor lewat:
> **App Store → Supervisor Manager**

Tambahkan program dengan:
- **Name**: `laravel-mqtt`
- **Run User**: `www`
- **Command**: `php /www/wwwroot/smarthom/artisan mqtt:listen`
- **Number of processes**: `1`

Lakukan untuk ketiga program (mqtt, scheduler, queue).
