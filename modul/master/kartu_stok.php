<?php
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// 1. Ambil info detail barang
$query_b = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE id_barang='$id'");
$barang = mysqli_fetch_array($query_b);

// Jika ID tidak ditemukan
if(!$barang) { echo "Barang tidak ditemukan"; exit; }

// 2. Setting Filter Tanggal
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// 3. HITUNG SALDO AWAL (Mutasi SEBELUM tgl_mulai)
$q_saldo_lalu = mysqli_query($koneksi, "SELECT 
    SUM(CASE WHEN tipe_transaksi = 'MASUK' THEN qty ELSE 0 END) AS total_masuk,
    SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) AS total_keluar
    FROM tr_stok_log 
    WHERE id_barang = '$id' AND tgl_log < '$tgl_mulai 00:00:00'");
$d_lalu = mysqli_fetch_array($q_saldo_lalu);
$stok_awal = ($d_lalu['total_masuk'] ?? 0) - ($d_lalu['total_keluar'] ?? 0);

// 4. Ambil data histori
$query_log = mysqli_query($koneksi, "SELECT * FROM tr_stok_log 
    WHERE id_barang='$id' 
    AND tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59' 
    ORDER BY tgl_log ASC, id_log ASC");

$total_masuk = 0;
$total_keluar = 0;
$running_saldo = $stok_awal; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Stok - <?= $barang['nama_barang'] ?></title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #00008B; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card-stok { border-radius: 10px; border: none; shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); }
        .table-stok thead { background: var(--mcp-blue) !important; color: white !important; }
        .tabel-footer-custom { background-color: #f1f1f1 !important; font-weight: bold; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; padding: 0 !important; }
            .card-stok { border: 1px solid #000 !important; }
        }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="fw-bold mb-0 text-uppercase"><i class="fas fa-history me-2 text-primary"></i> KARTU STOK</h4>
        <div class="d-flex gap-2">
             <a href="../laporan/data_stock.php" class="btn btn-sm btn-danger"><i class="fas fa-arrow-left"></i> KEMBALI</a>
             <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print me-2"></i> CETAK</button>
        </div>
    </div>

    <div class="card mb-3 no-print border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="id" value="<?= $id; ?>">
                <div class="col-md-4">
                    <label class="small fw-bold">Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= $tgl_mulai ?>">
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= $tgl_selesai ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm btn-dark w-100"><i class="fas fa-filter me-1"></i> TAMPILKAN</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-stok bg-white shadow-sm">
        <div class="card-body p-4">
            <div class="row mb-3 border-bottom pb-3">
               <div class="col-6">
        <table class="table table-sm table-borderless small mb-0">
            <tr>
                <td width="120">NAMA BARANG</td>
                <td width="10">:</td>
                <td class="fw-bold"><?= strtoupper($barang['nama_barang']) ?></td>
            </tr>
            <tr>
                <td>LOKASI RAK</td>
                <td>:</td>
                <td><?= strtoupper($barang['lokasi_rak'] ?: '-') ?></td>
            </tr>
            <tr>
                <td>KATEGORI</td>
                <td>:</td>
                <td><?= strtoupper($barang['kategori']) ?></td>
            </tr>
        </table>
    </div>
    <div class="col-6">
        <table class="table table-sm table-borderless small mb-0">
            <tr>
                <td width="120">SATUAN</td>
                <td width="10">:</td>
                <td><?= strtoupper($barang['satuan']) ?></td>
            </tr>
            <tr>
                <td>PERIODE</td>
                <td>:</td>
                <td><?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></td>
            </tr>
            <tr>
                <td>TGL CETAK</td>
                <td>:</td>
                <td><?= date('d/m/Y H:i') ?></td>
            </tr>
        </table>
    </div>
            </div>

            <table class="table table-bordered table-striped align-middle table-stok mt-3" style="font-size: 0.8rem;">
                <thead class="text-center small">
                    <tr>
                        <th width="15%">TANGGAL</th>
                        <th width="45%">KETERANGAN / USER / NO. REF</th>
                        <th width="13%">MASUK (+)</th>
                        <th width="13%">KELUAR (-)</th>
                        <th width="14%">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light italic">
                        <td class="text-center text-muted">-</td>
                        <td class="fw-bold">SALDO AWAL PERIODE</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                       
                        <td class="text-center fw-bold"><?= number_format($stok_awal, 2, ',', '.') ?></td>
                    </tr>

                    <?php
                    while($row = mysqli_fetch_array($query_log)) {
                        $masuk = ($row['tipe_transaksi'] == 'MASUK') ? $row['qty'] : 0;
                        $keluar = ($row['tipe_transaksi'] == 'KELUAR') ? $row['qty'] : 0;
                        
                        $total_masuk += $masuk;
                        $total_keluar += $keluar;
                        $running_saldo = $running_saldo + $masuk - $keluar;
                    ?>
                    <tr>
                        <!--<td class="text-center"><?= date('d/m/y H:i', strtotime($row['tgl_log'])) ?></td>-ada jam nya-->
                        <td class="text-center"><?= date('d/m/y', strtotime($row['tgl_log'])) ?>
                        <td><?= $row['keterangan'] ?></td>
                       <td class="text-center text-success fw-bold"><?= ($masuk > 0) ? number_format($masuk, 2, ',', '.') : '-' ?></td>
                        <td class="text-center text-danger fw-bold"><?= ($keluar > 0) ? number_format($keluar, 2, ',', '.') : '-' ?></td>
                        <td class="text-center fw-bold bg-light"><?= number_format($running_saldo, 2, ',', '.') ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="tabel-footer-custom">
                    <tr>
                        <td colspan="2" class="text-end">TOTAL MUTASI & SALDO AKHIR :</td>
                       <td class="text-center text-success"><?= number_format($total_masuk, 2, ',', '.') ?></td>
                        <td class="text-center text-danger"><?= number_format($total_keluar, 2, ',', '.') ?></td>
                       <td class="text-center bg-warning fw-bold fs-6">
                            <?= number_format($running_saldo, 2, ',', '.') ?>
                        </td>
                                            </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

</body>
</html>