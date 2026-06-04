# HDSF Mushroom — Inventory System
**V.2.2.0 © HDSF Organic Precision**

---

## 📦 Cara Instalasi

### 1. Persyaratan
- PHP 7.4+ (disarankan PHP 8.x)
- MySQL 5.7+ / MariaDB 10.3+
- Apache (XAMPP / Laragon / WAMP)

### 2. Letakkan File
Salin folder `hdsf_mushroom` ke direktori web server:
- **XAMPP** → `C:/xampp/htdocs/hdsf_mushroom`
- **Laragon** → `C:/laragon/www/hdsf_mushroom`

### 3. Import Database
1. Buka `http://localhost/phpmyadmin`
2. Klik tab **Import** → pilih file **`setup.sql`** → klik **Go**
3. Pastikan database `db_umkm_jamur` berhasil dibuat

### 4. Konfigurasi Koneksi
Edit `admin/koneksi.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // kosong jika XAMPP default
define('DB_NAME', 'db_umkm_jamur');
```

### 5. Akses Website
```
http://localhost/hdsf_mushroom/
```

---

## 🔑 Login Default
| Field    | Value      |
|----------|------------|
| Username | `admin`    |
| Password | `admin123` |
> ⚠️ Ganti password setelah login pertama!

---

## 🍄 Fitur v2.2.0

| Modul | Fitur |
|-------|-------|
| **Login** | Autentikasi bcrypt, proteksi session |
| **Dashboard** | Statistik ringkas, transaksi terbaru |
| **Pemasok** | CRUD + detail + input barang masuk langsung |
| **Pedagang** | CRUD + detail + input barang keluar + riwayat |
| **Barang Masuk** | CRUD + auto-create stock batch |
| **Barang Keluar** | CRUD + validasi stok FIFO + harga putih/coklat terpisah |
| **Stok** | Real-time FIFO-aware + tren 7 hari + top pemasok/pedagang |

### 🔄 Fitur FIFO (v2.2.0)
- Sistem menampilkan **antrian stok FIFO** sebelum input barang keluar
- Pengguna dapat melihat **dari batch pemasok mana** stok akan diambil
- Batch dengan tanggal masuk paling awal ditampilkan sebagai **prioritas #1**
- Sisa stok per-batch otomatis berkurang saat ada pengiriman
- Saat pengiriman dihapus, stok otomatis **dikembalikan ke batch asal**

### 💰 Harga Terpisah (v2.2.0)
- **Harga jamur putih** dan **harga jamur coklat** dicatat terpisah
- Subtotal dihitung per jenis: `(putih × harga_putih) + (coklat × harga_coklat)`
- Harga bersifat **opsional** — bisa diisi belakangan via menu Edit
- Preview kalkulasi real-time saat mengisi form

---

## 📁 Struktur File
```
hdsf_mushroom/
├── index.php
├── setup.sql
├── README.md
├── auth/
│   ├── login.php
│   ├── proses_login.php
│   └── logout.php
├── admin/
│   ├── koneksi.php          ← DB + FIFO helpers
│   ├── session_guard.php
│   ├── header.php
│   ├── footer.php
│   ├── fifo_widget.php      ← Widget antrian stok FIFO (reusable)
│   ├── dashboard.php
│   ├── stock.php
│   ├── pemasok/             ← index, tambah, edit, hapus, detail
│   ├── pedagang/            ← index, tambah, edit, hapus, detail
│   ├── barang_masuk/        ← index, tambah, edit, hapus
│   └── barang_keluar/       ← index, tambah, edit, hapus
└── assets/
    ├── css/custom.css
    └── js/custom.js
```

---

## ⚙️ Keamanan
- Session guard di semua halaman admin
- Password bcrypt (`password_hash`)
- MySQLi Prepared Statement (anti SQL Injection)
- `htmlspecialchars` di semua output (anti XSS)

---

## 🛠️ Teknologi
- **Backend**: PHP Native + MySQLi
- **Frontend**: Tailwind CSS CDN, Material Symbols, Inter Font
- **Database**: MySQL dengan Generated Column untuk subtotal

*Dibuat dengan ❤️ untuk kemudahan pengelolaan UMKM Jamur*
