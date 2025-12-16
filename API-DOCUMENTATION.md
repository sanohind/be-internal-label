# 📖 API Documentation - Internal Label System

## Base URL
```
http://localhost:8000/api
```

---

## 🔐 Authentication

Semua endpoint (kecuali `/login`) memerlukan autentikasi menggunakan Bearer Token.

### Headers untuk Protected Endpoints:
```
Authorization: Bearer {your-token}
Accept: application/json
```

---

## 📋 Endpoints

### 1. Authentication

#### 1.1 Login
**Endpoint**: `POST /login`

**Request Body**:
```json
{
  "username": "admin",
  "password": "password"
}
```

**Success Response** (200):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Administrator",
      "username": "admin",
      "email": "admin@example.com"
    },
    "token": "1|abcdefghijklmnopqrstuvwxyz..."
  }
}
```

**Error Response** (422):
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "username": ["The provided credentials are incorrect."]
  }
}
```

---

#### 1.2 Get Current User
**Endpoint**: `GET /me` 🔒

**Success Response** (200):
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Administrator",
      "username": "admin",
      "email": "admin@example.com"
    }
  }
}
```

---

#### 1.3 Logout
**Endpoint**: `POST /logout` 🔒

**Success Response** (200):
```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

### 2. ERP Sync

#### 2.1 Sync Prod Headers
**Endpoint**: `GET /sync/prod-header` 🔒

**Query Parameters**:
- `prod_index` (optional): Filter by production period (e.g., "2512")

**Success Response** (200):
```json
{
  "success": true,
  "message": "Sync completed successfully",
  "stats": {
    "total_fetched": 150,
    "new_records": 10,
    "updated_records": 140,
    "failed_records": 0
  }
}
```

---

#### 2.2 Sync Prod Labels
**Endpoint**: `GET /sync/prod-label` 🔒

**Query Parameters**:
- `prod_index` (optional): Filter by production period (e.g., "2512")

**Success Response** (200):
```json
{
  "success": true,
  "message": "Sync completed successfully",
  "stats": {
    "total_fetched": 500,
    "new_records": 50,
    "updated_records": 450,
    "failed_records": 0
  }
}
```

---

### 3. Label Printing

#### 3.1 List Prod Headers
**Endpoint**: `GET /labels/prod-headers` 🔒

**Query Parameters**:
- `prod_index` (optional): Filter by production period

**Success Response** (200):
```json
{
  "success": true,
  "message": "Prod headers retrieved successfully",
  "count": 5,
  "data": [
    {
      "id": 1,
      "prod_index": "2512",
      "prod_no": "251201063",
      "planning_date": "2025-12-01",
      "item": "ITEM-001",
      "old_partno": "PART-001",
      "description": "Sample Part Description",
      "customer": "CUSTOMER-ABC",
      "model": "MODEL-X",
      "unique_no": "UNQ123",
      "snp": 10,
      "sts": 60,
      "qty_order": 1000,
      "created_at": "2025-12-15T10:00:00.000000Z",
      "updated_at": "2025-12-15T10:00:00.000000Z"
    }
  ]
}
```

---

#### 3.2 Get Printable Labels
**Endpoint**: `GET /labels/printable` 🔒

**Query Parameters**:
- `prod_no` (required): Production number

**Example**: `GET /labels/printable?prod_no=251201063`

**Success Response** (200):
```json
{
  "success": true,
  "message": "Printable labels retrieved successfully",
  "count": 3,
  "prod_header": {
    "prod_no": "251201063",
    "prod_index": "2512",
    "sts": 60
  },
  "data": [
    {
      "label_id": 1,
      "lot_no": "251100468384",
      "model": "MODEL-X",
      "unique_no": "UNQ123",
      "part_no": "PART-001",
      "description": "Sample Part Description",
      "date": "2025",
      "qty": 100,
      "lot_date": "2025-11-01",
      "lot_qty": 500,
      "print_data": "ERP-CODE-123;100;251100468384;CUSTOMER-ABC;251201063"
    },
    {
      "label_id": 2,
      "lot_no": "251100468385",
      "model": "MODEL-X",
      "unique_no": "UNQ123",
      "part_no": "PART-001",
      "description": "Sample Part Description",
      "date": "2025",
      "qty": 100,
      "lot_date": "2025-11-01",
      "lot_qty": 500,
      "print_data": "ERP-CODE-123;100;251100468385;CUSTOMER-ABC;251201063"
    }
  ]
}
```

