# 📅 Scheduler Setup - Production Best Practices

## ⚠️ PENTING: `schedule:work` vs `schedule:run`

### Masalah dengan `schedule:work`
- ❌ **Tidak recommended untuk production**
- ❌ Memory leak jika berjalan lama
- ❌ Tidak auto-reload code setelah deploy
- ❌ Lebih sulit di-monitor

### ✅ Solusi: `schedule:run`
- ✅ **Official Laravel recommendation**
- ✅ Ringan & reliable
- ✅ Auto-reload code setiap run
- ✅ Mudah di-monitor

---

## 🎯 Pilihan Setup untuk Production

### **Opsi 1: System Cron (RECOMMENDED)** ⭐

Ini adalah cara **official Laravel** dan paling recommended.

#### Setup:

```bash
# Edit crontab untuk user www-data
sudo crontab -u www-data -e
```

Tambahkan baris ini:

```cron
* * * * * cd /var/www/be-internal-label && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Penjelasan:**
- `* * * * *` = Jalankan setiap menit
- `cd /var/www/be-internal-label` = Pindah ke directory project
- `/usr/bin/php artisan schedule:run` = Jalankan Laravel scheduler
- `>> /dev/null 2>&1` = Redirect output (opsional)

#### Verify Cron:

```bash
# Lihat crontab yang sudah diset
sudo crontab -u www-data -l

# Monitor cron log
sudo tail -f /var/log/syslog | grep CRON
```

#### Keuntungan:
- ✅ Cara official Laravel
- ✅ Tidak perlu Supervisor untuk scheduler
- ✅ Lebih ringan & reliable
- ✅ Auto-reload code setiap run
- ✅ Standard practice di semua Laravel project

#### Kekurangan:
- ⚠️ Log tidak terpusat (harus lihat Laravel log)
- ⚠️ Monitoring terpisah dari Supervisor

---

### **Opsi 2: Supervisor dengan `schedule:run` Loop**

Jika Anda ingin **centralized monitoring** dengan Supervisor.

#### Konfigurasi:

```ini
[program:inlab-sync]
command=/bin/bash -c "while true; do /usr/bin/php /var/www/be-internal-label/artisan schedule:run; sleep 60; done"
directory=/var/www/be-internal-label
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/be-internal-label/storage/logs/scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
startsecs=0
startretries=3
```

**Penjelasan parameter penting:**
- `startsecs=0` = Supervisor tidak menganggap crash jika program exit cepat
- `startretries=3` = Retry 3x jika gagal start
- `stopwaitsecs=10` = Tunggu 10 detik sebelum force kill
- `while true; do ... sleep 60; done` = Loop yang menjalankan schedule:run setiap 60 detik

#### Keuntungan:
- ✅ Centralized monitoring via Supervisor
- ✅ Log terpusat di `scheduler.log`
- ✅ Mudah restart: `supervisorctl restart inlab-sync`

#### Kekurangan:
- ⚠️ Lebih kompleks dari cron
- ⚠️ Butuh resource Supervisor

---

### **Opsi 3: Hybrid (BEST OF BOTH WORLDS)** 🌟

Gunakan **Cron untuk scheduler** + **Supervisor untuk queue worker**.

#### Setup:

**1. Hapus `inlab-sync` dari Supervisor**

Edit `/etc/supervisor/conf.d/inlab-sync.conf`, hapus bagian `[program:inlab-sync]`, sisakan hanya `[program:inlab-queue]`.

**2. Setup Cron:**

```bash
sudo crontab -u www-data -e
```

Tambahkan:
```cron
* * * * * cd /var/www/be-internal-label && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**3. Reload Supervisor:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

**4. Verify:**

```bash
# Check cron
sudo crontab -u www-data -l

# Check supervisor (hanya queue worker)
sudo supervisorctl status
```

**Output yang diharapkan:**
```
inlab-queue:inlab-queue_00       RUNNING   ✅
inlab-queue:inlab-queue_01       RUNNING   ✅
```

#### Keuntungan:
- ✅ **Best practice Laravel** (cron untuk scheduler)
- ✅ **Supervisor untuk queue** (yang memang butuh daemon)
- ✅ Separation of concerns
- ✅ Lebih mudah maintenance

---

## 📊 Perbandingan Opsi

