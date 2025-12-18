# ⚡ Quick Deploy - Copy Paste Commands (UPDATED)

## 🎯 Recommended Setup: Hybrid (Cron + Supervisor)

**Struktur:**
- ✅ **Cron** untuk scheduler (sync otomatis)
- ✅ **Supervisor** untuk queue worker (manual sync)

---

## 📝 Langkah 1: Setup Cron untuk Scheduler

```bash
# Edit crontab untuk user www-data
sudo crontab -u www-data -e
```

**Tambahkan baris ini:**

```cron
* * * * * cd /var/www/be-internal-label && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Save: `Ctrl+O`, Enter, `Ctrl+X`

**Verify:**
```bash
sudo crontab -u www-data -l
```

---

## 📝 Langkah 2: Setup Supervisor untuk Queue Worker

### Opsi A: File Baru (Recommended)

```bash
# Buat file config baru
sudo nano /etc/supervisor/conf.d/inlab-queue.conf
```

**Isi dengan:**

```ini
[program:inlab-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/be-internal-label/artisan queue:work database --sleep=3 --tries=1 --max-time=3600 --timeout=900
directory=/var/www/be-internal-label
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/be-internal-label/storage/logs/queue-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
numprocs=2
```

Save: `Ctrl+O`, Enter, `Ctrl+X`

### Opsi B: Edit File yang Sudah Ada

```bash
# Edit file inlab-sync.conf yang sudah ada
sudo nano /etc/supervisor/conf.d/inlab-sync.conf
```

**Hapus atau comment bagian `[program:inlab-sync]`**, lalu tambahkan config `[program:inlab-queue]` di atas.

---

## 📝 Langkah 3: Load & Start

```bash
# Reload konfigurasi supervisor
sudo supervisorctl reread

# Update supervisor
sudo supervisorctl update

# Start queue worker
sudo supervisorctl start inlab-queue:*

# Stop scheduler di supervisor (jika masih ada)
sudo supervisorctl stop inlab-sync 2>/dev/null || true
```

---

## 📝 Langkah 4: Verify

```bash
# Check cron
sudo crontab -u www-data -l

# Check supervisor
sudo supervisorctl status
```

**Expected output:**
```
inlab-queue:inlab-queue_00       RUNNING   pid xxxxx, uptime 0:00:XX
inlab-queue:inlab-queue_01       RUNNING   pid xxxxx, uptime 0:00:XX
```

✅ **Tidak ada `inlab-sync`** (sudah diganti cron)

---

## ✅ Done!

### Test Scheduler (Cron):

```bash
# Test manual
cd /var/www/be-internal-label
sudo -u www-data php artisan schedule:run

# Check scheduled jobs
php artisan schedule:list
```

### Test Queue Worker:

1. Login ke aplikasi
2. Buka halaman Label List
3. Klik "Sync Prod Label"
4. Monitor log:
   ```bash
   tail -f /var/www/be-internal-label/storage/logs/queue-worker.log
   ```

---

## 📝 Catatan Penting

### Setiap Deploy Update Code:

```bash
cd /var/www/be-internal-label
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
sudo supervisorctl restart inlab-queue:*  # ⚠️ JANGAN LUPA!

# Cron tidak perlu restart (auto-reload setiap run)
```

---

## 🆘 Troubleshooting

### Scheduler tidak jalan?
```bash
# Check cron service
sudo systemctl status cron

# Test manual
sudo -u www-data php artisan schedule:run

# Monitor cron log
sudo tail -f /var/log/syslog | grep CRON
```

### Queue worker tidak jalan?
```bash
sudo supervisorctl status inlab-queue:*
sudo supervisorctl restart inlab-queue:*
tail -f /var/www/be-internal-label/storage/logs/queue-worker.log
```

### Check failed jobs:
```bash
cd /var/www/be-internal-label
php artisan queue:failed
php artisan queue:retry all
```

---

## 📊 Struktur Akhir

```
System Cron (www-data)
└── schedule:run setiap menit
    └── Sync otomatis daily

Supervisor
└── inlab-queue (2 workers)
    └── Manual sync on-demand
```

---

**Dokumentasi lengkap:**
- `DEPLOYMENT_GUIDE.md` - Setup detail
- `SCHEDULER_SETUP.md` - Perbandingan opsi scheduler
- `QUICK_REFERENCE.md` - Daily commands

