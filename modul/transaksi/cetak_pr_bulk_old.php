<?php
include '../../config/koneksi.php';

$tgl_dari   = mysqli_real_escape_string($koneksi, $_GET['tgl_dari']   ?? $_GET['tgl'] ?? date('Y-m-d'));
$tgl_sampai = mysqli_real_escape_string($koneksi, $_GET['tgl_sampai'] ?? $_GET['tgl'] ?? date('Y-m-d'));
$status     = mysqli_real_escape_string($koneksi, $_GET['status']     ?? 'PENDING');

$sql   = "SELECT * FROM tr_request
          WHERE tgl_request BETWEEN '$tgl_dari' AND '$tgl_sampai'
            AND status_request = '$status'
          ORDER BY tgl_request ASC, no_request ASC";
$query = mysqli_query($koneksi, $sql);

if (mysqli_num_rows($query) == 0) {
    die("<script>alert('Tidak ada data PR pada periode tersebut.'); window.close();</script>");
}

// Tampung semua PR + detailnya dulu sebelum render
$all_pr = [];
while ($row = mysqli_fetch_assoc($query)) {
    $id_req = (int)$row['id_request'];
    $sql_d  = "SELECT d.*, b.nama_barang AS master, m.plat_nomor
               FROM tr_request_detail d
               LEFT JOIN master_barang b ON d.id_barang = b.id_barang
               LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
               WHERE d.id_request = $id_req
               ORDER BY d.id_detail ASC";
    $res_d   = mysqli_query($koneksi, $sql_d);
    $items   = [];
    while ($d = mysqli_fetch_assoc($res_d)) { $items[] = $d; }
    $row['items'] = $items;
    $all_pr[] = $row;
}

$total = count($all_pr);

