<?php
session_start();
include '../../config/koneksi.php';

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_header);

if (!$h) { die("Data tidak ditemukan."); }

// ── Generate no_form jika belum ada ──────────────────────────────────────────

if (empty($h['no_form'])) {
    $bulan = date('m');
    $tahun = date('Y');

    // Ambil 3 digit terakhir dari no_request
    $suffix = substr($h['no_request'], -3);

    $no_form = "PR-MCP-$suffix/$bulan/$tahun";

    mysqli_query($koneksi,
        "UPDATE tr_request SET no_form = '$no_form' WHERE id_request = '$id'"
    );
    $h['no_form'] = $no_form;
}
// ─────────────────────────────────────────────────────────────────────────────

$sql_detail = "SELECT d.*, m.plat_nomor, b.nama_barang as nama_barang_master 
               FROM tr_request_detail d
               LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil
               LEFT JOIN master_barang b ON d.id_barang = b.id_barang
               WHERE d.id_request = '$id' 
               ORDER BY d.id_detail ASC";
$items = [];
$q = mysqli_query($koneksi, $sql_detail);
while ($d = mysqli_fetch_array($q)) { $items[] = $d; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR - <?= $h['no_request'] ?></title>
    <style>
        @page { size: 21.5cm 16.5cm landscape; margin: 0.5cm 0.7cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 8px; background:#fff; color:#000; }

        .header { text-align:center; border-bottom:1.5px solid #000; padding-bottom:4px; margin-bottom:6px; }
        .header h2 { margin:0; font-size:11pt; }
        .header h4 { margin:0; font-size:8pt; font-weight:normal; }

        .info-pr { width:100%; margin-bottom:5px; font-size:7.5pt; font-weight:bold; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; }
        table.data th, table.data td {
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

        .c-no   { width:22px;  text-align:center; }
        .c-unit { width:54px;  text-align:center; }
        .c-tipe { width:36px;  text-align:center; }
        .c-qty  { width:52px;  text-align:center; }
        .c-ket  { width:95px; }
        /* TTD per baris — tinggi memberi ruang tanda tangan */
        .c-ttd  { width:50px; text-align:center; height:36px; font-size:6.5pt; color:#777; }

        .ttd-note {
            margin-top:4px; font-size:6.5pt; color:#444;
            border-top:0.5px dashed #aaa; padding-top:3px;
        }

        @media print {
            .no-print { display:none !important; }
            body { padding:0; margin:0; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="no-print" style="background:#fff3cd;padding:8px;margin-bottom:12px;border:1px solid #ffc107;border-radius:4px;">
    <button onclick="window.print()" style="padding:7px 18px;background:#007bff;color:#fff;border:none;border-radius:4px;font-weight:bold;cursor:pointer;">
        🖨️ PRINT PURCHASE REQUEST
    </button>
    <span style="font-size:11px;margin-left:10px;color:#555;">Setengah Folio (F4 Landscape)</span>
</div>

<div class="header">
    <h2>PURCHASE REQUEST (PR)</h2>
    <h4>PT. Mutiara Cahaya Plastindo</h4>
    
</div>
<h4 style="margin-top:2px; font-weight:bold; font-size:8pt; color:#000;">FORM :
        <?= $h['no_form'] ?>
    </h4>
<table class="info-pr">
    <tr>
        
       <td width="33%">NO: <?= $h['no_request'] ?></td>
        <td width="33%" style="text-align:right;">TGL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th class="c-no">NO</th>
            <th>NAMA BARANG / ITEM</th>
            <th class="c-unit">UNIT/MOBIL</th>
            <th class="c-tipe">TIPE</th>
            <th class="c-qty">QTY</th>
            <th class="c-ket">KETERANGAN</th>
            <th class="c-ttd">TTD 1</th>
            <th class="c-ttd">TTD 2</th>
            <th class="c-ttd">TTD 3</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $d):
            $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['nama_barang_master'];
        ?>
        <tr>
            <td style="text-align:center;"><?= $i + 1 ?></td>
            <td style="font-weight:bold;"><?= strtoupper($nama) ?></td>
            <td style="text-align:center;"><?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? $d['plat_nomor'] : '-' ?></td>
            <td style="text-align:center;font-size:6.5pt;font-weight:bold;"><?= $d['tipe_request'] ?></td>
            <td style="text-align:center;"><b><?= (float)$d['jumlah'] ?></b> <?= $d['satuan'] ?></td>
            <td style="font-size:7pt;"><?= $d['keterangan'] ?: '-' ?></td>
            <td class="c-ttd"></td>
            <td class="c-ttd"></td>
            <td class="c-ttd"></td>
        </tr>
        <?php endforeach; ?>

        <?php for ($x = count($items); $x < 3; $x++): ?>
        <tr>
            <td style="text-align:center;color:#ccc;"><?= $x + 1 ?></td>
            <td>&nbsp;</td><td></td><td></td><td></td><td></td>
            <td class="c-ttd"></td><td class="c-ttd"></td><td class="c-ttd"></td>
        </tr>
        <?php endfor; ?>
    </tbody>
</table>

<div class="ttd-note">
    TTD 1 = Pemesan &nbsp;|&nbsp; TTD 2 = Pembeli &nbsp;|&nbsp; TTD 3 = Mengetahui
</div>

</body>
</html>