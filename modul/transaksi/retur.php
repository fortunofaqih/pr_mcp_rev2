<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Proteksi Login
if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// Generate No. Retur Otomatis
$bulan_sekarang = date('Ym');
$prefix = "RT-" . $bulan_sekarang . "-";
$query_no = mysqli_query($koneksi, "SELECT no_retur FROM tr_retur WHERE no_retur LIKE '$prefix%' ORDER BY no_retur DESC LIMIT 1");
$data_no = mysqli_fetch_array($query_no);

if ($data_no) {
    // Ambil 4 angka terakhir
    $no_urut = (int) substr($data_no['no_retur'], -4);
    $no_urut++;
} else {
    // Jika data bulan ini masih kosong
    $no_urut = 1;
}
$no_retur = $prefix . sprintf("%04s", $no_urut);

// Proses Simpan Retur
if(isset($_POST['simpan_retur'])){
    $no_rt      = $_POST['no_retur'];
    $tgl        = $_POST['tgl_retur'];
    $jenis      = $_POST['jenis_retur']; 
    $id_barang  = $_POST['id_barang'];
    $qty        = $_POST['qty_retur'];
    $pengembali = mysqli_real_escape_string($koneksi, strtoupper($_POST['pengembali']));
    $alasan      = mysqli_real_escape_string($koneksi, strtoupper($_POST['alasan_retur']));
    $id_user    = $_SESSION['id_user'];

    mysqli_begin_transaction($koneksi);

    try {
        // 1. Simpan ke tabel tr_retur (ID tidak perlu dimasukkan karena sudah AUTO_INCREMENT)
        $query_retur = "INSERT INTO tr_retur (no_retur, tgl_retur, jenis_retur, id_barang, qty_retur, alasan_retur, pengembali, id_user) 
                        VALUES ('$no_rt', '$tgl', '$jenis', '$id_barang', '$qty', '$alasan', '$pengembali', '$id_user')";
        
        if (!mysqli_query($koneksi, $query_retur)) {
            throw new Exception("Gagal simpan data retur: " . mysqli_error($koneksi));
        }
        
        // 2. Tambahkan kembali stok fisik di Master Barang
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty WHERE id_barang='$id_barang'");

        // 3. Catat ke tr_stok_log agar sinkron dengan KARTU STOK
        $keterangan_log = "RETUR ($jenis): DARI $pengembali - $alasan";
        $tgl_full = $tgl . " " . date('H:i:s');
        $user_nama = $_SESSION['nama_lengkap']; // Pastikan session ini sesuai dengan nama di tabel users
        
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                    VALUES ('$id_barang', '$tgl_full', 'MASUK', '$qty', '$keterangan_log', '$user_nama')";
        
        if (!mysqli_query($koneksi, $sql_log)) {
            throw new Exception("Gagal simpan log stok: " . mysqli_error($koneksi));
        }
        
        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil! Stok telah dikembalikan ke gudang.'); window.location='retur.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Barang - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-orange: #FF8C00; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-orange { background-color: var(--mcp-orange) !important; color: white; }
        .table thead { background-color: #f1f3f5; color: #444; font-weight: 600; }
        input, select, textarea { text-transform: uppercase; }
        .btn-orange { background-color: var(--mcp-orange); color: white; border: none; }
        .btn-orange:hover { background-color: #e67e00; color: white; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="m-0 fw-bold text-dark text-uppercase">
                        <i class="fas fa-history me-2 text-warning"></i>Riwayat Transaksi Retur
                    </h5>
                </div>
                <div class="d-flex gap-2">
                    <a href="../../index.php" class="btn btn-sm btn-danger px-2"><i class="fas fa-rotate-left"></i>Kembali</a>
                    <button type="button" class="btn btn-sm btn-orange px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalRetur">
                        <i class="fas fa-exchange-alt me-1"></i> INPUT RETUR BARANG
                    </button>
                    
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" id="tabelRetur">
                    <thead>
                        <tr class="text-center">
                            <th>NO. RETUR</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>PENGEMBALI</th>
                            <th>PENERIMA (GUDANG)</th>
                            <th>ALASAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $histori = mysqli_query($koneksi, "SELECT r.*, m.nama_barang, m.satuan, u.nama_lengkap 
                                        FROM tr_retur r 
                                        JOIN master_barang m ON r.id_barang = m.id_barang 
                                        JOIN users u ON r.id_user = u.id_user 
                                        ORDER BY r.id_retur DESC");
                        while($h = mysqli_fetch_array($histori)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-primary"><?= $h['no_retur'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_retur'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center fw-bold text-success">+ <?= $h['qty_retur'] ?> <small><?= $h['satuan'] ?></small></td>
                            <td class="text-uppercase"><?= $h['pengembali'] ?></td>
                            <td class="text-uppercase text-muted"><?= $h['nama_lengkap'] ?></td>
                            <td class="small"><?= $h['alasan_retur'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-orange">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-edit me-2"></i>FORM PENGEMBALIAN BARANG KE STOK</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">NO. RETUR</label>
                            <input type="text" name="no_retur" class="form-control fw-bold text-danger bg-light" value="<?= $no_retur ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">TANGGAL KEMBALI</label>
                            <input type="date" name="tgl_retur" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">JENIS RETUR</label>
                            <select name="jenis_retur" class="form-select" required>
                                <option value="BATAL PAKAI">BATAL PAKAI</option>
                                <option value="SALAH AMBIL">SALAH AMBIL BARANG</option>
                                <option value="KELEBIHAN JUMLAH">KELEBIHAN JUMLAH</option>
                                <option value="BARANG RUSAK">BARANG RUSAK</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">CARI BARANG</label>
                            <select name="id_barang" id="pilihBarang" class="form-select select2" data-placeholder="CARI NAMA BARANG..." required>
                                <option value=""></option> 
                                <?php
                                // Query ini menghitung stok asli dari LOG, bukan sekadar baca kolom stok_akhir
                                $sql_akurat = "SELECT 
                                                b.id_barang, 
                                                b.nama_barang, 
                                                b.satuan,
                                                (SELECT SUM(CASE WHEN tipe_transaksi = 'MASUK' THEN qty ELSE 0 END) FROM tr_stok_log WHERE id_barang = b.id_barang) -
                                                (SELECT SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) FROM tr_stok_log WHERE id_barang = b.id_barang) as stok_real
                                            FROM master_barang b 
                                            ORDER BY b.nama_barang ASC";
                                
                                $brg = mysqli_query($koneksi, $sql_akurat);
                                while($b = mysqli_fetch_array($brg)){
                                    $stok_tampil = $b['stok_real'] ?? 0;
                                    echo "<option value='{$b['id_barang']}'>{$b['nama_barang']} (Stok: ".number_format($stok_tampil, 0)." {$b['satuan']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 mb-3" style="background-color: #fff4e5; border-radius: 8px; border-left: 5px solid #FF8C00;">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <label class="small fw-bold text-dark">JUMLAH (QTY) YANG DIKEMBALIKAN</label>
                                <p class="small text-muted mb-0">*Otomatis menambah stok akhir di gudang.</p>
                            </div>
                            <div class="col-md-5">
                                <input type="number" name="qty_retur" class="form-control form-control-lg fw-bold text-center" 
                                   placeholder="0.00" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">NAMA YANG MENGEMBALIKAN (PERSONAL/DEPT)</label>
                        <input type="text" name="pengembali" class="form-control" placeholder="CONTOH: BUDI (WORKSHOP)" required>
                    </div>

                    <div class="mb-0">
                        <label class="small fw-bold mb-1">ALASAN DETAIL PENGEMBALIAN</label>
                        <textarea name="alasan_retur" class="form-control" rows="3" placeholder="Tuliskan alasan lengkap barang dibalikan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-danger px-4" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" name="simpan_retur" class="btn btn-sm btn-orange px-5 fw-bold">
                        <i class="fas fa-check-circle me-1"></i> SIMPAN & UPDATE STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#tabelRetur').DataTable({
            "pageLength": 10,
            "order": [[0, "desc"]], // Urutkan berdasarkan No Retur terbaru
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
            }
        });
    });
    $(document).ready(function() {
    $('#pilihBarang').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#modalRetur') // PENTING: Agar search bisa diketik di dalam Modal
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>