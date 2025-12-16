# 🏷️ Internal Label System - Backend API

Backend API untuk sistem label internal yang terintegrasi dengan ERP system. Sistem ini mengelola sinkronisasi data produksi dan pencetakan label dengan autentikasi.

---

## 🚀 Features

### ✅ Authentication
- Login dengan username & password
- Token-based authentication menggunakan Laravel Sanctum
- Protected API endpoints
- User management

### ✅ ERP Integration
- Sinkronisasi data `prod_header` dari ERP
- Sinkronisasi data `prod_label` dari ERP
- Automatic daily sync via scheduled command
- Comprehensive sync logging

### ✅ Label Printing
- List production orders ready for printing
- Get printable labels with complete information
- **Print data in semicolon-separated format** untuk ditampilkan di atas QR code
- Mark labels as printed
- Filter by production period

---

## 📋 Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Laravel 11.x

---

## 🛠️ Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd be-internal-label
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
```bash
cp .env.example .env
```

Edit `.env` file dan sesuaikan konfigurasi:

```env
# Database Local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Database ERP (SQL Server)
ERP_DB_CONNECTION=sqlsrv
ERP_DB_HOST=your_erp_host
ERP_DB_PORT=1433
ERP_DB_DATABASE=your_erp_database
ERP_DB_USERNAME=your_erp_username
ERP_DB_PASSWORD=your_erp_password
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Run Migrations
```bash
php artisan migrate
```

### 6. Seed Default Users
```bash
php artisan db:seed --class=UserSeeder
```

### 7. Start Development Server
```bash
php artisan serve
```

Server akan berjalan di `http://localhost:8000`

---

## 👤 Default Users

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Administrator |
| `user` | `password` | Test User |

---

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
Semua endpoint (kecuali `/login`) memerlukan Bearer Token:
```
Authorization: Bearer {your-token}
```

### Available Endpoints

#### 🔐 Authentication
- `POST /login` - Login dan dapatkan token
- `POST /logout` 🔒 - Logout dan revoke token
- `GET /me` 🔒 - Get current user info

#### 🔄 ERP Sync
- `GET /sync/prod-header` 🔒 - Sync prod headers dari ERP
- `GET /sync/prod-label` 🔒 - Sync prod labels dari ERP

#### 🏷️ Label Printing
- `GET /labels/prod-headers` 🔒 - List production orders
- `GET /labels/printable?prod_no={prod_no}` 🔒 - Get printable labels
- `POST /labels/mark-printed` 🔒 - Mark labels as printed

**Detailed API Documentation**: Lihat [API-DOCUMENTATION.md](API-DOCUMENTATION.md)

---

## 🧪 Testing

### Quick Test - Login
```bash
curl.exe -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data-binary "@test-login.json"
```

### Test Files
- `test-login.json` - Login request body
- `test-mark-printed.json` - Mark printed request body
- `test-auth.ps1` - PowerShell script untuk automated testing

### Manual Testing
Lihat dokumentasi lengkap:
- [AUTHENTICATION.md](AUTHENTICATION.md) - Authentication guide
- [API-DOCUMENTATION.md](API-DOCUMENTATION.md) - Complete API reference
- [test-mark-printed.md](test-mark-printed.md) - Mark printed testing guide

---

## 📊 Database Schema

### Tables

#### `users`
- `id` - Primary key
- `name` - User's full name
- `username` - Unique username untuk login
- `email` - Email address
- `password` - Hashed password
- `created_at`, `updated_at`

#### `prod_header`
Production order header dari ERP
- `prod_no` - Production number (unique)
- `prod_index` - Production period
- `customer` - Customer name
- `model` - Product model
- `sts` - Status (60 atau 70 untuk ready to print)
- Dan field lainnya...

#### `prod_label`
Label detail untuk setiap production order
- `prod_no` - Foreign key ke prod_header
- `lot_no` - Lot number
- `partno` - Part number (kode ERP)
- `status` - Status ('NS' untuk ready to print)
- `print_status` - 0: belum print, 1: sudah print
- Dan field lainnya...

