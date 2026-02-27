<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (isset($_GET['id'])) {
    $id     = mysqli_real_escape_string($koneksi, $_GET['id']);
    $alasan = mysqli_real_escape_string($koneksi, $_GET['alasan']);
    $user   = $_SESSION['nama'] ?? 'System';
    $now    = date('Y-m-d H:i:s');

    // 1. Ambil detail pembelian DAN ID DETAIL REQUEST (untuk mengembalikan status PR)
    $query_beli = mysqli_query($koneksi, "SELECT p.*, m.id_barang, m.stok_akhir 
                                          FROM pembelian p 
                                          LEFT JOIN master_barang m ON p.nama_barang_beli = m.nama_barang 
                                          WHERE p.id_pembelian = '$id'");
    $data = mysqli_fetch_array($query_beli);

    if ($data) {
        $id_barang     = $data['id_barang'];
        $nama_barang   = mysqli_real_escape_string($koneksi, $data['nama_barang_beli']);
        $qty_retur     = (float)$data['qty'];
        $alokasi       = $data['alokasi_stok'];
        $no_pr         = $data['no_request'];
        $id_req_detail = $data['id_request_detail']; // Pastikan kolom ini ada di tabel pembelian Anda
        $supplier      = mysqli_real_escape_string($koneksi, $data['supplier']);
        $stok_saat_ini = (float)$data['stok_akhir'];

        mysqli_begin_transaction($koneksi);

        try {
            // A. VALIDASI STOK (Jika Masuk Stok)
            if ($alokasi == 'MASUK STOK') {
                if (empty($id_barang)) {
                    throw new Exception("Barang tidak terdaftar di Master Barang. Hubungi Admin.");
                }
                if ($stok_saat_ini < $qty_retur) {
                    throw new Exception("Gagal Retur! Stok di gudang sisa $stok_saat_ini, tidak cukup untuk meretur $qty_retur.");
                }

                // B. Potong Stok di Master
                $sql_update_master = "UPDATE master_barang SET stok_akhir = stok_akhir - $qty_retur WHERE id_barang = '$id_barang'";
                mysqli_query($koneksi, $sql_update_master);

                // C. Catat Mutasi Keluar di Kartu Stok
                $ket_log = "RETUR KE TOKO ($supplier) - ALASAN: $alasan";
                $sql_stok_log = "INSERT INTO tr_stok_log 
                                (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                                VALUES 
                                ('$id_barang', '$now', 'KELUAR', '$qty_retur', '$ket_log', '$user')";
                mysqli_query($koneksi, $sql_stok_log);
            }

            // D. Catat ke Log Retur
            $sql_log_admin = "INSERT INTO log_retur 
                             (tgl_retur, no_request, nama_barang_retur, qty_retur, supplier, alokasi_sebelumnya, alasan_retur, eksekutor_retur) 
                             VALUES 
                             ('$now', '$no_pr', '$nama_barang', '$qty_retur', '$supplier', '$alokasi', '$alasan', '$user')";
            mysqli_query($koneksi, $sql_log_admin);

            // E. UPDATE STATUS PR DETAIL (Supaya bisa dibeli lagi)
            // Asumsi: status dikembalikan ke 'APPROVED' agar muncul lagi di form realisasi
            if(!empty($no_pr)){
                $sql_upd_pr = "UPDATE tr_request_detail SET status_item = 'APPROVED' 
                               WHERE id_request_detail = '$id_req_detail'";
                mysqli_query($koneksi, $sql_upd_pr);
            }

            // F. Hapus data pembelian
            $sql_delete = "DELETE FROM pembelian WHERE id_pembelian = '$id'";
            mysqli_query($koneksi, $sql_delete);

            mysqli_commit($koneksi);
            header("location:data_pembelian.php?pesan=retur_sukses");

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='data_pembelian.php';</script>";
        }
    } else {
        echo "<script>alert('Data pembelian tidak ditemukan!'); window.location='data_pembelian.php';</script>";
    }
}
?>