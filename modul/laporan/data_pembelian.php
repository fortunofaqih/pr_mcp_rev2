<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
// Ambil nama user dari session (sesuaikan dengan struktur session Anda)
$nama_user = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'ADMIN';
// 1. TANGKAP FILTER
$abjad_filter = isset($_GET['abjad']) ? mysqli_real_escape_string($koneksi, strtoupper($_GET['abjad'])) : '';
$tgl_min      = isset($_GET['tgl_min']) ? $_GET['tgl_min'] : '';
$tgl_max      = isset($_GET['tgl_max']) ? $_GET['tgl_max'] : '';
$keyword      = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, strtoupper($_GET['keyword'])) : '';

// 2. QUERY STRING
$query_string = "tgl_min=$tgl_min&tgl_max=$tgl_max&keyword=$keyword";

// 3. BANGUN SQL FILTER
$filter_sql = " WHERE 1=1 ";
if ($abjad_filter != '' && $abjad_filter != 'ALL') {
    $filter_sql .= " AND p.nama_barang_beli LIKE '$abjad_filter%' ";
}
if ($tgl_min != '' && $tgl_max != '') {
    $filter_sql .= " AND p.tgl_beli_barang BETWEEN '$tgl_min' AND '$tgl_max' ";
}
if ($keyword != '') {
    $filter_sql .= " AND (p.nama_barang_beli LIKE '%$keyword%' OR p.supplier LIKE '%$keyword%' OR p.plat_nomor LIKE '%$keyword%') ";
}

// 4. AMBIL DATA
$sql = "SELECT p.*, m.merk as merk_master, m.satuan as satuan_master 
        FROM pembelian p 
        LEFT JOIN master_barang m ON p.nama_barang_beli = m.nama_barang 
        $filter_sql 
        ORDER BY p.tgl_beli_barang DESC, p.id_pembelian DESC LIMIT 500";

$query = mysqli_query($koneksi, $sql);
$data_tampil = [];
$harga_array = [];

while($row = mysqli_fetch_assoc($query)) {
    $data_tampil[] = $row;
    if($row['harga'] > 0) { $harga_array[] = (float)$row['harga']; }
}

