<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Proteksi akses langsung dan login
if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil data dan bersihkan (Sanitize)
    $id_pembelian = mysqli_real_escape_string($koneksi, $_POST['id_pembelian']);
    $tgl_beli     = mysqli_real_escape_string($koneksi, $_POST['tgl_beli_barang']); // TANGKAP TANGGAL BARU
    $nama_barang  = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_barang']));
    $merk_beli    = mysqli_real_escape_string($koneksi, strtoupper($_POST['merk_beli']));
    $supplier     = mysqli_real_escape_string($koneksi, strtoupper($_POST['supplier']));
    $qty          = (float)$_POST['qty'];
    $harga        = (int)$_POST['harga'];
    
    // Sesuaikan name input dengan form
    $alokasi      = mysqli_real_escape_string($koneksi, $_POST['alokasi_stok']);
    
    // Data pendukung
    $driver       = mysqli_real_escape_string($koneksi, strtoupper($_POST['driver']));
    $plat_nomor   = mysqli_real_escape_string($koneksi, strtoupper($_POST['plat_nomor']));
    $keterangan   = mysqli_real_escape_string($koneksi, strtoupper($_POST['keterangan']));

    // 2. Validasi Input Dasar
    if (empty($nama_barang) || empty($tgl_beli) || $qty <= 0 || $harga < 0) {
        header("location:data_pembelian.php?pesan=input_tidak_valid");
        exit;
    }

    // 3. Mulai Transaksi Database
    mysqli_begin_transaction($koneksi);

    try {
        // Query Update (Menambahkan tgl_beli_barang)
        $sql = "UPDATE pembelian SET 
                tgl_beli_barang  = '$tgl_beli', 
                nama_barang_beli = '$nama_barang',
                merk_beli        = '$merk_beli',
                supplier         = '$supplier',
                qty              = '$qty',
                harga            = '$harga',
                alokasi_stok     = '$alokasi',
                driver           = '$driver',
                plat_nomor       = '$plat_nomor',
                keterangan       = '$keterangan'
                WHERE id_pembelian = '$id_pembelian'";
        
        if (!mysqli_query($koneksi, $sql)) {
            throw new Exception("Gagal eksekusi query update: " . mysqli_error($koneksi));
        }

        // Jika semua OK, simpan permanen
        mysqli_commit($koneksi);
        header("location:data_pembelian.php?pesan=update_berhasil");

    } catch (Exception $e) {
        // Batalkan perubahan jika error
        mysqli_rollback($koneksi);
        header("location:data_pembelian.php?pesan=gagal_update&log=" . urlencode($e->getMessage()));
    }
} else {
    header("location:data_pembelian.php");
}
?>