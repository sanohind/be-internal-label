# 📋 Quick Reference - Queue Worker Production

## Setup Awal (Hanya Sekali)

```bash
# 1. Edit konfigurasi supervisor yang sudah ada
sudo nano /etc/supervisor/conf.d/inlab-sync.conf

# 2. Tambahkan konfigurasi inlab-queue di bawah inlab-sync
#    (Lihat file supervisor-laravel-worker.conf)

# 3. Load konfigurasi
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start inlab-queue:*
```

---

## Perintah Sehari-hari

### Check Status
```bash
# Status semua
sudo supervisorctl status

# Status queue worker saja
sudo supervisorctl status inlab-queue:*
```

### Restart Worker (setelah deploy)
```bash
# Restart queue worker (PENTING setiap deploy!)
sudo supervisorctl restart inlab-queue:*

# Restart scheduler juga (jika perlu)
sudo supervisorctl restart inlab-sync
```

### Monitor Log
```bash
# Log queue worker (manual sync)
tail -f /var/www/be-internal-label/storage/logs/queue-worker.log

# Log scheduler (sync otomatis)
tail -f /var/www/be-internal-label/storage/logs/scheduler.log

# Log Laravel
tail -f /var/www/be-internal-label/storage/logs/laravel.log
```

---

## Workflow Deploy

```bash
cd /var/www/be-internal-label
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
sudo supervisorctl restart inlab-queue:*  # ⚠️ PENTING!
```

---

## Troubleshooting

### Worker tidak jalan?
```bash
sudo supervisorctl status inlab-queue:*
sudo supervisorctl restart inlab-queue:*
```

### Job gagal terus?
```bash
php artisan queue:failed
php artisan queue:retry all
```

### Clear queue?
```bash
php artisan queue:clear
php artisan queue:flush
```

### Check database
```bash
mysql -u user -p -e "SELECT * FROM be_internal_label.sync_logs ORDER BY created_at DESC LIMIT 10;"
mysql -u user -p -e "SELECT * FROM be_internal_label.jobs;"
mysql -u user -p -e "SELECT * FROM be_internal_label.failed_jobs;"
```

---

## Program Supervisor

| Program | Fungsi | Log File |
|---------|--------|----------|
| `inlab-sync` | Sync otomatis (daily) | `scheduler.log` |
| `inlab-queue` | Manual sync (on-demand) | `queue-worker.log` |

**Keduanya harus RUNNING!**

---

## File Penting

- **Konfigurasi:** `/etc/supervisor/conf.d/inlab-sync.conf`
- **Log Queue:** `/var/www/be-internal-label/storage/logs/queue-worker.log`
- **Log Scheduler:** `/var/www/be-internal-label/storage/logs/scheduler.log`
- **Log Laravel:** `/var/www/be-internal-label/storage/logs/laravel.log`

---

## Kontak

Dokumentasi lengkap: `DEPLOYMENT_GUIDE.md`

