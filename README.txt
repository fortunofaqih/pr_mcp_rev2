================================================================
PANDUAN IMPLEMENTASI - FITUR LOGIN GANDA & AKSES MANAGER
================================================================

FITUR YANG DITAMBAHKAN:
1. Akun manager bisa akses halaman admin_gudang sekaligus
2. Satu akun hanya bisa aktif di 1 komputer (anti login ganda)
3. Jika akun login di komputer lain → sesi lama otomatis dimatikan

----------------------------------------------------------------
LANGKAH 1: ALTER TABLE (wajib dilakukan pertama)
----------------------------------------------------------------
Jalankan file: 1_ALTER_TABLE.sql di phpMyAdmin

Query-nya:
    ALTER TABLE `users`
    ADD COLUMN `session_token` VARCHAR(64) DEFAULT NULL;

----------------------------------------------------------------
LANGKAH 2: Ganti/update file-file ini
----------------------------------------------------------------

a) auth/cek_login.php  → Ganti seluruh isinya
   (Tambahan: generate & simpan session_token saat login)

b) auth/check_session.php → FILE BARU, buat di folder auth/
   (Cek token di setiap halaman yang butuh login)

c) auth/logout.php → Ganti seluruh isinya
   (Tambahan: hapus session_token dari DB saat logout)

d) index.php → Ganti seluruh isinya
   (Tambahan: manager dapat akses menu admin_gudang)

e) login.php → TAMBAHKAN saja blok pesan sesi_ganda
   Lihat file: TAMBAHAN_login.php untuk kode yang perlu ditambah
   Letakkan setelah blok pesan 'nonaktif' yang sudah ada

----------------------------------------------------------------
LANGKAH 3: Update halaman lain yang butuh proteksi
----------------------------------------------------------------
Setiap halaman yang sebelumnya punya proteksi:

    // LAMA (hapus/ganti ini):
    if ($_SESSION['status'] != "login") {
        header("location:login.php?pesan=belum_login");
        exit;
    }

    // GANTI DENGAN (tambahkan setelah session_start & include koneksi):
    include 'auth/check_session.php';
    // (sesuaikan path relatif, misal dari subfolder: '../auth/check_session.php')

Contoh untuk halaman di folder modul/transaksi/:
    <?php
    session_start();
    include '../../config/koneksi.php';
    include '../../auth/check_session.php';
    ?>

----------------------------------------------------------------
CARA KERJA ANTI LOGIN GANDA:
----------------------------------------------------------------
1. User A login di Komputer 1 → token "abc123" disimpan di DB & session
2. User A login di Komputer 2 → token baru "xyz789" menimpa "abc123" di DB
3. Komputer 1 refresh halaman → check_session.php cek token:
   session punya "abc123" ≠ DB punya "xyz789" → PAKSA LOGOUT
4. Komputer 1 diarahkan ke login.php dengan pesan "sesi diakhiri"

----------------------------------------------------------------
CATATAN PENTING:
----------------------------------------------------------------
- Kolom session_token di DB akan NULL jika user tidak sedang login
- Logout normal juga membersihkan token dari DB
- Tidak perlu ubah struktur role di tabel users
- Manager tetap ber-role 'manager', hanya logika tampilan yang berubah

================================================================
-- ============================================================
-- LANGKAH 1: Tambah kolom akses_gudang di tabel users
-- ============================================================
ALTER TABLE `users`
ADD COLUMN `akses_gudang` ENUM('Y','N') DEFAULT 'N' AFTER `status_aktif`;

-- ============================================================
-- LANGKAH 2: Aktifkan akses gudang untuk Bu Helena
-- (dan siapapun yang perlu akses ganda di masa depan)
-- ============================================================
UPDATE `users` SET `akses_gudang` = 'Y' WHERE `username` = 'helena@mcp';

-- ============================================================
-- LANGKAH 3: Pastikan role Bu Helena adalah 'manager'
-- ============================================================
UPDATE `users` SET `role` = 'manager' WHERE `username` = 'helena@mcp';

-- ============================================================
-- Cek hasilnya:
-- ============================================================
SELECT id_user, username, nama_lengkap, role, akses_gudang, status_aktif
FROM users WHERE username = 'helena@mcp';