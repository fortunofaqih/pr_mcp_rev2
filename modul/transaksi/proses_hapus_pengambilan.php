<?php
session_start();
include '../../config/koneksi.php';

if (isset($_GET['id'])) {
    $id_bon = mysqli_real_escape_string($koneksi, $_GET['id']);
    $user   = $_SESSION['nama'];
    $now    = date('Y-m-d H:i:s');

    // 1. Ambil detail pengambilan sebelum dihapus
    $query_ambil = mysqli_query($koneksi, "SELECT * FROM bon_permintaan WHERE id_bon = '$id_bon'");
    $data = mysqli_fetch_array($query_ambil);

    if ($data) {
        $id_barang = $data['id_barang'];
        $qty_awal  = $data['qty_keluar'];
        $penerima  = $data['penerima'];
        $no_pb     = $data['no_permintaan'];

        mysqli_begin_transaction($koneksi);

        try {
            // 2. KEMBALIKAN STOK KE MASTER_BARANG
            $sql_update = "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_awal WHERE id_barang = '$id_barang'";
            if (!mysqli_query($koneksi, $sql_update)) {
                throw new Exception("Gagal mengembalikan stok ke master.");
            }

            // 3. CATAT PEMBATALAN DI tr_stok_log (Tipe MASUK karena barang balik ke gudang)
            $ket_log = "BATAL AMBIL: $no_pb - OLEH: $penerima (Dihapus oleh $user)";
            $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                        VALUES ('$id_barang', '$now', 'MASUK', '$qty_awal', '$ket_log', '$user')";
            
            if (!mysqli_query($koneksi, $sql_log)) {
                throw new Exception("Gagal mencatat pembatalan di kartu stok.");
            }

            // 4. HAPUS DATA DARI TABEL bon_permintaan
            $sql_delete = "DELETE FROM bon_permintaan WHERE id_bon = '$id_bon'";
            if (!mysqli_query($koneksi, $sql_delete)) {
                throw new Exception("Gagal menghapus data pengambilan.");
            }

            mysqli_commit($koneksi);
            header("location:pengambilan.php?pesan=hapus_sukses");

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='pengambilan.php';</script>";
        }
    } else {
        header("location:pengambilan.php");
    }
}
?>