# 📚 Dokumentasi Queue Worker - Manual Sync

Dokumentasi lengkap untuk fitur Manual Sync menggunakan Laravel Queue Jobs.

---

## 📖 Daftar Dokumentasi

### 1. **QUICK_DEPLOY.md** ⚡ MULAI DARI SINI!
**Untuk:** Quick deployment (5 menit)  
**Isi:** Copy-paste commands untuk deploy cepat
- Edit konfigurasi supervisor
- Load & start queue worker
- Verify & test

### 2. **DEPLOYMENT_GUIDE.md** 📘 BACA INI UNTUK DETAIL!
**Untuk:** DevOps / System Administrator  
**Isi:** Panduan lengkap setup production
- Konfigurasi supervisor detail
- Monitoring & troubleshooting
- Workflow deployment
- Emergency commands

### 3. **QUICK_REFERENCE.md** 📋
**Untuk:** Developer / Admin (daily use)  
**Isi:** Cheat sheet perintah-perintah penting
- Perintah sehari-hari
- Restart worker
- Check status
- Troubleshooting cepat

### 4. **QUEUE_SETUP.md** 🔧
**Untuk:** Developer  
**Isi:** Penjelasan teknis queue setup
- Cara kerja queue
- Konfigurasi Laravel
- Supervisor setup detail
- Monitoring queue jobs

### 5. **TESTING_MANUAL_SYNC.md** 🧪
**Untuk:** Developer / QA  
**Isi:** Panduan testing fitur manual sync
- Test via frontend
- Test via API
- Monitor progress
- Verify data

### 6. **supervisor-laravel-worker.conf** ⚙️
**Untuk:** Production server  
**Isi:** File konfigurasi Supervisor (reference)
- Konfigurasi `inlab-sync` (scheduler)
- Konfigurasi `inlab-queue` (queue worker)

---

## 🚀 Quick Start

### Development (Local)
```bash
# Terminal 1: Laravel Server
php artisan serve

# Terminal 2: Queue Worker
php artisan queue:work --verbose
```

### Production (Server) - SUDAH ADA SCHEDULER

Anda sudah punya `inlab-sync` untuk scheduler. Sekarang tambahkan `inlab-queue`:

```bash
# 1. Edit konfigurasi supervisor yang sudah ada
sudo nano /etc/supervisor/conf.d/inlab-sync.conf

# 2. Tambahkan konfigurasi inlab-queue (lihat QUICK_DEPLOY.md)

# 3. Load & start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start inlab-queue:*

# 4. Verify
sudo supervisorctl status
```

**Expected:**
```
inlab-sync                       RUNNING   ✅ Sync otomatis
inlab-queue:inlab-queue_00       RUNNING   ✅ Manual sync
inlab-queue:inlab-queue_01       RUNNING   ✅ Manual sync
```

---

## ⚠️ PENTING!

### Setiap Deploy Update Code:
```bash
cd /var/www/be-internal-label
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
sudo supervisorctl restart inlab-queue:*  # ⚠️ JANGAN LUPA!
```

**Jangan lupa restart worker!** Jika tidak, worker akan tetap menggunakan code lama.

---

## 🔍 Monitoring

### Check Worker Status
```bash
# Status semua program
sudo supervisorctl status

# Status queue worker saja
sudo supervisorctl status inlab-queue:*
```

### Monitor Log Real-time
```bash
# Log queue worker (manual sync)
tail -f /var/www/be-internal-label/storage/logs/queue-worker.log

# Log scheduler (sync otomatis)
tail -f /var/www/be-internal-label/storage/logs/scheduler.log

# Log Laravel
tail -f /var/www/be-internal-label/storage/logs/laravel.log
```

### Check Database
```bash
# Login ke MySQL
mysql -u your_user -p be_internal_label

# Check sync logs
SELECT * FROM sync_logs ORDER BY created_at DESC LIMIT 10;

# Check jobs in queue
SELECT * FROM jobs;

# Check failed jobs
SELECT * FROM failed_jobs;
```

---

## 📞 Troubleshooting

Jika manual sync tidak berjalan:

1. ✅ **Check worker running:**
   ```bash
   sudo supervisorctl status
   ```

2. ✅ **Check log worker:**
   ```bash
   tail -f storage/logs/worker.log
   ```

3. ✅ **Check failed jobs:**
   ```bash
   php artisan queue:failed
   ```

4. ✅ **Restart worker:**
   ```bash
   sudo supervisorctl restart internal-label-worker:*
   ```

Lihat **DEPLOYMENT_GUIDE.md** untuk troubleshooting lengkap.

---

## 📁 File Structure

```
be-internal-label/
├── app/
│   ├── Jobs/
│   │   └── SyncProdData.php          # Queue Job untuk sync
│   └── Http/Controllers/Api/
│       └── ErpSyncController.php     # Controller dengan syncManual()
├── routes/
│   └── api.php                       # Route POST /api/sync/manual
├── supervisor-laravel-worker.conf    # Konfigurasi Supervisor
├── DEPLOYMENT_GUIDE.md               # 📘 Panduan deployment
├── QUICK_REFERENCE.md                # 📋 Cheat sheet
├── QUEUE_SETUP.md                    # 🔧 Setup queue detail
├── TESTING_MANUAL_SYNC.md            # 🧪 Panduan testing
└── README_QUEUE.md                   # 📚 File ini
```

---

## 🎯 Cara Kerja

```
User klik "Sync Manual"
    ↓
POST /api/sync/manual
    ↓
Dispatch SyncProdData job
    ↓
Response: "Job queued" (langsung!)
    ↓
Supervisor → Queue Worker
    ↓
Process job (~5 menit)
    ↓
Data synced ✅
```

---

## 📚 Resources

- [Laravel Queue Documentation](https://laravel.com/docs/11.x/queues)
- [Supervisor Documentation](http://supervisord.org/)
- [Laravel Horizon](https://laravel.com/docs/11.x/horizon) (alternative untuk Redis)

---

## ✅ Checklist Production

- [ ] Supervisor installed
- [ ] Konfigurasi worker di `/etc/supervisor/conf.d/`
- [ ] Path project sudah benar
- [ ] Worker running (`supervisorctl status`)
- [ ] `.env` QUEUE_CONNECTION=database
- [ ] Table `jobs` exists
- [ ] Test manual sync berhasil
- [ ] Log monitoring setup

---

**Dibuat:** 2025-12-18  
**Update terakhir:** 2025-12-18  
**Versi:** 1.0
