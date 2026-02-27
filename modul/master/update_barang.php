<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
// Tambahkan ini setelah session_start()
if (!isset($_POST['id_barang']) || !is_numeric($_POST['id_barang'])) {
    header("location:data_barang.php?pesan=error");
    exit;
}

// Menangkap data dari form

$id           = (int)$_POST['id_barang'];
$nama         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_barang']));
$merk         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk']));
$lokasi       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['lokasi_rak']));
$satuan       = $_POST['satuan']; 
$stok_akhir   = $_POST['stok_akhir'];
$status_aktif = $_POST['status_aktif'];
$kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori']);
$user_login   = $_SESSION['nama']; 

// AMBIL HARGA BARANG (TAMBAHAN BARU)
// Jika kosong dari form, set ke 0
$harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);

// 1. Ambil stok lama untuk pengecekan log
$query_lama = mysqli_query($koneksi, "SELECT stok_akhir FROM master_barang WHERE id_barang='$id'");
$lama = mysqli_fetch_array($query_lama);
$stok_lama = $lama['stok_akhir'];

// 2. Update Master Barang (Menambahkan harga_barang_stok)
$sql = "UPDATE master_barang SET 
        nama_barang  = '$nama', 
        merk         = '$merk',
        kategori     = '$kategori', 
        lokasi_rak   = '$lokasi', 
        satuan       = '$satuan', 
        stok_akhir   = '$stok_akhir', 
        harga_barang_stok = '$harga_barang',
        status_aktif = '$status_aktif'
        WHERE id_barang = '$id'";

if(mysqli_query($koneksi, $sql)){
    
    // 3. Catat ke log jika ada perubahan angka stok akhir
    if($stok_akhir != $stok_lama) {
        $selisih = $stok_akhir - $stok_lama;
        $tipe = ($selisih > 0) ? 'MASUK' : 'KELUAR';
        $qty_log = abs($selisih);
        
        $keterangan = "ADJUSTMENT STOK BY $user_login (STOK LAMA: $stok_lama)";
        
        // Sesuaikan dengan kolom tabel tr_stok_log
       $log = "INSERT INTO tr_stok_log (id_barang, tipe_transaksi, qty, keterangan, user_input) 
        VALUES ('$id', '$tipe', '$qty_log', '$keterangan', '$user_login')";
        mysqli_query($koneksi, $log);
        
    }

    header("location:data_barang.php?pesan=berhasil_update");
} else {
    echo "Error Database: " . mysqli_error($koneksi);
}
?>