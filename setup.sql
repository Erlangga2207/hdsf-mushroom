-- ============================================================
--  TABEL USER
-- ============================================================
CREATE TABLE IF NOT EXISTS user (
    id_user   INT(11) AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(100) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL
);

-- ============================================================
--  TABEL PEMASOK
-- ============================================================
CREATE TABLE IF NOT EXISTS pemasok (
    id_pemasok INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(150) NOT NULL,
    no_hp      VARCHAR(20)  NOT NULL,
    alamat     TEXT         NOT NULL
);

-- ============================================================
--  TABEL PEDAGANG
-- ============================================================
CREATE TABLE IF NOT EXISTS pedagang (
    id_pedagang INT(11) AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(150) NOT NULL,
    no_hp       VARCHAR(20)  NOT NULL,
    alamat      TEXT         NOT NULL
);

-- ============================================================
--  TABEL BARANG MASUK
--  harga_beli_putih & harga_beli_coklat = harga beli dari pemasok
--  pendapatan = generated column otomatis
-- ============================================================
CREATE TABLE IF NOT EXISTS barang_masuk (
    id_barang_masuk  INT(11)        AUTO_INCREMENT PRIMARY KEY,
    id_pemasok       INT(11)        NOT NULL,
    tgl_setor        DATE           NOT NULL,
    jamur_putih      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    jamur_coklat     DECIMAL(10,2)  NOT NULL DEFAULT 0,
    jumlah_barang    DECIMAL(10,2)  GENERATED ALWAYS AS (jamur_putih + jamur_coklat) STORED,
    harga_beli_putih  DECIMAL(12,2) NULL DEFAULT NULL,
    harga_beli_coklat DECIMAL(12,2) NULL DEFAULT NULL,
    pendapatan        DECIMAL(16,2) GENERATED ALWAYS AS (
                          COALESCE(jamur_putih  * harga_beli_putih,  0) +
                          COALESCE(jamur_coklat * harga_beli_coklat, 0)
                      ) STORED,
    FOREIGN KEY (id_pemasok) REFERENCES pemasok(id_pemasok) ON DELETE CASCADE
);

-- ============================================================
--  TABEL STOCK — saldo stok per-batch (FIFO tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock (
    id_stock             INT(11)       AUTO_INCREMENT PRIMARY KEY,
    id_barang_masuk      INT(11)       NOT NULL UNIQUE,
    jumlah_jamur_putih   DECIMAL(10,2) NOT NULL DEFAULT 0,
    jumlah_jamur_coklat  DECIMAL(10,2) NOT NULL DEFAULT 0,
    sisa_putih           DECIMAL(10,2) NOT NULL DEFAULT 0,
    sisa_coklat          DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_barang_masuk) REFERENCES barang_masuk(id_barang_masuk) ON DELETE CASCADE
);

-- ============================================================
--  TABEL BARANG KELUAR
--  harga_putih & harga_coklat = harga jual ke pedagang (terpisah)
--  subtotal = generated column
-- ============================================================
CREATE TABLE IF NOT EXISTS barang_keluar (
    id_barang_keluar INT(11)        AUTO_INCREMENT PRIMARY KEY,
    id_stock         INT(11)        NOT NULL,
    id_pedagang      INT(11)        NOT NULL,
    tgl_kirim        DATE           NOT NULL,
    jamur_putih      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    jamur_coklat     DECIMAL(10,2)  NOT NULL DEFAULT 0,
    harga_putih      DECIMAL(12,2)  NULL DEFAULT NULL,
    harga_coklat     DECIMAL(12,2)  NULL DEFAULT NULL,
    subtotal         DECIMAL(16,2)  GENERATED ALWAYS AS (
                         COALESCE(jamur_putih  * harga_putih,  0) +
                         COALESCE(jamur_coklat * harga_coklat, 0)
                     ) STORED,
    FOREIGN KEY (id_stock)    REFERENCES stock(id_stock) ON DELETE CASCADE,
    FOREIGN KEY (id_pedagang) REFERENCES pedagang(id_pedagang)
);

