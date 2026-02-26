<?php
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$tgl_awal  = isset($_GET['tgl_awal'])  ? $_GET['tgl_awal']  : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$search    = isset($_GET['search'])    ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

$nama_bulan = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

// QUERY SEDERHANA DULU - TANPA SUBQUERY TGL TERAKHIR YANG RUMIT
$query_sql = "SELECT 
                id_transaksi, driver_tetap, plat_nomor, jenis_kendaraan, 
                nama_item, tgl_beli, harga_satuan, total_per_item, 
                kategori, nama_barang_asli
              FROM (
                SELECT 
                    rd.id_detail as id_transaksi,
                    m.driver_tetap, 
                    m.plat_nomor, 
                    m.jenis_kendaraan,
                    CONCAT(rd.nama_barang_manual, ' (', rd.jumlah, ' ', rd.satuan, ')') as nama_item,
                    IFNULL(p.tgl_final, r.tgl_request) as tgl_beli,
                    CASE 
                        WHEN IFNULL(p.harga_final, 0) > 0 THEN p.harga_final 
                        ELSE IFNULL(mb_ref.harga_barang_stok, 0) 
                    END as harga_satuan,
                    (rd.jumlah * (
                        CASE 
                            WHEN IFNULL(p.harga_final, 0) > 0 THEN p.harga_final 
                            ELSE IFNULL(mb_ref.harga_barang_stok, 0) 
                        END
                    )) as total_per_item,
                    'BELI' as kategori,
                    rd.nama_barang_manual as nama_barang_asli
                FROM master_mobil m
                INNER JOIN tr_request_detail rd ON m.id_mobil = rd.id_mobil
                INNER JOIN tr_request r ON rd.id_request = r.id_request
                LEFT JOIN master_barang mb_ref ON rd.nama_barang_manual = mb_ref.nama_barang
                LEFT JOIN (
                    SELECT id_request, nama_barang_beli, 
                           MAX(harga) as harga_final, 
                           MAX(tgl_beli_barang) as tgl_final 
                    FROM pembelian 
                    GROUP BY id_request, nama_barang_beli
                ) p ON (rd.id_request = p.id_request AND rd.nama_barang_manual = p.nama_barang_beli)
                WHERE (IFNULL(p.tgl_final, r.tgl_request) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                " . ($search != '' ? " AND (m.driver_tetap LIKE '%$search%' OR m.plat_nomor LIKE '%$search%')" : "") . "

                UNION ALL

                SELECT 
                    b.id_bon as id_transaksi,
                    m.driver_tetap, 
                    m.plat_nomor, 
                    m.jenis_kendaraan,
                    CONCAT('[STOK] ', mb.nama_barang, ' (', b.qty_keluar, ' ', mb.satuan, ')') as nama_item,
                    DATE(b.tgl_keluar) as tgl_beli, 
                    IFNULL(mb.harga_barang_stok, 0) as harga_satuan,
                    (b.qty_keluar * IFNULL(mb.harga_barang_stok, 0)) as total_per_item,
                    'STOK' as kategori,
                    mb.nama_barang as nama_barang_asli
                FROM master_mobil m
                INNER JOIN bon_permintaan b ON REPLACE(m.plat_nomor,' ','') = REPLACE(b.plat_nomor,' ','')
                INNER JOIN master_barang mb ON b.id_barang = mb.id_barang
                WHERE (DATE(b.tgl_keluar) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                " . ($search != '' ? " AND (m.driver_tetap LIKE '%$search%' OR m.plat_nomor LIKE '%$search%')" : "") . "
              ) AS gabungan
              ORDER BY driver_tetap ASC, plat_nomor ASC, tgl_beli ASC, kategori DESC";

$result = mysqli_query($koneksi, $query_sql);

if (!$result) {
    die("Query Error: " . mysqli_error($koneksi));
}

// Setelah dapat data, kita hitung tgl_terakhir secara manual di PHP
$data_by_driver = [];

while ($row = mysqli_fetch_assoc($result)) {
    $driver_key = $row['driver_tetap'] ?: 'TANPA DRIVER';
    $plat_key = $row['plat_nomor'];
    $barang_key = $row['nama_barang_asli'];
    
    // Simpan semua data dulu
    if (!isset($data_by_driver[$driver_key])) {
        $data_by_driver[$driver_key] = [];
    }
    
    if (!isset($data_by_driver[$driver_key][$plat_key])) {
        $data_by_driver[$driver_key][$plat_key] = [
            'driver' => $row['driver_tetap'],
            'jenis'  => $row['jenis_kendaraan'],
            'items'  => []
        ];
    }
    
    // Tambahkan data item
    $data_by_driver[$driver_key][$plat_key]['items'][] = $row;
}

// Urutkan driver secara abjad
ksort($data_by_driver);

// Fungsi untuk mencari tanggal terakhir pembelian barang yang sama
function getLastPurchaseDate($koneksi, $nama_barang, $tgl_sekarang, $plat_nomor) {
    $query = "SELECT MAX(tgl_beli) as tgl_terakhir FROM (
                SELECT IFNULL(p.tgl_beli_barang, r.tgl_request) as tgl_beli
                FROM tr_request_detail rd
                INNER JOIN tr_request r ON rd.id_request = r.id_request
                LEFT JOIN pembelian p ON rd.id_request = p.id_request AND rd.nama_barang_manual = p.nama_barang_beli
                WHERE rd.nama_barang_manual = '$nama_barang'
                AND rd.id_mobil = (SELECT id_mobil FROM master_mobil WHERE plat_nomor = '$plat_nomor' LIMIT 1)
                AND IFNULL(p.tgl_beli_barang, r.tgl_request) < '$tgl_sekarang'
                UNION ALL
                SELECT DATE(b.tgl_keluar) as tgl_beli
                FROM bon_permintaan b
                INNER JOIN master_barang mb ON b.id_barang = mb.id_barang
                WHERE mb.nama_barang = '$nama_barang'
                AND b.plat_nomor = '$plat_nomor'
                AND DATE(b.tgl_keluar) < '$tgl_sekarang'
            ) AS history
            ORDER BY tgl_beli DESC LIMIT 1";
    
    $res = mysqli_query($koneksi, $query);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['tgl_terakhir'];
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Rincian Mobil - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── TAMPILAN LAYAR ── */
        body { background-color: #f8f9fa; font-family: 'Times New Roman', Times, serif; }

        .table-laporan {
            width: 100%;
            border-collapse: collapse !important;
            background: white;
            border: 2px solid #000 !important;
        }
        .table-laporan th,
        .table-laporan td {
            border: 1px solid #000 !important;
            padding: 6px;
            vertical-align: middle;
            color: #000 !important;
        }
        .table-laporan th {
            background-color: #f2f2f2 !important;
            text-align: center;
            font-size: 10pt;
        }

        .header-laporan h4  { font-weight: bold; text-decoration: underline; margin-bottom: 2px; }
        .sub-total          { background-color: #f9f9f9 !important; font-weight: bold; }

        .badge-stok {
            color: #198754; font-weight: bold;
            border: 1px solid #198754;
            padding: 1px 4px; border-radius: 3px; font-size: 8pt;
        }
        .badge-jenis {
            background-color: #333; color: #fff;
            padding: 2px 6px; border-radius: 4px;
            font-size: 7pt; text-transform: uppercase;
            margin-bottom: 3px; display: inline-block;
        }
        .baris-total { background-color: #343a40 !important; color: #fff !important; font-weight: bold; }
        .baris-total td { color: #fff !important; }

        /* Badge untuk periode pembelian */
        .badge-periode {
            background-color: #0dcaf0;
            color: #000;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7pt;
            font-weight: bold;
            display: inline-block;
            margin-top: 3px;
        }

        /* Driver header */
        .driver-header {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 10pt;
            border-bottom: 2px solid #000 !important;
        }
        .driver-header td {
            padding: 8px 10px !important;
            background-color: #e9ecef !important;
        }

     /* ── CETAK: F4 / FOLIO (21.59cm x 33cm) ── */
    @media print {
        @page {
            size: F4; /* atau bisa juga: 21.59cm 33cm */
            margin: 1.5cm 1.5cm; /* Margin lebih kecil dari A4 */
        }

        body {
            background-color: white;
            margin: 0; padding: 0;
            font-family: Arial, sans-serif;
            font-size: 9pt; /* Font sedikit lebih besar dari A4 */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print { display: none !important; }
        .print-show { display: block !important; }
        .container-fluid { padding: 0 !important; }

        .header-laporan h4  { font-size: 14pt; } /* Lebih besar */
        .header-laporan p   { font-size: 10pt; }

        .table-laporan      { font-size: 9pt; width: 100%; }
        .table-laporan th   { font-size: 9pt; padding: 4px 5px; }
        .table-laporan td   { font-size: 9pt; padding: 4px 5px; }

        .badge-jenis        { font-size: 8pt; padding: 2px 6px; }
        .badge-stok         { font-size: 8pt; }
        .badge-periode      { font-size: 8pt; padding: 2px 5px; }

        .fw-bold            { font-size: 9pt !important; }
        .sub-total td       { font-size: 9pt; padding: 4px 5px; }
        .baris-total td     { font-size: 9pt; padding: 5px 5px; }
        .driver-header td   { font-size: 10pt; padding: 6px 10px !important; }

        /* Info periode untuk F4 */
        .info-periode-cetak {
            margin: 15px 0;
            padding: 8px;
            border: 1px solid #000;
            background-color: #f9f9f9 !important;
            font-size: 9pt;
        }
        
        /* Footer TTD untuk F4 */
        .footer-cetak-f4 {
            margin-top: 25px;
            page-break-inside: avoid;
        }


            /* Styling info periode saat cetak */
            .info-periode-cetak {
                margin: 10px 0;
                padding: 5px;
                border: 1px solid #000;
                background-color: #f9f9f9 !important;
                font-size: 7pt;
                page-break-inside: avoid;
            }
            
            .info-periode-cetak .badge-periode {
                font-size: 6pt;
                padding: 1px 4px;
                margin-right: 5px;
            }

            .d-none.d-print-flex { display: flex !important; margin-top: 16px; }
        }
    </style>
</head>
<body>

<div class="container-fluid py-3">

    <!-- Filter (tidak ikut cetak) -->
    <div class="card mb-4 no-print shadow-sm border-0">
        <div class="card-body bg-light">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-secondary text-uppercase">Periode Realisasi</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="tgl_awal"  class="form-control" value="<?= $tgl_awal ?>">
                        <span class="input-group-text">s/d</span>
                        <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-secondary text-uppercase">Cari Mobil/Driver</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($search) ?>" placeholder="Plat nomor atau nama...">
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="fas fa-filter me-1"></i> Tampilkan
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-dark px-3">
                        <i class="fas fa-print me-1"></i> Cetak 
                    </button>
                    <a href="?" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
                    <a href="../../index.php" class="btn btn-sm btn-danger px-3">Kembali</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Header laporan -->
    <div class="text-center mb-3 header-laporan">
        <h4 class="text-uppercase">Laporan Pemeliharaan &amp; Pengeluaran Mobil</h4>
        <p class="mb-0">
            Periode: <b><?= date('d/m/Y', strtotime($tgl_awal)) ?></b>
            s/d <b><?= date('d/m/Y', strtotime($tgl_akhir)) ?></b>
        </p>
    </div>

   <!-- Tabel laporan -->
<table class="table-laporan">
    <thead>
        <tr>
            <th width="4%">NO</th>
            <th width="15%">KENDARAAN / DRIVER</th>
            <th>NAMA BARANG / ITEM</th>
            <th width="8%">TGL BELI</th>
            <th width="10%">TGL TERAKHIR</th>
            <th width="10%">HARGA</th>
            <th width="10%">SUBTOTAL</th>
            <th width="5%" class="no-print">AKSI</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no          = 0;
        $grand_total = 0;
        
        if (!empty($data_by_driver)) :
            foreach ($data_by_driver as $driver_name => $data_mobil) :
        ?>
        <!-- Baris header driver -->
        <tr class="driver-header">
            <td colspan="8" style="text-align: left; padding-left: 15px;">
                <i class="fas fa-user me-2"></i> DRIVER: <?= strtoupper($driver_name) ?>
            </td>
        </tr>
        
        <?php
                foreach ($data_mobil as $plat => $m) :
                    $sub_total_mobil = 0;
                    $rowspan         = count($m['items']);
                    
                    foreach ($m['items'] as $index => $item) :
                        $sub_total_mobil += $item['total_per_item'];
                        $grand_total     += $item['total_per_item'];

                        $nama_item_display = $item['nama_item'];
                        if (strpos($nama_item_display, '[STOK]') !== false) {
                            $nama_item_display = str_replace('[STOK]', '<span class="badge-stok">STOK</span>', $nama_item_display);
                        }
                        
                        // Cari tanggal terakhir pembelian untuk barang yang sama
                        $tgl_terakhir = '-';
                        $badge_periode = '';
                        
                        if ($item['nama_barang_asli'] && $item['tgl_beli'] != '0000-00-00') {
                            $tgl_terakhir_db = getLastPurchaseDate($koneksi, $item['nama_barang_asli'], $item['tgl_beli'], $plat);
                            
                            if ($tgl_terakhir_db) {
                                $tgl_terakhir = date('d/m/y', strtotime($tgl_terakhir_db));
                                
                                // Hitung selisih hari
                                $tgl1 = new DateTime($item['tgl_beli']);
                                $tgl2 = new DateTime($tgl_terakhir_db);
                                $selisih_hari = $tgl1->diff($tgl2)->days;
                                
                                if ($selisih_hari <= 30) {
                                    $badge_periode = '<span class="badge-periode" style="background:#198754; color:white;">Baru</span>';
                                } elseif ($selisih_hari <= 90) {
                                    $badge_periode = '<span class="badge-periode" style="background:#ffc107;">' . $selisih_hari . ' hr</span>';
                                } else {
                                    $badge_periode = '<span class="badge-periode" style="background:#dc3545; color:white;">Lama</span>';
                                }
                            }
                        }
        ?>
        <tr>
            <?php if ($index === 0) : ?>
                <td rowspan="<?= $rowspan ?>" class="text-center fw-bold"><?= ++$no ?></td>
                <td rowspan="<?= $rowspan ?>" class="align-top pt-2">
                    <span class="badge-jenis"><?= ($m['jenis'] ?: 'Unit') ?></span><br>
                    <span class="fw-bold" style="font-size:11pt;"><?= $plat ?></span><br>
                    <small class="text-uppercase text-muted"><?= $m['driver'] ?></small>
                </td>
            <?php endif; ?>

            <td><?= $nama_item_display ?></td>
            <td class="text-center">
                <?php
                $tgl_val     = $item['tgl_beli'];
                $tgl_display = ($tgl_val != '' && $tgl_val != '0000-00-00')
                    ? date('d/m/y', strtotime($tgl_val)) : '-';
                ?>
                <a href="javascript:void(0)"
                   class="text-decoration-none <?= ($tgl_val == '' || $tgl_val == '0000-00-00') ? 'text-danger' : 'text-primary' ?> no-print"
                   onclick="editTanggal('<?= $item['id_transaksi'] ?>', '<?= $item['kategori'] ?>', '<?= $tgl_val ?>')">
                    <?= $tgl_display ?>
                </a>
                <span class="d-none d-print-inline"><?= $tgl_display ?></span>
            </td>
            <td class="text-center">
                <span class="fw-bold"><?= $tgl_terakhir ?></span>
                <?= $badge_periode ?>
            </td>
            <td class="text-end">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
            <td class="text-end fw-bold">Rp <?= number_format($item['total_per_item'], 0, ',', '.') ?></td>
            <td class="text-center no-print">
                <a href="hapus_item_mobil.php?id=<?= $item['id_transaksi'] ?>&kat=<?= $item['kategori'] ?>"
                   class="text-danger" onclick="return confirm('Hapus item ini?')">
                   <i class="fas fa-trash-alt"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>

        <!-- Subtotal per mobil -->
        <tr class="sub-total">
            <td colspan="4" class="text-end text-uppercase" style="font-size:8pt;">
                Subtotal Biaya <?= $plat ?> :
            </td>
            <td colspan="2" class="text-end fw-bold">Rp <?= number_format($sub_total_mobil, 0, ',', '.') ?></td>
            <td class="no-print"></td>
            <td class="no-print"></td>
        </tr>

        <?php 
                    endforeach; // end per mobil
                endforeach; // end per driver
            ?>

        <!-- Grand total -->
        <tr class="baris-total">
            <td colspan="5" class="text-end py-2 fw-bold">TOTAL KESELURUHAN :</td>
            <td class="text-end py-2 fw-bold" colspan="2">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
            <td class="no-print"></td>
        </tr>

        <?php else : ?>
        <tr>
            <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-info-circle me-2"></i>
                Belum ada data pemeliharaan untuk periode ini.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Footer Cetak dengan Informasi Periode -->
<div class="row mt-4 d-none d-print-flex" style="page-break-before: avoid;">
    <div class="col-12 mb-3">
        <table style="width:100%; border-top:2px solid #333; padding-top:5px;">
            <tr>
                <td width="60%" style="vertical-align:top;">
                    <table style="border:1px solid #999; border-collapse:collapse; width:100%;">
                        <tr>
                            <td colspan="2" style="background:#e9ecef; padding:3px; font-weight:bold; text-align:center; border:1px solid #999;">
                                LEGENDA PERIODE PEMBELIAN
                            </td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #999; padding:3px; width:30%; text-align:center;">
                                <span style="background:#198754; color:white; padding:2px 8px; border-radius:3px;">Baru</span>
                            </td>
                            <td style="border:1px solid #999; padding:3px;">Pembelian dalam 30 hari terakhir</td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #999; padding:3px; text-align:center;">
                                <span style="background:#ffc107; padding:2px 8px; border-radius:3px;">90 hr</span>
                            </td>
                            <td style="border:1px solid #999; padding:3px;">Pembelian 31 - 90 hari yang lalu</td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #999; padding:3px; text-align:center;">
                                <span style="background:#dc3545; color:white; padding:2px 8px; border-radius:3px;">Lama</span>
                            </td>
                            <td style="border:1px solid #999; padding:3px;">Pembelian lebih dari 90 hari yang lalu</td>
                        </tr>
                    </table>
                </td>
                <td width="40%" style="text-align:right; vertical-align:bottom;">
                    <p class="mb-5">
                        Surabaya, <?= date('d') ?> <?= $nama_bulan[date('m')] ?> <?= date('Y') ?>
                    </p>
                    <p class="fw-bold mb-0">( ____________________ )</p>
                    <p>Manager</p>
                </td>
            </tr>
        </table>
    </div>
</div>

</div><!-- /container -->

<!-- Modal edit tanggal (tidak ikut cetak) -->
<div class="modal fade no-print" id="modalEditTgl" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="update_tgl_laporan.php" method="POST" class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Sesuaikan Tanggal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id"  id="edit_id">
                <input type="hidden" name="kat" id="edit_kat">
                <div class="mb-2">
                    <label class="small fw-bold">Tanggal Nota/Beli</label>
                    <input type="date" name="tgl_baru" id="edit_tgl"
                           class="form-control form-control-sm" required>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Update Data</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTanggal(id, kat, tgl) {
    document.getElementById('edit_id').value  = id;
    document.getElementById('edit_kat').value = kat;
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('edit_tgl').value = (tgl === '' || tgl === '0000-00-00') ? today : tgl;
    new bootstrap.Modal(document.getElementById('modalEditTgl')).show();
}
</script>
</body>
</html>