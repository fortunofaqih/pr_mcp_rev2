<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_bon     = (int)$_POST['id_bon'];
    $penerima   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['penerima']));
    $qty_baru   = (float)$_POST['qty_baru'];
    $qty_lama   = (float)$_POST['qty_lama'];
    $keperluan  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keperluan']));
    $plat_nomor = strtoupper(mysqli_real_escape_string($koneksi, $_POST['plat_nomor']));
    $tgl_keluar = $_POST['tgl_keluar']; // TAMBAHAN
    $now        = date('Y-m-d H:i:s');

    // 1. Cari info barang dari ID Bon
    $data_awal = mysqli_fetch_array(mysqli_query($koneksi, "SELECT id_barang, no_permintaan, tgl_keluar FROM bon_permintaan WHERE id_bon = '$id_bon'"));
    $id_barang = $data_awal['id_barang'];
    $no_pb     = $data_awal['no_permintaan'];
    $tgl_asli  = $data_awal['tgl_keluar'];

    mysqli_begin_transaction($koneksi);

    try {
        // 2. Kembalikan stok lama ke Master (cadangan)
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_lama WHERE id_barang = '$id_barang'");

        // 3. Cek stok dari tr_stok_log (sumber kebenaran)
        $res_cek = mysqli_fetch_array(mysqli_query($koneksi, "SELECT 
            (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang='$id_barang' AND tipe_transaksi='MASUK') -
            (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang='$id_barang' AND tipe_transaksi='KELUAR') 
            AS stok_log"));

        if($qty_baru > $res_cek['stok_log']){
            // Rollback master_barang dulu sebelum lempar exception
            mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty_lama WHERE id_barang = '$id_barang'");
            throw new Exception("Gagal! Stok tidak cukup. Sisa stok tersedia: " . $res_cek['stok_log']);
        }

        // 4. Potong stok baru di Master (cadangan)
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty_baru WHERE id_barang = '$id_barang'");

        // 5. Update bon_permintaan (+ tgl_keluar)
        mysqli_query($koneksi, "UPDATE bon_permintaan SET 
                                qty_keluar  = '$qty_baru', 
                                penerima    = '$penerima', 
                                keperluan   = '$keperluan',
                                plat_nomor  = '$plat_nomor',
                                tgl_keluar  = '$tgl_keluar'
                                WHERE id_bon = '$id_bon'");

        // 6. Cari id_log dulu berdasarkan id_barang + KELUAR + tanggal asli
            $tgl_asli_date = date('Y-m-d', strtotime($tgl_asli));

            $cari_log = mysqli_fetch_array(mysqli_query($koneksi, 
                "SELECT id_log FROM tr_stok_log 
                WHERE id_barang = '$id_barang' 
                AND tipe_transaksi = 'KELUAR'
                AND DATE(tgl_log) = '$tgl_asli_date'
                ORDER BY id_log DESC LIMIT 1"
            ));

            $id_log_target = $cari_log['id_log'] ?? null;

            if($id_log_target) {
                $info_plat    = ($plat_nomor != "") ? " [UNIT: $plat_nomor]" : "";
                $ket_baru     = "EDIT PENGAMBILAN: $penerima ($keperluan)$info_plat";
                $tgl_log_baru = $tgl_keluar . ' ' . date('H:i:s');

                mysqli_query($koneksi, "UPDATE tr_stok_log SET 
                            qty        = '$qty_baru', 
                            keterangan = '$ket_baru',
                            tgl_log    = '$tgl_log_baru'
                            WHERE id_log = '$id_log_target'");
            }

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil Koreksi Pengambilan!'); window.location='pengambilan.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('".$e->getMessage()."'); window.location='pengambilan.php';</script>";
    }
}