| Aspek | Opsi 1: Cron | Opsi 2: Supervisor Loop | Opsi 3: Hybrid |
|-------|--------------|-------------------------|----------------|
| **Complexity** | ⭐ Simple | ⭐⭐ Medium | ⭐⭐ Medium |
| **Laravel Best Practice** | ✅ Yes | ⚠️ Workaround | ✅ Yes |
| **Centralized Monitoring** | ❌ No | ✅ Yes | ⚠️ Partial |
| **Resource Usage** | ⭐⭐⭐ Low | ⭐⭐ Medium | ⭐⭐ Medium |
| **Maintenance** | ⭐⭐⭐ Easy | ⭐⭐ Medium | ⭐⭐⭐ Easy |
| **Recommended** | ✅ Yes | ⚠️ OK | ✅ **BEST** |

---

## 🚀 Rekomendasi Kami

### **Gunakan Opsi 3: Hybrid** 🌟

**Setup:**
1. **Cron** untuk scheduler (sync otomatis)
2. **Supervisor** untuk queue worker (manual sync)

**Alasan:**
- ✅ Mengikuti Laravel best practice
- ✅ Supervisor fokus untuk queue (yang memang butuh daemon)
- ✅ Scheduler ringan via cron
- ✅ Mudah maintenance

---

## 📝 Migration Guide

### Jika Anda sudah pakai `schedule:work` di Supervisor:

#### Step 1: Setup Cron

```bash
sudo crontab -u www-data -e
```

Tambahkan:
```cron
* * * * * cd /var/www/be-internal-label && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

#### Step 2: Stop & Disable inlab-sync di Supervisor

```bash
# Stop scheduler di supervisor
sudo supervisorctl stop inlab-sync

# Edit config, comment atau hapus [program:inlab-sync]
sudo nano /etc/supervisor/conf.d/inlab-sync.conf

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

#### Step 3: Verify

```bash
# Check cron
sudo crontab -u www-data -l

# Check supervisor (hanya queue worker)
sudo supervisorctl status

# Monitor Laravel log untuk memastikan scheduler jalan
tail -f /var/www/be-internal-label/storage/logs/laravel.log
```

#### Step 4: Test

Tunggu beberapa menit, lalu check:

```bash
# Check sync logs
mysql -u user -p -e "SELECT * FROM be_internal_label.sync_logs ORDER BY created_at DESC LIMIT 5;"
```

---

## 🔍 Monitoring Scheduler

### Dengan Cron:

```bash
# Monitor cron execution
sudo tail -f /var/log/syslog | grep CRON

# Monitor Laravel log
tail -f /var/www/be-internal-label/storage/logs/laravel.log

# Check sync logs di database
SELECT * FROM sync_logs WHERE sync_type IN ('prod_header', 'prod_label') ORDER BY created_at DESC LIMIT 10;
```

### Dengan Supervisor:

```bash
# Check status
sudo supervisorctl status inlab-sync

# Monitor log
tail -f /var/www/be-internal-label/storage/logs/scheduler.log

# Via supervisor
sudo supervisorctl tail -f inlab-sync stdout
```

---

## 🆘 Troubleshooting

### Cron tidak jalan?

```bash
# Check cron service
sudo systemctl status cron

# Restart cron
sudo systemctl restart cron

# Check crontab syntax
sudo crontab -u www-data -l

# Check permissions
ls -la /var/www/be-internal-label/artisan
```

### Scheduler tidak eksekusi scheduled jobs?

```bash
# Test manual
cd /var/www/be-internal-label
sudo -u www-data php artisan schedule:run

# Check scheduled jobs list
php artisan schedule:list

# Check Laravel log
tail -f storage/logs/laravel.log
```

---

## ✅ Final Recommendation

**Untuk production server Anda:**

1. ✅ **Gunakan Cron** untuk scheduler (sync otomatis)
2. ✅ **Gunakan Supervisor** untuk queue worker (manual sync)
3. ✅ Hapus `inlab-sync` dari Supervisor
4. ✅ Fokus Supervisor hanya untuk `inlab-queue`

**Struktur akhir:**
```
Cron (system)
└── schedule:run setiap menit
    └── Sync otomatis (daily)

Supervisor
└── inlab-queue (2 workers)
    └── Manual sync (on-demand)
```

**Ini adalah setup yang paling clean, maintainable, dan mengikuti Laravel best practices!** ✨

---

**Dokumentasi Laravel:**
- https://laravel.com/docs/11.x/scheduling#running-the-scheduler
