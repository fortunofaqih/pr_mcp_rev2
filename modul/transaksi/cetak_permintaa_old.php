<?php
include '../../config/koneksi.php';

$id_bon = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : '';

$sql = "SELECT b.*, m.nama_barang, m.satuan 
        FROM bon_permintaan b 
        LEFT JOIN master_barang m ON b.id_barang = m.id_barang 
        WHERE b.id_bon = '$id_bon'";

$query = mysqli_query($koneksi, $sql);
$data  = mysqli_fetch_array($query);

if (!$data) {
    echo "<div style='text-align:center; padding:50px;'><h3>Data Tidak Ditemukan</h3></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bon - <?= $data['no_permintaan'] ?></title>
    <style>
        @page {
            size: 170mm 105mm;
            margin: 3mm;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Arial', sans-serif; 
            background-color: #fff;
            font-size: 8pt;
            width: 170mm;
            height: 105mm;
        }

        .bon-wrapper {
            width: 164mm;  /* 170 - (3mm x 2) */
            height: 99mm;  /* 105 - (3mm x 2) */
            padding: 3mm 4mm;
            position: relative;
            overflow: hidden;
        }

        /* HEADER */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .header-left h3 { 
            font-size: 10pt; 
            text-transform: uppercase; 
            margin-bottom: 1px;
        }
        .header-left span { 
            font-size: 7pt; 
            color: #444; 
        }

        .header-right { text-align: right; }
        .header-right .no-pr { 
            font-weight: bold; 
            font-size: 9.5pt; 
            display: block; 
        }
        .header-right .tgl {
            font-size: 7.5pt;
        }

        /* INFO PENERIMA */
        .info-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 8pt;
        }
        .info-grid div { flex: 1; }
        .info-grid div:last-child { text-align: right; }

        /* TABEL BARANG */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            font-size: 8pt;
        }
        .table-items th, .table-items td {
            border: 1px solid #000;
            padding: 3px 5px;
            text-align: center;
        }
        .table-items th { 
            background: #f0f0f0; 
            font-size: 7.5pt; 
        }
        .table-items td.left { text-align: left; }

        /* TTD */
        .ttd-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            text-align: center;
            font-size: 7.5pt;
            gap: 4px;
        }
        .ttd-box { 
            height: 25px; 
        }
        .ttd-line {
            
            padding-top: 2px;
        }

        /* FOOTER */
        .footer-label {
            position: absolute;
            bottom: 3mm;
            right: 4mm;
            font-size: 6pt;
            color: #999;
        }

        @media print {
            .no-print { display: none !important; }
            body { width: 170mm; height: 105mm; }
        }

        .no-print {
            padding: 10px 20px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
            margin-top: 10px;
        }
        .btn-print {
            background: #0000FF;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 9pt;
        }
    </style>
</head>
<body onload="window.print()">

<div class="bon-wrapper">

    <!-- HEADER -->
    <div class="header-section">
        <div class="header-left">
            <h3>Bukti Permintaan Barang</h3>
            <span>PT. MUTIARA CAHAYA PLASTINDO</span>
        </div>
        <div class="header-right">
            <span class="no-pr">NO: <?= $data['no_permintaan'] ?></span>
            <span class="tgl">Tgl: <?= date('d/m/Y', strtotime($data['tgl_keluar'])) ?></span>
        </div>
    </div>

    <!-- INFO PENERIMA -->
    <div class="info-grid">
        <div>Penerima: <strong><?= strtoupper($data['penerima']) ?></strong></div>
        <?php if(!empty($data['plat_nomor'])): ?>
        <div style="text-align:right;">Unit: <strong><?= strtoupper($data['plat_nomor']) ?></strong></div>
        <?php endif; ?>
    </div>

    <!-- TABEL BARANG -->
    <table class="table-items">
        <thead>
            <tr>
                <th width="6%">NO</th>
                <th width="30%">NAMA BARANG</th>
                <th width="10%">QTY</th>
                <th width="10%">SAT</th>
                <th>KEPERLUAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td class="left"><strong><?= strtoupper($data['nama_barang']) ?></strong></td>
                <td><strong style="font-size:10pt;"><?= (float)$data['qty_keluar'] ?></strong></td>
                <td><?= strtoupper($data['satuan']) ?></td>
                <td class="left"><?= strtoupper($data['keperluan']) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TTD -->
   <!-- TTD -->
<div class="ttd-grid">
    <div>
        <span>Menyerahkan,</span>
        <div class="ttd-box"></div>
        <div class="ttd-line">( ________________ )</div>
    </div>
    <div>
        <span>Penerima,</span>
        <div class="ttd-box"></div>
        <div class="ttd-line">( ________________ )</div>
    </div>
    <div>
        <span>Mengetahui,</span>
        <div class="ttd-box"></div>
        <div class="ttd-line">( ________________ )</div>
    </div>
</div>

    <div class="footer-label">Gudang - <?= date('d/m/Y H:i') ?></div>
</div>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">ЁЯЦия╕П Cetak Sekarang</button>
    <span style="font-size:8pt; color:orange; margin-left:10px;">
        * Ukuran cetak: 17cm x 10,5cm | Margin: 3mm | Skala: 100%
    </span>
</div>

</body>
</html>