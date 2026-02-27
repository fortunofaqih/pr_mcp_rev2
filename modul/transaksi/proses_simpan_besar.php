<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Tangkap Data Header
    $tgl_form      = $_POST['tgl_request'];
    $tgl_kode      = date('Ymd', strtotime($tgl_form));
    $user_login    = $_SESSION['nama'];
    $nama_pemesan  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']));
    
    // Sesuaikan dengan name="keterangan_umum" di form
    $keterangan_investasi = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keterangan_umum'])); 

    // LOCK TABLES
    mysqli_query($koneksi, "LOCK TABLES tr_request WRITE, tr_request_detail WRITE");

    // 2. Generate Nomor Request (PRB = Purchase Request Besar)
    $query_no = mysqli_query($koneksi, "SELECT MAX(no_request) as max_code FROM tr_request WHERE no_request LIKE 'PRB-$tgl_kode%'");
    $data_no  = mysqli_fetch_array($query_no);
    $last_no  = $data_no['max_code'] ?? '';
    $sort_no  = (int) substr($last_no, -3);
    $new_no   = "PRB-" . $tgl_kode . "-" . str_pad(($sort_no + 1), 3, "0", STR_PAD_LEFT);

    // 3. Simpan Header
    $query_header = "INSERT INTO tr_request (
                        no_request, tgl_request, nama_pemesan, keterangan, 
                        status_request, kategori_pr, status_approval, created_by
                    ) VALUES (
                        '$new_no', '$tgl_form', '$nama_pemesan', '$keterangan_investasi', 
                        'PENDING', 'BESAR', 'PENDING', '$user_login'
                    )";

    if (mysqli_query($koneksi, $query_header)) {
        $id_header = mysqli_insert_id($koneksi);
        $nama_barang_array = $_POST['nama_barang'];

        // 4. Looping Detail
        foreach ($nama_barang_array as $key => $val) {
            if(empty(trim($val))) continue; 
            
            $input_barang = strtoupper(mysqli_real_escape_string($koneksi, $val));
            $hrg          = (float)($_POST['harga'][$key] ?? 0);
            $qty          = (float)($_POST['jumlah'][$key] ?? 0);
            $subtotal     = $qty * $hrg;
            
            $kwalifikasi  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['kwalifikasi'][$key] ?? ''));
            $satuan       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['satuan'][$key] ?? ''));
            $id_mobil     = (int)($_POST['id_mobil'][$key] ?? 0);
            $kategori_brg = strtoupper(mysqli_real_escape_string($koneksi, $_POST['kategori_request'][$key] ?? ''));
            $tipe_req     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['tipe_request'][$key] ?? 'LANGSUNG'));
            
            // Tangkap keterangan per item (dari textarea di setiap baris)
            $ket_item     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keterangan_item'][$key] ?? ''));

            $query_detail = "INSERT INTO tr_request_detail (
                                id_request, nama_barang_manual, id_barang, id_mobil, 
                                jumlah, satuan, harga_satuan_estimasi, subtotal_estimasi, 
                                kategori_barang, tipe_request, kwalifikasi, keterangan
                            ) VALUES (
                                '$id_header', '$input_barang', 0, '$id_mobil', 
                                '$qty', '$satuan', '$hrg', '$subtotal', 
                                '$kategori_brg', '$tipe_req', '$kwalifikasi', '$ket_item'
                            )";
            mysqli_query($koneksi, $query_detail);
        }

        mysqli_query($koneksi, "UNLOCK TABLES");
        // Kita kirim pesan 'berhasil' DAN nomor request 'no'
        header("location:pr.php?pesan=berhasil&no=" . $new_no);
        } else {
        mysqli_query($koneksi, "UNLOCK TABLES");
        echo "Error: " . mysqli_error($koneksi);
    }
}
?>