#### `sync_logs`
Log untuk setiap sinkronisasi
- `sync_type` - 'prod_header' atau 'prod_label'
- `status` - 'success' atau 'failed'
- `total_fetched`, `new_records`, `updated_records`, `failed_records`
- `error_message`

#### `personal_access_tokens`
Token untuk autentikasi (Laravel Sanctum)

---

## ⚙️ Scheduled Tasks

### Daily ERP Sync
Sistem akan otomatis melakukan sinkronisasi setiap hari pada jam 02:00 WIB.

**Command**: `php artisan sync:erp-data`

**Manual Run**:
```bash
php artisan sync:erp-data
```

**Setup Cron** (Production):
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔑 Key Features Detail

### 1. Print Data Format
Setiap label yang siap print memiliki field `print_data` dengan format:
```
kode_erp;qty;lot_no;customer;prod_no
```

**Contoh**:
```
ERP-CODE-123;100;251100468384;CUSTOMER-ABC;251201063
```

Data ini ditampilkan di atas QR code saat printing.

**Mapping**:
- `kode_erp` → dari `partno` (prod_label)
- `qty` → hasil perhitungan `qty_order / snp`
- `lot_no` → dari `lot_no` (prod_label)
- `customer` → dari `customer` (prod_header)
- `prod_no` → production number

### 2. Print Status Management
- Label dengan `print_status = 0` akan muncul di endpoint `/labels/printable`
- Setelah di-print, frontend harus call `/labels/mark-printed` untuk update status
- Label yang sudah di-print (`print_status = 1`) tidak akan muncul lagi

### 3. Production Status Filter
- Hanya prod_header dengan `sts = 60` atau `sts = 70` yang bisa di-print
- Hanya prod_label dengan `status = 'NS'` yang bisa di-print

---

## 📁 Project Structure

```
be-internal-label/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── SyncErpData.php          # Daily sync command
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── AuthController.php    # Authentication
│   │           ├── ErpSyncController.php # ERP sync
│   │           └── LabelController.php   # Label printing
│   └── Models/
│       ├── User.php
│       ├── ProdHeader.php
│       ├── ProdLabel.php
│       └── SyncLog.php
├── database/
│   ├── migrations/
│   └── seeders/
│       └── UserSeeder.php
├── routes/
│   └── api.php                          # API routes
├── .env                                 # Environment config
├── API-DOCUMENTATION.md                 # Complete API docs
├── AUTHENTICATION.md                    # Auth guide
├── AUTHENTICATION-SUMMARY.md            # Auth implementation summary
└── README.md                            # This file
```

---

## 🔧 Development

### Create New User
```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'New User',
    'username' => 'newuser',
    'email' => 'newuser@example.com',
    'password' => bcrypt('password'),
]);
```

### Reset Database
```bash
php artisan migrate:fresh --seed
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 🐛 Troubleshooting

### Issue: "Unauthenticated" Error
**Solution**: Pastikan token dikirim di header:
```
Authorization: Bearer {token}
```

### Issue: Sync Failed
**Solution**: 
1. Cek koneksi ke database ERP di `.env`
2. Lihat log di tabel `sync_logs`
3. Cek error message di response

### Issue: No Printable Labels
**Solution**:
1. Pastikan prod_header memiliki `sts = 60` atau `70`
2. Pastikan prod_label memiliki `status = 'NS'` dan `print_status = 0`
3. Jalankan sync terlebih dahulu jika data belum ada

---

## 📝 Notes

1. **Security**: 
   - Gunakan HTTPS di production
   - Ganti default password setelah deployment
   - Jangan commit file `.env` ke repository

2. **Performance**:
   - Sync berjalan di background
   - Gunakan queue untuk sync yang besar (optional)

3. **Backup**:
   - Backup database secara regular
   - Simpan sync logs untuk audit trail

---

## 📞 Support

Untuk pertanyaan atau issue, silakan hubungi tim development.

---

## 📄 License

Internal use only.

---

**Version**: 1.0  
**Last Updated**: 2025-12-16  
**Laravel Version**: 11.x  
**PHP Version**: 8.2+