-- ============================================================
--  DATA AWAL: Admin default  |  Username: admin  Password: admin123
-- ============================================================
INSERT IGNORE INTO user (username, password)
VALUES ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
--  DATA CONTOH
-- ============================================================
INSERT IGNORE INTO pemasok (id_pemasok, nama, no_hp, alamat) VALUES
(1, 'UD. Jamur Barokah',   '0812-3456-7890', 'Jl. Raya Agro No. 45, Sukabumi'),
(2, 'Subur Makmur Farm',   '0821-9988-1122', 'Kawasan Industri Tani Blok C, Sukabumi'),
(3, 'Tunas Hijau Mandiri', '0856-4433-2211', 'Dusun Mekar Wangi RT 04, Garut'),
(4, 'Sinergi Organik',     '0877-6655-4433', 'Jl. Lestari Indah No. 12, Cianjur'),
(5, 'Lembah Jamur Abadi',  '0813-8877-6655', 'Kp. Cigending No. 89, Bandung');

INSERT IGNORE INTO pedagang (id_pedagang, nama, no_hp, alamat) VALUES
(1, 'Hj. Siti Aminah',        '0812-3456-7890', 'Pasar Tradisional Caringin, Lapak No. 12, Bandung'),
(2, 'Bpk. Budiman Santoso',   '0856-7890-1234', 'Jl. Kedungdoro No. 88, Bandung'),
(3, 'Warung Makan Sederhana', '0821-9988-7766', 'Sentra Wisata Kuliner, Bandung'),
(4, 'Toko Sayur Segar',       '0877-6655-4433', 'Pasar Soponyono, Rungkut, Bandung'),
(5, 'Resto Jamur Crispy',     '0813-1122-3344', 'Jl. Manyar Kertoarjo No. 25, Bandung');

-- Barang masuk dengan harga beli
INSERT IGNORE INTO barang_masuk (id_barang_masuk, id_pemasok, tgl_setor, jamur_putih, jamur_coklat, harga_beli_putih, harga_beli_coklat) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 120.00, 45.00, 10000, 15000),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  98.00, 52.00,  9500, 14000),
(3, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 145.00, 30.00, 10000, 15000),
(4, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 110.00, 15.00, 10500, 15500),
(5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  85.00, 60.00,  9800, 14500),
(6, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 200.00, 80.00, 10000, 15000),
(7, 2, CURDATE(),                             75.00, 25.00,  9500, 14000);

-- Stock per-batch
INSERT IGNORE INTO stock (id_barang_masuk, jumlah_jamur_putih, jumlah_jamur_coklat, sisa_putih, sisa_coklat) VALUES
(1, 120.00, 45.00,  70.00, 25.00),
(2,  98.00, 52.00,  98.00, 52.00),
(3, 145.00, 30.00, 145.00, 30.00),
(4, 110.00, 15.00, 110.00, 15.00),
(5,  85.00, 60.00,  85.00, 60.00),
(6, 200.00, 80.00, 200.00, 80.00),
(7,  75.00, 25.00,  75.00, 25.00);

-- Barang keluar contoh
INSERT IGNORE INTO barang_keluar (id_stock, id_pedagang, tgl_kirim, jamur_putih, jamur_coklat, harga_putih, harga_coklat) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 30.00, 10.00, 15000, 20000),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 20.00, 10.00, 15500, 20500),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 40.00, 25.00, 15000, 20000),
(3, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 60.00, 10.00,  NULL,  NULL),
(4, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 45.00, 15.00, 16000, 21000);

-- ============================================================
--  TABEL BARANG KELUAR DETAIL (FIFO multi-batch tracking)
--  Menyimpan detail batch mana saja yang dipotong per transaksi
-- ============================================================
CREATE TABLE IF NOT EXISTS barang_keluar_detail (
    id_detail        INT(11)       AUTO_INCREMENT PRIMARY KEY,
    id_barang_keluar INT(11)       NOT NULL,
    id_stock         INT(11)       NOT NULL,
    potong_putih     DECIMAL(10,2) NOT NULL DEFAULT 0,
    potong_coklat    DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_barang_keluar) REFERENCES barang_keluar(id_barang_keluar) ON DELETE CASCADE,
    FOREIGN KEY (id_stock)         REFERENCES stock(id_stock) ON DELETE CASCADE
);
