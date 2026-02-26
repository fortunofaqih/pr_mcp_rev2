<?php
session_start();
include '../../config/koneksi.php';

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_header);

if (!$h) { die("Data tidak ditemukan."); }

$sql_detail = "SELECT d.*, m.plat_nomor, b.nama_barang as nama_barang_master 
               FROM tr_request_detail d
               LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil
               LEFT JOIN master_barang b ON d.id_barang = b.id_barang
               WHERE d.id_request = '$id' 
               ORDER BY d.id_detail ASC";
$query_detail = mysqli_query($koneksi, $sql_detail);
$items = [];
while ($d = mysqli_fetch_array($query_detail)) { $items[] = $d; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR - <?= $h['no_request'] ?></title>
    <style>
        @page {
            size: 21.5cm 16.5cm landscape;
            margin: 0.5cm 0.6cm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            margin: 0;
            padding: 8px;
            background: #fff;
            color: #000;
        }

        /* ── LAYOUT UTAMA: tabel kiri, TTD kanan ── */
        .layout-wrapper {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            width: 100%;
        }
        .col-table { flex: 1 1 auto; min-width: 0; }
        .col-ttd   { flex: 0 0 108px; width: 108px; }

        /* ── HEADER ── */
        .header {
            text-align: center;
            border-bottom: 1.5px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .header h2 { margin: 0; font-size: 11pt; letter-spacing: 0.5px; }
        .header h4 { margin: 0; font-size: 8pt; font-weight: normal; }

        /* ── INFO PR ── */
        .info-pr {
            width: 100%;
            margin-bottom: 5px;
            font-size: 7.5pt;
            font-weight: bold;
        }

        /* ── TABEL DATA ── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        table.data th,
        table.data td {
            border: 0.5px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }
        table.data th {
            background-color: #ddd !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-align: center;
            font-size: 7pt;
        }
        table.data td.center { text-align: center; }
        table.data td.right  { text-align: right; }

        /* ── KOLOM TABEL ── */
        .col-no   { width: 18px; }
        .col-item { width: auto; }
        .col-unit { width: 52px; }
        .col-tipe { width: 40px; }
        .col-qty  { width: 52px; }
        .col-ket  { width: 110px; }

        /* ── BLOK TTD SAMPING ── */
        .ttd-block {
            width: 100%;
            border: 0.5px solid #000;
            border-radius: 3px;
            padding: 6px 4px 4px;
            font-size: 7pt;
        }
        .ttd-item {
            margin-bottom: 6px;
            border-bottom: 0.5px dashed #aaa;
            padding-bottom: 6px;
        }
        .ttd-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .ttd-item .label {
            font-weight: bold;
            font-size: 6.5pt;
            color: #333;
            margin-bottom: 28px; /* ruang tanda tangan */
        }
        .ttd-item .garis {
            border-top: 0.5px solid #000;
            margin-top: 2px;
        }
        .ttd-item .note {
            font-size: 6pt;
            color: #555;
            margin-top: 2px;
        }

        /* ── KETERANGAN NOMOR TTD ── */
        .ttd-legend {
            margin-top: 5px;
            font-size: 6.5pt;
            color: #444;
            line-height: 1.6;
        }
        .ttd-legend span { display: block; }

        /* ── PRINT ── */
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- Tombol print (tidak ikut cetak) -->
    <div class="no-print" style="background:#fff3cd; padding:8px; margin-bottom:12px; border:1px solid #ffc107; border-radius:4px;">
        <button onclick="window.print()" style="padding:7px 18px; background:#007bff; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
            🖨️ PRINT PURCHASE REQUEST
        </button>
        <span style="font-size:11px; margin-left:10px; color:#555;">Ukuran: Setengah Folio (F4 Landscape)</span>
    </div>

    <!-- HEADER -->
    <div class="header">
        <h2>PURCHASE REQUEST (PR)</h2>
        <h4>PT. Mutiara Cahaya Plastindo</h4>
    </div>

    <!-- INFO PR -->
    <table class="info-pr">
        <tr>
            <td width="33%">NO: <?= $h['no_request'] ?></td>
            <td width="34%" style="text-align:center;">Admin: <?= strtoupper($h['nama_pemesan']) ?></td>
            <td width="33%" style="text-align:right;">TGL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></td>
        </tr>
    </table>

    <!-- LAYOUT: TABEL KIRI + TTD KANAN -->
    <div class="layout-wrapper">

        <!-- TABEL ITEM PR -->
        <div class="col-table">
            <table class="data">
                <thead>
                    <tr>
                        <th class="col-no">NO</th>
                        <th class="col-item">NAMA BARANG / ITEM</th>
                        <th class="col-unit">UNIT/MOBIL</th>
                        <th class="col-tipe">TIPE</th>
                        <th class="col-qty">QTY</th>
                        <th class="col-ket">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $d):
                        $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['nama_barang_master'];
                    ?>
                    <tr>
                        <td class="center"><?= $i + 1 ?></td>
                        <td style="font-weight:bold;"><?= strtoupper($nama) ?></td>
                        <td class="center"><?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? $d['plat_nomor'] : '-' ?></td>
                        <td class="center" style="font-size:6.5pt; font-weight:bold;"><?= $d['tipe_request'] ?></td>
                        <td class="center"><b><?= (float)$d['jumlah'] ?></b> <?= $d['satuan'] ?></td>
                        <td style="font-size:7pt;"><?= $d['keterangan'] ?: '-' ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Baris kosong tambahan jika item sedikit agar tabel terlihat rapi -->
                    <?php
                    $min_rows = 5;
                    $sisa = $min_rows - count($items);
                    for ($x = 0; $x < $sisa; $x++):
                    ?>
                    <tr>
                        <td class="center" style="color:#ccc;"><?= count($items) + $x + 1 ?></td>
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- BLOK TANDA TANGAN (KANAN) -->
        <div class="col-ttd">
            <div class="ttd-block">

                <div class="ttd-item">
                    <div class="label">TTD 1</div>
                    <div class="garis"></div>
                    <div class="note">Pemesan</div>
                </div>

                <div class="ttd-item">
                    <div class="label">TTD 2</div>
                    <div class="garis"></div>
                    <div class="note">Pembeli</div>
                </div>

                <div class="ttd-item">
                    <div class="label">TTD 3</div>
                    <div class="garis"></div>
                    <div class="note">Mengetahui</div>
                </div>

            </div>

            <!-- Keterangan nomor TTD -->
            <div class="ttd-legend">
                <span>TTD 1 = Pemesan</span>
                <span>TTD 2 = Pembeli</span>
                <span>TTD 3 = Mengetahui</span>
            </div>
        </div>

    </div><!-- /layout-wrapper -->

</body>
</html>