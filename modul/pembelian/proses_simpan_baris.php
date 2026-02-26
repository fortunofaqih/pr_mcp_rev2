<?php
/**
 * proses_simpan_baris.php v3 — sistem staging/verifikasi
 * Simpan ke pembelian_staging → status item jadi MENUNGGU VERIFIKASI
 * Admin gudang approve → baru masuk tabel pembelian + update stok
 */
session_start();
include '../../config/koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Metode tidak valid.']); exit;
}
if ($_SESSION['status'] != 'login') {
    echo json_encode(['status'=>'error','message'=>'Session habis, silakan login ulang.']); exit;
}

$id_detail       = (int)($_POST['id_detail']    ?? 0);
$id_request      = (int)($_POST['id_request']   ?? 0);
$id_barang       = (int)($_POST['id_barang']    ?? 0);
$id_mobil        = (int)($_POST['id_mobil']     ?? 0);
$nama_pemesan    = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']    ?? '')));
$nama_pembeli    = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['nama_pembeli']    ?? '')));
$supplier        = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['supplier']        ?? '')));
$nama_barang     = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['nama_barang']     ?? '')));
$alokasi         = mysqli_real_escape_string($koneksi, $_POST['alokasi']         ?? 'LANGSUNG PAKAI');
$kategori_pr     = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['kategori_pr']     ?? 'KECIL')));
$kategori_barang = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['kategori_barang'] ?? '-')));
$keterangan      = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['keterangan']      ?? '')));
$tgl_nota_raw    = $_POST['tgl_nota'] ?? '';
$qty             = (float)($_POST['qty']   ?? 0);
$harga           = (float)($_POST['harga'] ?? 0);
$id_user_beli    = (int)($_SESSION['id_user'] ?? 0);

// Validasi
if ($id_detail <= 0)    { echo json_encode(['status'=>'error','message'=>'ID detail tidak valid.']); exit; }
if ($qty <= 0)          { echo json_encode(['status'=>'error','message'=>'Qty harus lebih dari 0.']); exit; }
if ($harga <= 0)        { echo json_encode(['status'=>'error','message'=>'Harga harus diisi.']); exit; }
if (empty($supplier))   { echo json_encode(['status'=>'error','message'=>'Nama toko/supplier wajib diisi.']); exit; }
if (empty($keterangan)) { echo json_encode(['status'=>'error','message'=>'Keterangan wajib diisi.']); exit; }

// Lookup plat_nomor
$plat_nomor = '-';
if ($id_mobil > 0) {
    $q_plat = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE id_mobil = $id_mobil LIMIT 1");
    $r_plat = mysqli_fetch_assoc($q_plat);
    if ($r_plat) $plat_nomor = mysqli_real_escape_string($koneksi, strtoupper($r_plat['plat_nomor']));
}

// Konversi tanggal dd-mm-yyyy → yyyy-mm-dd
if (!empty($tgl_nota_raw)) {
    $parts = explode('-', $tgl_nota_raw);
    $tgl_beli_barang = (count($parts) === 3 && strlen($parts[2]) === 4)
        ? $parts[2].'-'.$parts[1].'-'.$parts[0]
        : date('Y-m-d', strtotime($tgl_nota_raw));
} else {
    $tgl_beli_barang = date('Y-m-d');
}

// Cek status item — tidak boleh duplikat staging
$cek  = mysqli_query($koneksi, "SELECT status_item FROM tr_request_detail WHERE id_detail = $id_detail LIMIT 1");
$item = mysqli_fetch_assoc($cek);
if (!$item) { echo json_encode(['status'=>'error','message'=>'Item tidak ditemukan.']); exit; }
if ($item['status_item'] === 'TERBELI') {
    echo json_encode(['status'=>'error','message'=>'Item ini sudah terbeli.']); exit;
}
if ($item['status_item'] === 'MENUNGGU VERIFIKASI') {
    echo json_encode(['status'=>'error','message'=>'Item ini sedang menunggu verifikasi admin gudang.']); exit;
}

// Ambil no_request
$no_request = '';
if ($id_request > 0) {
    $q_no = mysqli_query($koneksi, "SELECT no_request FROM tr_request WHERE id_request = $id_request LIMIT 1");
    $r_no = mysqli_fetch_assoc($q_no);
    $no_request = mysqli_real_escape_string($koneksi, $r_no['no_request'] ?? '');
}

$id_req_val = ($id_request > 0) ? $id_request : 'NULL';
$id_brg_val = ($id_barang  > 0) ? $id_barang  : 'NULL';
$id_mob_val = ($id_mobil   > 0) ? $id_mobil   : 'NULL';

mysqli_begin_transaction($koneksi);
try {
    // 1. Insert ke staging
    $q_staging = "INSERT INTO pembelian_staging
                  (id_request, id_request_detail, no_request, tgl_beli, tgl_beli_barang,
                   supplier, nama_barang_beli, id_barang, id_mobil, qty, harga,
                   kategori_beli, alokasi_stok, nama_pemesan, driver, plat_nomor,
                   keterangan, id_user_beli, status_staging)
                  VALUES
                  ($id_req_val, $id_detail, '$no_request', CURDATE(), '$tgl_beli_barang',
                   '$supplier', '$nama_barang', $id_brg_val, $id_mob_val, $qty, $harga,
                   '$kategori_pr', '$alokasi', '$nama_pemesan', '$nama_pembeli', '$plat_nomor',
                   '$keterangan', $id_user_beli, 'MENUNGGU')";

    if (!mysqli_query($koneksi, $q_staging)) {
        throw new Exception('Gagal simpan staging: '.mysqli_error($koneksi));
    }

    // 2. Update status item → MENUNGGU VERIFIKASI
    if (!mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item='MENUNGGU VERIFIKASI' WHERE id_detail = $id_detail")) {
        throw new Exception('Gagal update status item: '.mysqli_error($koneksi));
    }

    // 3. Update PR → PROSES jika masih PENDING
    if ($id_request > 0) {
        mysqli_query($koneksi, "UPDATE tr_request SET status_request='PROSES' WHERE id_request=$id_request AND status_request='PENDING'");
    }

    mysqli_commit($koneksi);

    $subtotal = $qty * $harga;
    echo json_encode([
        'status'          => 'ok',
        'message'         => 'Tersimpan! Menunggu verifikasi admin gudang.',
        'id_detail'       => $id_detail,
        'subtotal'        => $subtotal,
        'subtotal_fmt'    => number_format($subtotal, 0, ',', '.'),
        'plat_nomor'      => $plat_nomor,
        'kategori_beli'   => $kategori_pr,
        'kategori_barang' => $kategori_barang,
        'pr_selesai'      => false,
    ]);

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
exit;