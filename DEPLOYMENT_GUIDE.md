# 🚀 Deployment Guide - Production Setup (UPDATED)

## 📋 Ringkasan Setup Production

### **Recommended Setup (Hybrid):** ⭐

```
System Cron
└── schedule:run (setiap menit)
    └── Sync otomatis daily

Supervisor  
└── inlab-queue (2 workers)
    └── Manual sync on-demand
```

**Kenapa Hybrid?**
- ✅ Mengikuti Laravel best practice
- ✅ Cron untuk scheduler (official way)
- ✅ Supervisor untuk queue worker (yang memang butuh daemon)
- ✅ Lebih mudah maintenance

---

## 🔧 Deployment Steps

### **Step 1: Setup Cron untuk Scheduler**

```bash
# Edit crontab untuk user www-data
sudo crontab -u www-data -e
```

Tambahkan baris ini:

```cron
* * * * * cd /var/www/be-internal-label && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Save dan exit.

**Verify:**
```bash
sudo crontab -u www-data -l
```

---

### **Step 2: Setup Supervisor untuk Queue Worker**

#### A. Jika Belum Ada File Config:

```bash
# Buat file config baru
sudo nano /etc/supervisor/conf.d/inlab-queue.conf
```

Isi dengan:

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

#### B. Jika Sudah Ada `inlab-sync.conf`:

**Opsi 1: Pisahkan ke file terpisah (Recommended)**

```bash
# Buat file baru untuk queue
sudo nano /etc/supervisor/conf.d/inlab-queue.conf
```

Isi dengan config di atas, lalu:

```bash
# Edit file lama, hapus atau comment [program:inlab-sync]
sudo nano /etc/supervisor/conf.d/inlab-sync.conf
```

**Opsi 2: Gabung di file yang sama**

```bash
# Edit file yang sudah ada
sudo nano /etc/supervisor/conf.d/inlab-sync.conf
```

Hapus atau comment bagian `[program:inlab-sync]`, tambahkan `[program:inlab-queue]`.

---

### **Step 3: Load & Start Queue Worker**

```bash
# Reload konfigurasi
sudo supervisorctl reread

# Update supervisor
sudo supervisorctl update

# Start queue worker
sudo supervisorctl start inlab-queue:*

# Stop scheduler jika masih ada
sudo supervisorctl stop inlab-sync 2>/dev/null || true
```

---

### **Step 4: Verify Setup**

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

**Tidak ada `inlab-sync` lagi** (sudah diganti cron).

---

### **Step 5: Test**

#### Test Scheduler (Cron):

```bash
# Manual test
cd /var/www/be-internal-label
sudo -u www-data php artisan schedule:run

# Check scheduled jobs
php artisan schedule:list

# Monitor Laravel log
tail -f storage/logs/laravel.log
```

#### Test Queue Worker:

1. Login ke aplikasi frontend
2. Klik "Sync Prod Label"
3. Monitor log:
   ```bash
   tail -f /var/www/be-internal-label/storage/logs/queue-worker.log
   ```

---

## 📊 Monitoring

### Scheduler (Cron)

```bash
# Monitor cron execution
sudo tail -f /var/log/syslog | grep CRON

# Monitor Laravel log
tail -f /var/www/be-internal-label/storage/logs/laravel.log

# Check sync logs
mysql -u user -p -e "SELECT * FROM be_internal_label.sync_logs WHERE sync_type IN ('prod_header', 'prod_label') ORDER BY created_at DESC LIMIT 10;"
```

### Queue Worker (Supervisor)

```bash
# Check status
sudo supervisorctl status inlab-queue:*

# Monitor log
tail -f /var/www/be-internal-label/storage/logs/queue-worker.log

# Via supervisor
sudo supervisorctl tail -f inlab-queue:inlab-queue_00 stdout
```

---

## 🔄 Workflow Deploy

Setiap kali deploy update code:

```bash
cd /var/www/be-internal-label

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear & cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ⚠️ PENTING: Restart queue worker
sudo supervisorctl restart inlab-queue:*

# Cron tidak perlu restart (auto-reload setiap run)
```

---

## 🆘 Troubleshooting

### Scheduler Tidak Jalan

```bash
# Check cron service
sudo systemctl status cron

# Restart cron
sudo systemctl restart cron

# Test manual
cd /var/www/be-internal-label
sudo -u www-data php artisan schedule:run

# Check permissions
ls -la /var/www/be-internal-label/artisan

# Check scheduled jobs list
php artisan schedule:list
```

### Queue Worker Tidak Jalan

```bash
# Check status
sudo supervisorctl status inlab-queue:*

# Check log
tail -f /var/www/be-internal-label/storage/logs/queue-worker.log

# Restart
sudo supervisorctl restart inlab-queue:*

# Check error log
sudo supervisorctl tail inlab-queue:inlab-queue_00 stderr
```

### Job Tidak Diproses

```bash
# Check jobs in queue
mysql -u user -p -e "SELECT * FROM be_internal_label.jobs;"

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear queue
php artisan queue:clear
```

---

## 📁 File & Log Locations

```
/etc/supervisor/conf.d/
├── inlab-queue.conf              # Queue worker config
└── inlab-sync.conf (optional)    # Bisa dihapus jika pakai cron

/var/www/be-internal-label/storage/logs/
├── queue-worker.log              # Manual sync log
└── laravel.log                   # General log (termasuk scheduler)

/var/log/
└── syslog                        # Cron execution log
```

---

## ⚙️ Environment Configuration

Pastikan `.env` di production:

```env
# Queue Configuration
QUEUE_CONNECTION=database

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=be_internal_label
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info
```

---

## 📊 Struktur Akhir

| Component | Method | Purpose | Monitoring |
|-----------|--------|---------|------------|
| **Scheduler** | System Cron | Sync otomatis (daily) | Laravel log + syslog |
| **Queue Worker** | Supervisor | Manual sync (on-demand) | queue-worker.log |

---

## ✅ Checklist Deployment

- [ ] Setup cron: `sudo crontab -u www-data -e`
- [ ] Verify cron: `sudo crontab -u www-data -l`
- [ ] Create/edit supervisor config: `/etc/supervisor/conf.d/inlab-queue.conf`
- [ ] Remove/comment `inlab-sync` dari supervisor
- [ ] `supervisorctl reread && update`
- [ ] `supervisorctl start inlab-queue:*`
- [ ] Verify: `supervisorctl status`
- [ ] Test scheduler: `php artisan schedule:run`
- [ ] Test queue: Klik "Sync Prod Label" di frontend
- [ ] Monitor logs
- [ ] Check database: `SELECT * FROM sync_logs`

---

## 🔐 Security & Permissions

```bash
# Pastikan permissions benar
sudo chown -R www-data:www-data /var/www/be-internal-label/storage
sudo chmod -R 775 /var/www/be-internal-label/storage

# Pastikan artisan executable
sudo chmod +x /var/www/be-internal-label/artisan
```

---

## 📞 Support

**Dokumentasi terkait:**
- `SCHEDULER_SETUP.md` - Perbandingan opsi scheduler
- `QUICK_DEPLOY.md` - Quick deployment guide
- `QUICK_REFERENCE.md` - Daily commands cheat sheet

**Laravel Docs:**
- [Task Scheduling](https://laravel.com/docs/11.x/scheduling)
- [Queues](https://laravel.com/docs/11.x/queues)

---

**Dibuat:** 2025-12-18  
**Update:** 2025-12-18 (Hybrid setup recommendation)  
**Versi:** 3.0