**Field Descriptions**:
- `label_id`: ID label untuk mark as printed
- `lot_no`: Nomor lot
- `model`: Model produk
- `unique_no`: Nomor unik
- `part_no`: Part number / Kode ERP (dari partno di prod_label, sama dengan kode_erp)
- `description`: Deskripsi produk
- `date`: Tahun produksi (dari prod_index)
- `qty`: Quantity (qty_order / snp)
- `lot_date`: Tanggal lot
- `lot_qty`: Quantity lot
- **`print_data`**: **Data untuk ditampilkan di atas QR code** dalam format:
  ```
  kode_erp;qty;lot_no;customer;prod_no
  ```
  - `kode_erp`: Dari partno (prod_label) - sama dengan part_no
  - `qty`: Hasil perhitungan qty_order / snp
  - `lot_no`: Dari prod_label
  - `customer`: Dari prod_header
  - `prod_no`: Production number

**Error Response** (404):
```json
{
  "success": false,
  "message": "Prod header not found or not ready for printing (sts must be 60 or 70)"
}
```

---

#### 3.3 Mark Labels as Printed
**Endpoint**: `POST /labels/mark-printed` 🔒

**Request Body**:
```json
{
  "label_ids": [1, 2, 3]
}
```

**Success Response** (200):
```json
{
  "success": true,
  "message": "Labels marked as printed successfully",
  "updated_count": 3
}
```

**Validation Error** (422):
```json
{
  "message": "The selected label_ids.0 is invalid.",
  "errors": {
    "label_ids.0": ["The selected label_ids.0 is invalid."]
  }
}
```

---

## 🔒 Protected Endpoints

Semua endpoint berikut memerlukan token autentikasi:

### Auth
- `POST /logout`
- `GET /me`

### ERP Sync
- `GET /sync/prod-header`
- `GET /sync/prod-label`

### Label Printing
- `GET /labels/prod-headers`
- `GET /labels/printable`
- `POST /labels/mark-printed`

---

## ❌ Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Error message",
  "error": "Detailed error information"
}
```

---

## 🧪 Testing Examples

### Using cURL

#### 1. Login
```bash
curl.exe -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data-binary "@test-login.json"
```

#### 2. Get Printable Labels
```bash
curl.exe -X GET "http://localhost:8000/api/labels/printable?prod_no=251201063" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

#### 3. Mark as Printed
```bash
curl.exe -X POST http://localhost:8000/api/labels/mark-printed \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data-binary "@test-mark-printed.json"
```

---

## 📊 Complete Workflow

### Frontend Print Label Flow:

1. **Login**
   ```
   POST /api/login
   → Get token
   ```

2. **Get List of Production Orders**
   ```
   GET /api/labels/prod-headers
   → Display list to user
   ```

3. **Select Production Order & Get Labels**
   ```
   GET /api/labels/printable?prod_no={selected_prod_no}
   → Get labels ready to print
   ```

4. **Display Labels & Print**
   - Show label information
   - **Display `print_data` above QR code** (format: `kode_erp;qty;lot_no;customer;prod_no`)
   - Generate QR code from label data
   - Print labels

5. **Mark Labels as Printed**
   ```
   POST /api/labels/mark-printed
   Body: { "label_ids": [1, 2, 3] }
   → Update print_status to 1
   ```

6. **Logout (Optional)**
   ```
   POST /api/logout
   → Revoke token
   ```

---

## 🔑 Default Credentials

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Administrator |
| `user` | `password` | Test User |

---

## 📝 Notes

1. **Token Management**: 
   - Token tidak expire secara default
   - Setiap login baru akan menghapus token lama
   - Simpan token dengan aman di frontend (localStorage/sessionStorage)

2. **Print Data Format**:
   - Field `print_data` berisi data yang dipisahkan dengan semicolon (`;`)
   - Format: `kode_erp;qty;lot_no;customer;prod_no`
   - Data ini ditampilkan di atas QR code saat printing

3. **Print Status**:
   - `print_status = 0`: Belum di-print
   - `print_status = 1`: Sudah di-print
   - Hanya label dengan `print_status = 0` yang muncul di `/labels/printable`

4. **Production Status**:
   - Hanya prod_header dengan `sts = 60` atau `sts = 70` yang bisa di-print
   - Hanya prod_label dengan `status = 'NS'` yang bisa di-print

---

## 📚 Additional Documentation

- **AUTHENTICATION.md**: Detailed authentication guide
- **AUTHENTICATION-SUMMARY.md**: Implementation summary
- **test-mark-printed.md**: Testing guide for mark-printed endpoint

---

**Last Updated**: 2025-12-16
**Version**: 1.0