$periode = ($tgl_dari === $tgl_sampai)
    ? date('d/m/Y', strtotime($tgl_dari))
    : date('d/m/Y', strtotime($tgl_dari)).' s/d '.date('d/m/Y', strtotime($tgl_sampai));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Bulk PR - MCP</title>
    <style>
        /* ── KERTAS LEGAL PORTRAIT ── */
        @page {
            size: 21.5cm 33cm portrait;
            margin: 1cm 1.2cm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            margin: 0; padding: 0;
            color: #000;
            background: #fff;
        }

        /* ── TIAP PR = 1 HALAMAN ── */
        .pr-page {
            width: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            page-break-after: always;
        }
        /* Halaman terakhir tidak perlu page-break */
        .pr-page:last-child {
            page-break-after: auto;
        }

        /* ── HEADER TIAP HALAMAN ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .header-left .title    { font-size: 11pt; font-weight: bold; }
        .header-left .subtitle { font-size: 7.5pt; color: #444; }
        .header-right          { font-size: 7pt; text-align: right; color: #555; line-height: 1.7; }

        /* ── INFO PR (no, pemesan, tgl, pembeli) ── */
        .pr-infobar {
            background: #e9e9e9 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 4px 8px;
            border: 0.5px solid #000;
            border-bottom: none;
        }

        /* ── BODY: tabel kiri + TTD kanan ── */
        .pr-body {
            display: flex;
            gap: 8px;
            border: 0.5px solid #000;
            padding: 5px;
            flex: 1;
        }
        .col-table { flex: 1 1 auto; min-width: 0; }
        .col-ttd   { flex: 0 0 95px; width: 95px; display: flex; flex-direction: column; }

        /* ── TABEL ITEM ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            table-layout: fixed;
        }
        table.data-table th,
        table.data-table td {
            border: 0.5px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.data-table th {
            background: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-align: center;
            font-size: 7pt;
        }
        .c-no   { width: 28px; text-align: center; }
        .c-mob  { width: 58px; text-align: center; }
        .c-tipe { width: 38px; text-align: center; }
        .c-qty  { width: 55px; text-align: center; }
        .c-ket  { width: 105px; }
        .center { text-align: center; }

        /* ── BLOK TTD ── */
        .ttd-block {
            border: 0.5px solid #000;
            border-radius: 2px;
            padding: 6px 5px 5px;
            font-size: 6.5pt;
            flex: 1;
        }
        .ttd-item {
            margin-bottom: 0;
            padding-bottom: 8px;
            border-bottom: 0.5px dashed #aaa;
            margin-bottom: 8px;
        }
        .ttd-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        .ttd-item .lbl  {
            font-weight: bold;
            margin-bottom: 35px; /* ruang tanda tangan */
        }
        .ttd-item .line { border-top: 0.5px solid #000; }
        .ttd-item .note { font-size: 6pt; color: #555; margin-top: 2px; }

        /* ── LEGEND TTD ── */
        .ttd-legend {
            font-size: 6pt;
            color: #555;
            margin-top: 5px;
            line-height: 1.7;
        }

        /* ── FOOTER HALAMAN ── */
        .page-footer {
            margin-top: 8px;
            font-size: 7pt;
            color: #666;
            font-style: italic;
            border-top: 0.5px dashed #aaa;
            padding-top: 4px;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">

<!-- Tombol print -->
<div class="no-print" style="background:#fff3cd; padding:8px; margin-bottom:14px; border:1px solid #ffc107; border-radius:4px; display:flex; align-items:center; gap:12px;">
    <button onclick="window.print()" style="padding:7px 18px; background:#007bff; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">
        🖨️ PRINT BULK PR
    </button>
    <span style="font-size:11px; color:#555;">
        Kertas: Legal Portrait &nbsp;|&nbsp;
        Periode: <strong><?= $periode ?></strong> &nbsp;|&nbsp;
        Total PR: <strong><?= $total ?></strong>
    </span>
</div>

<?php foreach ($all_pr as $idx => $pr): ?>
<div class="pr-page">

    <!-- Header halaman -->
    <div class="page-header">
        <div class="header-left">
            <div class="title">PURCHASE REQUEST FORM</div>
            <div class="subtitle">PT. Mutiara Cahaya Plastindo</div>
        </div>
        <div class="header-right">
            Periode: <strong><?= $periode ?></strong><br>
            <?= ($idx + 1) ?> / <?= $total ?> &nbsp;|&nbsp; Cetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- Info bar PR -->
    <div class="pr-infobar">
        <span>NO: <?= $pr['no_request'] ?></span>
        <span>TGL: <?= date('d/m/Y', strtotime($pr['tgl_request'])) ?></span>
        <span>PEMESAN: <?= strtoupper($pr['nama_pemesan']) ?></span>
        <span>PEMBELI: <?= strtoupper($pr['nama_pembeli'] ?: '-') ?></span>
        <span class="badge">KATEGORI: <?= $pr['kategori_pr'] ?></span>
    </div>

    <!-- Body: tabel item kiri + TTD kanan -->
    <div class="pr-body">

        <!-- Tabel item -->
        <div class="col-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="c-no">NO</th>
                        <th>NAMA BARANG / ITEM</th>
                        <th class="c-mob">UNIT/MOBIL</th>
                        <th class="c-tipe">TIPE</th>
                        <th class="c-qty">QTY</th>
                        <th class="c-ket">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pr['items'] as $i => $d):
                        $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['master'];
                    ?>
                    <tr>
                        <td class="center"><?= $i + 1 ?></td>
                        <td style="font-weight:bold;"><?= strtoupper($nama) ?></td>
                        <td class="center" style="font-size:7pt;">
                            <?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? $d['plat_nomor'] : '-' ?>
                        </td>
                        <td class="center" style="font-size:6.5pt; font-weight:bold;"><?= $d['tipe_request'] ?></td>
                        <td class="center"><b><?= (float)$d['jumlah'] ?></b> <?= $d['satuan'] ?></td>
                        <td style="font-size:7pt; color:#333;"><?= $d['keterangan'] ?: '-' ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php
                    // Baris kosong minimal agar tabel tidak terlalu mepet (min 4 baris)
                    $min = 4;
                    for ($x = count($pr['items']); $x < $min; $x++):
                    ?>
                    <tr style="color:#ddd;">
                        <td class="center"><?= $x + 1 ?></td>
                        <td></td><td></td><td></td><td></td><td></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Blok TTD kanan -->
        <div class="col-ttd">
            <div class="ttd-block">
                <div class="ttd-item">
                    <div class="lbl">TTD 1</div>
                    <div class="line"></div>
                    <div class="note">Pemesan</div>
                </div>
                <div class="ttd-item">
                    <div class="lbl">TTD 2</div>
                    <div class="line"></div>
                    <div class="note">Pembeli</div>
                </div>
                <div class="ttd-item">
                    <div class="lbl">TTD 3</div>
                    <div class="line"></div>
                    <div class="note">Mengetahui</div>
                </div>
            </div>
            <div class="ttd-legend">
                <span>TTD 1 = Pemesan</span>
                <span>TTD 2 = Pembeli</span>
                <span>TTD 3 = Mengetahui</span>
            </div>
        </div>

    </div><!-- /pr-body -->

    <!-- Footer halaman -->
    <div class="page-footer">
        <span>TTD 1 = Pemesan &nbsp;|&nbsp; TTD 2 = Pembeli &nbsp;|&nbsp; TTD 3 = Mengetahui</span>
        <span><?= $pr['no_request'] ?> &nbsp;—&nbsp; <?= ($idx + 1) ?>/<?= $total ?></span>
    </div>

</div><!-- /pr-page -->
<?php endforeach; ?>

</body>
</html>