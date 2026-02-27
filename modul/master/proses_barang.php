<?php
session_start(); 
include '../../config/koneksi.php';
include '../../auth/check_session.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_barang'] ?? ''));
    $merk         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk'] ?? ''));
    $kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori'] ?? 'UMUM');
    $satuan       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['satuan'] ?? 'PCS'));
    $lokasi_rak   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['lokasi_rak'] ?? ''));
    $status       = $_POST['status_aktif'] ?? 'AKTIF';
    $stok_input   = (float)($_POST['stok_awal'] ?? 0); 
    
    // AMBIL HARGA BARANG (TAMBAHAN BARU)
    // Jika kosong, otomatis jadi 0
    $harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);

    $user_login   = $_SESSION['nama'] ?? 'SYSTEM'; 

    // Cek duplikasi nama
    $cek_dulu = mysqli_query($koneksi, "SELECT id_barang FROM master_barang WHERE nama_barang = '$nama'");
    if(mysqli_num_rows($cek_dulu) > 0){
        header("location:barang.php?pesan=ada");
        exit;
    }

    // HITUNG ID MANUAL UNTUK MASTER
    $q_max = mysqli_query($koneksi, "SELECT MAX(id_barang) as max_id FROM master_barang");
    $r_max = mysqli_fetch_assoc($q_max);
    $id_baru = ($r_max['max_id'] ?? 0) + 1;

    // 1. SIMPAN MASTER BARANG (Menambahkan kolom harga_barang_stok)
    $sql = "INSERT INTO master_barang (id_barang, nama_barang, merk, kategori, satuan, stok_minimal, stok_akhir, harga_barang_stok, lokasi_rak, status_aktif, created_by) 
            VALUES ('$id_baru', '$nama', '$merk', '$kategori', '$satuan', 3, '$stok_input', '$harga_barang', '$lokasi_rak', '$status', '$user_login')";

    if(mysqli_query($koneksi, $sql)){
        
        // HITUNG ID MANUAL UNTUK LOG STOK
        $q_log = mysqli_query($koneksi, "SELECT MAX(id_log) as max_log FROM tr_stok_log");
        $r_log = mysqli_fetch_assoc($q_log);
        $id_log_baru = ($r_log['max_log'] ?? 0) + 1;

        // 2. SIMPAN LOG STOK
        $log_stok = "INSERT INTO tr_stok_log (id_log, id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                     VALUES ('$id_log_baru', '$id_baru', NOW(), 'MASUK', '$stok_input', 'SALDO AWAL', '$user_login')";
        
        mysqli_query($koneksi, $log_stok);

        header("location:barang.php?pesan=berhasil");
        exit;
    } else {
        die("Gagal simpan master: " . mysqli_error($koneksi));
    }
}
?>