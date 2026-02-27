<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';


$id_req = mysqli_real_escape_string($koneksi, $_GET['id']);
$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id_req'");
$header = mysqli_fetch_array($query_header);

// Redirect jika data tidak ditemukan atau sudah di-approve
if (!$header || $header['status_approval'] != 'PENDING') {
    header("location:approval_pimpinan.php");
    exit;
}

// LOGIKA SIMPAN APPROVAL
if (isset($_POST['aksi'])) {
    $status_final = $_POST['status_baru']; 
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan_pimpinan']);
    $nama_pimpinan = $_SESSION['nama'] ?? 'PIMPINAN';
    
    // Ambil data item yang dicentang
    $item_disetujui = $_POST['item_pilihan'] ?? []; 

    mysqli_begin_transaction($koneksi);

    try {
        if ($status_final == 'APPROVED') {
            // 1. Set semua item di detail jadi REJECTED dulu agar yang tidak dicentang otomatis tertolak
            mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item = 'REJECTED' WHERE id_request = '$id_req'");
            
            // 2. Set item yang terpilih jadi APPROVED
            if (!empty($item_disetujui)) {
                foreach ($item_disetujui as $id_detail) {
                    $id_det_safe = mysqli_real_escape_string($koneksi, $id_detail);
                    mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item = 'APPROVED' WHERE id_detail = '$id_det_safe'");
                }
            } else {
                // Jika mencet tombol Approve tapi tidak ada barang dicentang, maka status final jadi REJECTED
                $status_final = 'REJECTED';
            }
        } else {
            // Jika tombol REJECT yang ditekan, set semua item jadi REJECTED
            mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item = 'REJECTED' WHERE id_request = '$id_req'");
        }

        // 3. Update Status di Header
        $update_header = "UPDATE tr_request SET 
                        status_approval = '$status_final', 
                        catatan_pimpinan = '$catatan',
                        tgl_approval = NOW(),
                        approve_by = '$nama_pimpinan' 
                        WHERE id_request = '$id_req'";
        
        mysqli_query($koneksi, $update_header);

        mysqli_commit($koneksi);
        header("location:approval_pimpinan.php?pesan=berhasil");
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal memproses approval: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Pengajuan - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 15px; overflow: hidden; }
        .table thead { background-color: #4e73df; color: white; }
        .form-check-input:checked { background-color: #198754; border-color: #198754; }
        .btn-approve { background: linear-gradient(45deg, #198754, #2ecc71); color: white; border: none; }
        .btn-approve:hover { background: linear-gradient(45deg, #146c43, #27ae60); color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <a href="approval_pimpinan.php" class="btn btn-white shadow-sm fw-bold text-danger">
                    <i class="fas fa-arrow-left me-2"></i> Kembali
                </a>
                <span class="badge bg-primary px-3 py-2">STATUS: <?= $header['status_approval'] ?></span>
            </div>
            
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-search me-2"></i>Review Pengajuan: <?= $header['no_request'] ?></h5>
                </div>
                
                <div class="card-body p-4">
                    <div class="row mb-4 bg-light p-3 rounded-3 mx-1">
                        <div class="col-md-6 border-end">
                            <label class="text-muted small d-block">PEMESAN</label>
                            <p class="fw-bold fs-5 mb-0"><?= $header['nama_pemesan'] ?></p>
                            <span class="text-muted small"><?= date('d F Y', strtotime($header['tgl_request'])) ?></span>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <label class="text-muted small d-block">KEPERLUAN / KETERANGAN</label>
                            <p class="fw-bold mb-0 text-dark"><?= !empty($header['keterangan']) ? $header['keterangan'] : '-' ?></p>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 text-secondary"><i class="fas fa-list me-2"></i>Pilih Item yang Disetujui</h6>
                    
                    <form action="" method="POST" onsubmit="return confirm('Proses keputusan Anda sekarang?')">
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle border">
                                <thead>
                                    <tr class="text-center">
                                        <th width="80">PILIH</th>
                                        <th class="text-start">NAMA BARANG</th>
                                        <th width="120">QTY</th>
                                        <th class="text-end" width="180">SUBTOTAL EST.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_est = 0;
                                    $q_det = mysqli_query($koneksi, "SELECT d.*, m.nama_barang as nama_master 
                                             FROM tr_request_detail d 
                                             LEFT JOIN master_barang m ON d.id_barang = m.id_barang 
                                             WHERE d.id_request = '$id_req'");
                                    
                                    while($det = mysqli_fetch_array($q_det)) {
                                        $nama = !empty($det['nama_barang_manual']) ? $det['nama_barang_manual'] : $det['nama_master'];
                                        $sub = $det['jumlah'] * $det['harga_satuan_estimasi'];
                                        $total_est += $sub;
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="item_pilihan[]" value="<?= $det['id_detail'] ?>" 
                                                   class="form-check-input shadow-sm p-2" checked style="cursor:pointer; width:25px; height:25px;">
                                        </td>
                                        <td>
                                            <span class="fw-bold d-block"><?= strtoupper($nama) ?></span>
                                            <small class="text-muted">Untuk: <?= $det['kategori_barang'] ?? '-' ?></small>
                                        </td>
                                        <td class="text-center fw-bold"><?= $det['jumlah'] ?> <?= $det['satuan'] ?></td>
                                        <td class="text-end fw-bold text-primary">Rp <?= number_format($sub,0,',','.') ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-end">TOTAL ESTIMASI PENGAJUAN:</td>
                                        <td class="text-end text-danger fs-5">Rp <?= number_format($total_est,0,',','.') ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold mb-2"><i class="fas fa-comment-dots me-2"></i>CATATAN PIMPINAN (OPSIONAL)</label>
                            <textarea name="catatan_pimpinan" class="form-control" rows="3" ></textarea>
                        </div>

                        <input type="hidden" name="status_baru" id="status_baru" value="">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <button type="submit" name="aksi" class="btn btn-approve btn-lg w-100 fw-bold py-3 shadow-sm" onclick="document.getElementById('status_baru').value='APPROVED'">
                                    <i class="fas fa-check-circle me-2"></i> APPROVE ITEM TERPILIH
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" name="aksi" class="btn btn-outline-danger btn-lg w-100 fw-bold py-3" onclick="document.getElementById('status_baru').value='REJECTED'">
                                    <i class="fas fa-times-circle me-2"></i> TOLAK SELURUHNYA
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <p class="text-center text-muted mt-4 small">PT. Mutiaracahaya Plastindo - Purchase System &copy; 2024</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>