$harga_termurah = (count($harga_array) > 0) ? min($harga_array) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Realisasi Pembelian - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.82rem; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .search-box, .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 20px; }
        .row-termurah { background-color: #f0fff4 !important; border-left: 5px solid #198754; }
        .badge-termurah { background-color: #198754; color: white; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; display: inline-block; margin-bottom: 3px; }
        .alphabet-nav { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 15px; }
        .btn-abjad { padding: 4px 9px; font-size: 10px; font-weight: bold; border: 1px solid #dee2e6; background: white; color: #333; text-decoration: none; border-radius: 4px; }
        .btn-abjad.active, .btn-abjad:hover { background: var(--mcp-blue); color: white; border-color: var(--mcp-blue); }
        .text-plat { background: #333; color: #fff; padding: 2px 5px; border-radius: 3px; font-weight: bold; font-family: monospace; font-size: 10px; }
        .btn-xs { padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        table thead th { vertical-align: middle; background-color: #212529 !important; color: white; }
        /* CSS untuk Cetak */
        /* CSS untuk Cetak */
@media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { 
        position: absolute; 
        left: 0; 
        top: 0; 
        width: 100%; 
        font-size: 9pt !important;
    }
    .navbar-mcp, .search-box, .btn-group, .btn-edit, .btn-info, .btn-danger, .btn-warning, 
    .alphabet-nav, .table-container .btn { display: none !important; }
    
    .print-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-family: Arial, sans-serif;
        font-size: 9pt !important;
        table-layout: fixed; /* Fixed layout untuk kontrol lebar */
    }
    .print-table th { 
        background-color: #333 !important; 
        color: white !important; 
        padding: 4px 3px !important;
        text-align: center; 
        font-weight: bold;
        font-size: 9pt !important;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
    .print-table td { 
        border: 1px solid #000; 
        padding: 3px 4px !important;
        vertical-align: top; 
        font-size: 9pt !important;
        word-wrap: break-word; /* Wrap text if needed */
    }
    .print-table .row-termurah { 
        background-color: #f0fff4 !important; 
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
    
    /* Lebar kolom spesifik */
    .print-table th:nth-child(1), .print-table td:nth-child(1) { width: 4%; } /* NO */
    .print-table th:nth-child(2), .print-table td:nth-child(2) { width: 8%; } /* TGL */
    .print-table th:nth-child(3), .print-table td:nth-child(3) { width: 15%; } /* SUPPLIER */
    .print-table th:nth-child(4), .print-table td:nth-child(4) { width: 28%; } /* NAMA BARANG */
    .print-table th:nth-child(5), .print-table td:nth-child(5) { width: 5%; } /* QTY */
    .print-table th:nth-child(6), .print-table td:nth-child(6) { width: 5%; } /* SAT */
    .print-table th:nth-child(7), .print-table td:nth-child(7) { width: 10%; } /* HARGA */
    .print-table th:nth-child(8), .print-table td:nth-child(8) { width: 12%; } /* TOTAL */
    .print-table th:nth-child(9), .print-table td:nth-child(9) { width: 13%; } /* KETERANGAN */
    
    .print-header {
        text-align: center;
        margin-bottom: 8px !important;
        padding: 5px !important;
        border-bottom: 2px solid #000;
    }
    .print-header h2 { margin: 0; font-size: 14pt !important; }
    .print-header h4 { margin: 3px 0; font-size: 11pt !important; }
    .print-header p { margin: 2px 0; font-size: 8pt !important; }
    
    .print-filter-info {
        font-size: 8pt !important;
        margin: 5px 0 !important;
        padding: 4px !important;
        background: #f5f5f5;
        border: 1px dashed #999;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
    
    .print-summary {
        page-break-inside: avoid;
        margin-top: 15px;
        padding: 8px;
        border-top: 2px solid #333;
        background-color: #fafafa;
        font-size: 9pt !important;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
    
    .no-urut {
        text-align: center;
        font-weight: bold;
    }
    
    /* Hindari page break di dalam baris */
    tr, td, th { 
        page-break-inside: avoid; 
    }
    
    /* Pastikan ringkasan di halaman terakhir */
    .print-summary {
        page-break-before: avoid;
        page-break-after: avoid;
    }
}

.print-area { display: none; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4 shadow-sm">
    <div class="container-fluid px-4 text-white">
        <span class="navbar-brand fw-bold text-white small"><i class="fas fa-book me-2"></i> BUKU REALISASI PEMBELIAN</span>
        <div>
            <!-- TOMBOL CETAK BARU -->
            <button onclick="cetakLaporan()" class="btn btn-light btn-sm px-3 fw-bold me-2">
                <i class="fas fa-print me-1"></i> CETAK LAPORAN
            </button>
            <a href="export_excel_pembelian.php?abjad=<?= $abjad_filter ?>&<?= $query_string ?>" class="btn btn-success btn-sm px-3 fw-bold me-2">
                <i class="fas fa-file-excel me-1"></i> EXPORT
            </a>
            <a href="../../index.php" class="btn btn-danger btn-sm px-3 fw-bold"><i class="fas fa-arrow-left me-1"></i> KEMBALI</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="search-box mb-4">
        <form action="" method="GET">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">RENTANG TANGGAL NOTA</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="tgl_min" class="form-control" value="<?= $tgl_min ?>">
                        <input type="date" name="tgl_max" class="form-control" value="<?= $tgl_max ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">PENCARIAN</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="keyword" class="form-control text-uppercase" placeholder="BARANG / TOKO / PLAT..." value="<?= $keyword ?>">
                        <button type="submit" class="btn btn-primary fw-bold px-4">CARI DATA</button>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="data_pembelian.php" class="btn btn-warning btn-sm w-100 fw-bold"><i class="fas fa-sync me-1"></i> RESET</a>
                </div>
            </div>
            
            <div class="alphabet-nav">
                <a href="?abjad=ALL&<?= $query_string ?>" class="btn-abjad <?= ($abjad_filter == '' || $abjad_filter == 'ALL') ? 'active' : '' ?>">ALL</a>
                <?php foreach (range('A', 'Z') as $char): ?>
                    <a href="?abjad=<?= $char ?>&<?= $query_string ?>" class="btn-abjad <?= ($abjad_filter == $char) ? 'active' : '' ?>"><?= $char ?></a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100">
                <thead class="table-dark">
                    <tr class="text-nowrap small text-center">
                        <th width="100">TGL NOTA</th>
                        <th width="150">SUPPLIER</th>
                        <th class="text-start">NAMA BARANG</th>
                        <th width="70">QTY</th>
                        <th width="120">HARGA</th>
                        <th width="130">TOTAL</th>
                        <th width="150">ALOKASI / UNIT</th>
                        <th class="text-start">KETERANGAN</th>
                        <th width="80">AKSI</th>
                    </tr>
                </thead>
                <tbody class="text-uppercase">
                    <?php 
                    if(!empty($data_tampil)):
                        foreach($data_tampil as $row): 
                            $total_bayar = $row['qty'] * $row['harga'];
                            $is_termurah = ($row['harga'] == $harga_termurah && $harga_termurah > 0);
                            $merk_tampil = !empty($row['merk_beli']) ? $row['merk_beli'] : ($row['merk_master'] ?? '-');
                            $satuan = !empty($row['satuan_master']) ? $row['satuan_master'] : 'PCS';
                            
                            // FORMAT TANGGAL AMAN
                            $tgl_display = ($row['tgl_beli_barang'] == '0000-00-00' || empty($row['tgl_beli_barang'])) 
                                           ? '<span class="text-muted small">-</span>' 
                                           : date('d/m/y', strtotime($row['tgl_beli_barang']));
                    ?>
                    <tr class="<?= $is_termurah ? 'row-termurah' : '' ?>">
                        <td class="text-center fw-bold text-muted"><?= $tgl_display ?></td>
                        <td class="small"><?= substr($row['supplier'], 0, 25) ?></td>
                        <td>
                            <div class="fw-bold"><?= $row['nama_barang_beli'] ?></div>
                            <small class="text-primary fw-bold" style="font-size: 10px;"><?= $merk_tampil ?></small>
                        </td>
                        <td class="text-center">
                            <div class="fw-bold"><?= (float)$row['qty'] ?></div>
                            <div class="text-muted fw-bold" style="font-size: 9px;"><?= strtoupper($satuan) ?></div>
                        </td>
                        <td class="text-end">
                            <?php if($is_termurah): ?>
                                <span class="badge-termurah"><i class="fas fa-check-circle"></i> TERMURAH</span><br>
                            <?php endif; ?>
                            <span class="fw-bold <?= $is_termurah ? 'text-success' : '' ?>">
                                <?= number_format($row['harga'], 0, ',', '.') ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-danger"><?= number_format($total_bayar, 0, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-secondary mb-1" style="font-size: 9px;"><?= $row['alokasi_stok'] ?></span><br>
                            <?php if(!empty($row['plat_nomor'])): ?>
                                <span class="text-plat"><?= $row['plat_nomor'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small fw-bold text-start"><?= $row['keterangan'] ?: '-' ?></td>
                        <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-xs btn-warning btn-edit" 
                                data-id="<?= $row['id_pembelian'] ?>"
                                data-tgl="<?= $row['tgl_beli_barang'] ?>"
                                data-barang="<?= $row['nama_barang_beli'] ?>"
                                data-merk="<?= $row['merk_beli'] ?>"
                                data-supplier="<?= $row['supplier'] ?>"
                                data-qty="<?= $row['qty'] ?>"
                                data-harga="<?= $row['harga'] ?>"
                                data-alokasi="<?= $row['alokasi_stok'] ?>"
                                data-driver="<?= $row['driver'] ?>"
                                data-plat="<?= $row['plat_nomor'] ?>"
                                data-ket="<?= $row['keterangan'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                    
                            <button class="btn btn-xs btn-info text-white" onclick="aksiRetur('<?= $row['id_pembelian'] ?>', '<?= addslashes($row['nama_barang_beli']) ?>', '<?= (float)$row['qty'] ?>', '<?= addslashes($row['supplier']) ?>')">
                                <i class="fas fa-undo"></i>
                            </button>
                    
                            <a href="hapus_pembelian_double.php?id=<?= $row['id_pembelian'] ?>" 
                               class="btn btn-xs btn-danger" 
                               onclick="return confirm('PERINGATAN!\n\nData ini akan dihapus permanen. Menghapus data pembelian akan merubah total pengeluaran di dashboard.\n\nYakin ingin menghapus?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                        
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="9" class="text-center p-4 text-muted">Data tidak ditemukan sesuai filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditBeli" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="proses_edit_pembelian.php" method="POST">
                <div class="modal-header bg-warning">
                    <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>EDIT DATA REALISASI</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_pembelian" id="edit_id">
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">TANGGAL NOTA</label>
                            <input type="date" name="tgl_beli_barang" id="edit_tgl" class="form-control form-control-sm border-primary" required>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">NAMA BARANG</label>
                            <input type="text" name="nama_barang" id="edit_barang" class="form-control form-control-sm fw-bold text-uppercase">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">MERK</label>
                            <input type="text" name="merk_beli" id="edit_merk" class="form-control form-control-sm text-uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">SUPPLIER / TOKO</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control form-control-sm text-uppercase">
                        </div>
                    </div>

                    <div class="row g-2 mb-3 p-2 bg-light border rounded">
                        <div class="col-md-4">
                            <label class="small fw-bold text-primary">QTY</label>
                            <input type="number" name="qty" id="edit_qty" class="form-control form-control-sm hitung" step="0.01">
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold text-primary">TOTAL BAYAR (GLOBAL)</label>
                            <input type="number" id="edit_total_global" class="form-control form-control-sm hitung">
                            <input type="hidden" name="harga" id="edit_harga">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">ALOKASI STOK</label>
                            <select name="alokasi_stok" id="edit_alokasi" class="form-select form-select-sm fw-bold">
                                <option value="LANGSUNG PAKAI">LANGSUNG PAKAI</option>
                                <option value="MASUK STOK">MASUK STOK</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">DRIVER</label>
                            <input type="text" name="driver" id="edit_driver" class="form-control form-control-sm text-uppercase">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="small fw-bold">PLAT NOMOR</label>
                            <input type="text" name="plat_nomor" id="edit_plat" class="form-control form-control-sm text-uppercase">
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">KETERANGAN / CATATAN</label>
                            <input type="text" name="keterangan" id="edit_ket" class="form-control form-control-sm text-uppercase">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">SIMPAN PERUBAHAN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- AREA CETAK (HIDDEN BY DEFAULT) -->
<div class="print-area" id="printArea">
    <div class="print-header">
        <h2>PT. MUTIARA CAHAYA PLASTINDO</h2>
        <h4>LAPORAN BUKU REALISASI PEMBELIAN</h4>
        <p>
            Periode: 
            <?php 
            if($tgl_min && $tgl_max) {
                echo date('d/m/Y', strtotime($tgl_min)) . ' s/d ' . date('d/m/Y', strtotime($tgl_max));
            } else {
                echo "Semua Data";
            }
            ?> | 
            Dicetak: <?= date('d/m/Y H:i:s') ?> | 
            Oleh: <?= strtoupper($_SESSION['nama_user'] ?? 'ADMIN') ?>
        </p>
    </div>
    
    <div class="print-filter-info">
        <strong>FILTER:</strong> 
        <?php 
        if($abjad_filter && $abjad_filter != 'ALL') echo "Abjad: $abjad_filter | ";
        if($keyword) echo "Keyword: $keyword | ";
        if(!$tgl_min && !$tgl_max && !$keyword && (!$abjad_filter || $abjad_filter=='ALL')) echo "Semua Data";
        ?>
    </div>
    
    <table class="print-table" id="printTable">
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th width="8%">TGL</th>
                <th width="15%">SUPPLIER</th>
                <th width="28%">NAMA BARANG</th>
                <th width="5%">QTY</th>
                <th width="5%">SAT</th>
                <th width="10%">HARGA</th>
                <th width="12%">TOTAL</th>
                <th width="13%">KETERANGAN</th> <!-- DIUBAH MENJADI KETERANGAN -->
            </tr>
        </thead>
        <tbody>
            <?php 
            $grand_total = 0;
            $total_qty = 0;
            $no = 1;
            
            if(!empty($data_tampil)):
                foreach($data_tampil as $row): 
                    $total_bayar = $row['qty'] * $row['harga'];
                    $grand_total += $total_bayar;
                    $total_qty += $row['qty'];
                    
                    $satuan = !empty($row['satuan_master']) ? $row['satuan_master'] : 'PCS';
                    $tgl_format = ($row['tgl_beli_barang'] != '0000-00-00') 
                                 ? date('d/m/y', strtotime($row['tgl_beli_barang'])) 
                                 : '-';
                    
                    // MENGGABUNGKAN INFORMASI UNTUK KOLOM KETERANGAN
                    $keterangan = '';
                    
                    // Tambah alokasi stok
                    $keterangan .= $row['alokasi_stok'];
                    
                    // Tambah plat nomor jika ada
                    if(!empty($row['plat_nomor'])) {
                        $keterangan .= ' - ' . $row['plat_nomor'];
                    }
                    
                    // Tambah driver jika ada
                    if(!empty($row['driver'])) {
                        $keterangan .= ' (' . $row['driver'] . ')';
                    }
                    
                    // Tambah keterangan tambahan dari field keterangan jika ada
                    if(!empty($row['keterangan']) && $row['keterangan'] != '-') {
                        $keterangan .= ' | ' . $row['keterangan'];
                    }
            ?>
            <tr <?= ($row['harga'] == $harga_termurah && $harga_termurah > 0) ? 'class="row-termurah"' : '' ?>>
                <td class="no-urut"><?= $no++ ?></td>
                <td style="text-align: center;"><?= $tgl_format ?></td>
                <td><?= htmlspecialchars($row['supplier']) ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['nama_barang_beli']) ?></strong>
                    <?php if(!empty($row['merk_beli'])): ?>
                        <br><span style="font-size: 7.5pt; color: #666;"><?= htmlspecialchars($row['merk_beli']) ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><?= number_format($row['qty'], 2) ?></td>
                <td style="text-align: center;"><?= $satuan ?></td>
                <td style="text-align: right;"><?= number_format($row['harga'], 0, ',', '.') ?></td>
                <td style="text-align: right; font-weight: bold;"><?= number_format($total_bayar, 0, ',', '.') ?></td>
                <td style="text-align: left; font-size: 8pt;"><?= htmlspecialchars($keterangan) ?></td> <!-- KETERANGAN -->
            </tr>
            <?php 
                endforeach; 
            else:
            ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 20px;">
                    <em>Tidak ada data untuk ditampilkan</em>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Ringkasan dengan Grand Total -->
    <div class="print-summary">
        <table width="100%" style="font-size: 9pt;">
            <tr>
                <td width="60%" valign="top">
                    <strong>RINGKASAN TRANSAKSI:</strong><br>
                    <table style="margin-top: 5px; font-size: 9pt;" width="100%">
                        <tr>
                            <td width="40%">Total Item (baris)</td>
                            <td width="10%">:</td>
                            <td width="50%"><strong><?= count($data_tampil) ?></strong> item</td>
                        </tr>
                        <tr>
                            <td>Total Quantity</td>
                            <td>:</td>
                            <td><strong><?= number_format($total_qty, 2) ?></strong></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold; font-size: 10pt;">TOTAL NILAI PEMBELIAN</td>
                            <td style="font-weight: bold;">:</td>
                            <td style="font-weight: bold; font-size: 11pt; color: #000;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>Rata-rata per item</td>
                            <td>:</td>
                            <td>Rp <?= count($data_tampil) > 0 ? number_format($grand_total/count($data_tampil), 0, ',', '.') : 0 ?></td>
                        </tr>
                    </table>
                </td>
                <td width="40%" style="text-align: right;" valign="bottom">
                    <table style="float: right;" cellpadding="5">
                        <tr>
                            <td style="text-align: center;">
                                <span style="border-top: 1px solid #000; padding-top: 3px; display: inline-block; width: 200px;">
                                    Mengetahui,
                                </span><br>
                                <span style="font-weight: bold; margin-top: 35px; display: block;">( ____________________ )</span>
                                <span style="font-size: 8pt;">MANAGER</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Informasi tambahan -->
    <div style="text-align: left; font-size: 6pt; margin-top: 10px; color: #666; border-top: 1px dotted #ccc; padding-top: 3px;">
        <table width="100%">
            <tr>
                <td width="50%">
                    *Harga termurah ditandai dengan background hijau
                </td>
                <td width="50%" style="text-align: right;">
                    *Dokumen ini dicetak otomatis dari MCP - Sistem Modul Pembelian | <?= date('Y') ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function aksiRetur(id, barang, qty, supplier) {
    let msg = `RETUR BARANG?\n--------------------------\nBarang : ${barang}\nQty    : ${qty}\nToko   : ${supplier}\n--------------------------\nMasukkan alasan retur:`;
    let alasan = prompt(msg);
    if (alasan) {
        window.location.href = `proses_retur_pembelian.php?id=${id}&alasan=${encodeURIComponent(alasan)}`;
    }
}

$(document).ready(function() {
    $(document).on('click', '.btn-edit', function() {
        var d = $(this).data();
        $('#edit_id').val(d.id);
        $('#edit_tgl').val(d.tgl); // Isi tanggal nota
        $('#edit_barang').val(d.barang);
        $('#edit_merk').val(d.merk);
        $('#edit_supplier').val(d.supplier);
        $('#edit_qty').val(d.qty);
        
        var total = parseFloat(d.qty) * parseFloat(d.harga);
        $('#edit_total_global').val(Math.round(total));
        $('#edit_harga').val(d.harga);
        
        $('#edit_alokasi').val(d.alokasi);
        $('#edit_driver').val(d.driver);
        $('#edit_plat').val(d.plat);
        $('#edit_ket').val(d.ket);
        
        $('#modalEditBeli').modal('show');
    });

    $('.hitung').on('input', function() {
        var qty = parseFloat($('#edit_qty').val()) || 0;
        var total = parseFloat($('#edit_total_global').val()) || 0;
        if(qty > 0) {
            var harga_satuan = Math.round(total / qty);
            $('#edit_harga').val(harga_satuan);
        }
    });
});
// Fungsi untuk mencetak laporan
// Fungsi untuk mencetak laporan - VERSI HEMAT KERTAS
function cetakLaporan() {
    // Siapkan area cetak
    var printContent = document.getElementById('printArea').innerHTML;
    
    // Hitung jumlah data untuk estimasi halaman
    var totalRows = <?= count($data_tampil) ?>;
    var rowsPerPage = 35; // Estimasi baris per halaman (font kecil)
    var estimatedPages = Math.ceil(totalRows / rowsPerPage);
    
    // Buat window baru untuk preview cetak
    var printWindow = window.open('', '_blank', 'width=1000,height=700');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laporan Pembelian - MCP</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 10px; 
                    background: #fff;
                }
                .print-header { 
                    text-align: center; 
                    margin-bottom: 5px; 
                    border-bottom: 1px solid #000; 
                }
                .print-header h2 { margin: 0; font-size: 14pt; }
                .print-header h4 { margin: 2px 0; font-size: 10pt; color: #333; }
                .print-header p { margin: 2px 0; font-size: 8pt; }
                
                .print-filter-info { 
                    font-size: 8pt; 
                    margin: 5px 0; 
                    padding: 3px; 
                    background: #f5f5f5; 
                    border: 1px dashed #999; 
                }
                
                .print-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    font-size: 8pt; 
                }
                .print-table th { 
                    background-color: #333 !important; 
                    color: white !important; 
                    padding: 3px 2px; 
                    text-align: center; 
                    font-weight: bold;
                    -webkit-print-color-adjust: exact; 
                    print-color-adjust: exact;
                }
                .print-table td { 
                    border: 1px solid #000; 
                    padding: 2px 3px; 
                    vertical-align: top; 
                }
                .print-table .row-termurah { 
                    background-color: #f0fff4 !important; 
                    -webkit-print-color-adjust: exact; 
                    print-color-adjust: exact;
                }
                
                .print-summary { 
                    margin-top: 10px; 
                    padding: 5px; 
                    border-top: 1px solid #333; 
                    font-size: 8pt; 
                    page-break-inside: avoid;
                }
                
                .no-urut {
                    text-align: center;
                    font-weight: bold;
                }
                
                /* Kontrol cetak */
                .print-control {
                    text-align: center; 
                    margin: 10px 0; 
                    padding: 10px;
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .print-control button {
                    background: #007bff; 
                    color: white; 
                    border: none;
                    padding: 8px 20px; 
                    margin: 0 5px;
                    font-size: 12px; 
                    border-radius: 4px;
                    cursor: pointer; 
                    font-weight: bold;
                }
                .print-control button:hover { opacity: 0.9; }
                .print-control .btn-secondary { background: #6c757d; }
                
                @media print {
                    body { margin: 0.2in; }
                    .print-control, .no-print { display: none; }
                }
                
                /* Utility */
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .fw-bold { font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="print-control no-print">
                <button onclick="window.print()"><i class="fas fa-print"></i> CETAK (${estimatedPages} halaman)</button>
                <button onclick="window.print();window.close()">CETAK & TUTUP</button>
                <button class="btn-secondary" onclick="window.close()">BATAL</button>
                <p style="margin-top:5px; font-size:9px; color:#666;">
                    <i class="fas fa-info-circle"></i> 
                    Estimasi ${estimatedPages} halaman dengan font 8pt. Gunakan kertas A4 - Landscape untuk hasil terbaik.
                </p>
            </div>
            ${printContent}
            <script>
                // Set page numbers
                document.querySelectorAll('.pageNumber').forEach(el => el.textContent = '1');
                document.querySelectorAll('.totalPages').forEach(el => el.textContent = '${estimatedPages}');
                
                // Optional: auto print after load
                // window.onload = function() { setTimeout(() => window.print(), 500); }
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Shortcut Ctrl+P
$(document).keydown(function(e) {
    if (e.ctrlKey && e.keyCode == 80) { 
        e.preventDefault();
        cetakLaporan();
    }
});
// Tambahkan shortcut keyboard Ctrl+P untuk cetak
$(document).keydown(function(e) {
    if (e.ctrlKey && e.keyCode == 80) { // Ctrl+P
        e.preventDefault();
        cetakLaporan();
    }
});
</script>
</body>
</html>