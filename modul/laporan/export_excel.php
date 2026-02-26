<?php
include '../../config/koneksi.php';

// 1. Ambil Parameter
$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$huruf_awal  = $_GET['huruf_awal'] ?? 'A';
$huruf_akhir = $_GET['huruf_akhir'] ?? 'Z';
$search_nama = isset($_GET['search_nama']) ? mysqli_real_escape_string($koneksi, $_GET['search_nama']) : '';
$filter_rak  = isset($_GET['filter_rak']) ? mysqli_real_escape_string($koneksi, $_GET['filter_rak']) : '';

// 2. Header Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=SO_Gudang_" . date('d-m-Y') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 3. Query (Identik dengan Web)
$sql = "SELECT 
            m.nama_barang, m.satuan, m.lokasi_rak,
            COALESCE(awal.total_awal, 0) as stok_awal,
            COALESCE(mutasi.m_masuk, 0) as masuk,
            COALESCE(mutasi.m_keluar, 0) as keluar,
            (COALESCE(awal.total_awal, 0) + COALESCE(mutasi.m_masuk, 0) - COALESCE(mutasi.m_keluar, 0)) as stok_akhir
        FROM master_barang m
        LEFT JOIN (
            SELECT id_barang, SUM(CASE WHEN tipe_transaksi = 'MASUK' THEN qty ELSE -qty END) as total_awal
            FROM tr_stok_log WHERE tgl_log < '$tgl_mulai 00:00:00' GROUP BY id_barang
        ) awal ON m.id_barang = awal.id_barang
        LEFT JOIN (
            SELECT id_barang, SUM(CASE WHEN tipe_transaksi = 'MASUK' THEN qty ELSE 0 END) as m_masuk,
            SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) as m_keluar
            FROM tr_stok_log WHERE tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59' GROUP BY id_barang
        ) mutasi ON m.id_barang = mutasi.id_barang
        WHERE 1=1";

if ($search_nama != '') {
    $sql .= " AND m.nama_barang LIKE '%$search_nama%'";
} elseif ($filter_rak != '') {
    $sql .= " AND m.lokasi_rak = '$filter_rak'";
} else {
    $sql .= " AND LEFT(m.nama_barang, 1) BETWEEN '$huruf_awal' AND '$huruf_akhir'";
}

$sql .= " ORDER BY m.lokasi_rak ASC, m.nama_barang ASC";
$query = mysqli_query($koneksi, $sql);
?>

<meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">

<table border="1">
    <tr>
        <th colspan="10" style="font-size:16px; text-align:center; height:30px; vertical-align:middle;">
            LAPORAN MUTASI & STOK OPNAME GUDANG
        </th>
    </tr>
    <tr>
        <th colspan="10" style="text-align:center;">
            Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
        </th>
    </tr>
    <tr style="background-color: #00008B; color: #FFFFFF; font-weight: bold; text-align:center;">
        <th width="50">NO</th>
        <th width="400">NAMA BARANG</th>
        <th width="100">RAK</th>
        <th width="100">SATUAN</th>
       <!-- <th width="100">AWAL</th>
        <th width="100">MASUK</th>
        <th width="100">KELUAR</th>-->
        <th width="120">STOK AKHIR</th>
        <th width="120">STOK FISIK</th>
        <th width="50">CEK</th>
    </tr>

    <?php 
    $no = 1; 
    $last_rak = null;
    while($r = mysqli_fetch_array($query)): 
        // Logika grouping rak yang sama dengan tampilan web
        if ($filter_rak == '' && $r['lokasi_rak'] !== $last_rak):
    ?>
        <tr style="background-color: #f2f2f2; font-weight: bold;">
            <td colspan="10" style="padding-left: 10px;">LOKASI RAK: <?= $r['lokasi_rak'] ?: 'TANPA RAK' ?></td>
        </tr>
    <?php 
        $last_rak = $r['lokasi_rak'];
        endif; 
    ?>
    <tr>
        <td align="center"><?= $no++ ?></td>
        <td><?= strtoupper($r['nama_barang']) ?></td>
        <td align="center"><?= $r['lokasi_rak'] ?: '-' ?></td>
        <td align="center"><?= $r['satuan'] ?></td>
       <!-- <td align="center" style="mso-number-format:'\#\,\#\#0';"><?= $r['stok_awal'] ?></td>
        <td align="center" style="mso-number-format:'\#\,\#\#0'; color:green;"><?= $r['masuk'] ?></td>
        <td align="center" style="mso-number-format:'\#\,\#\#0'; color:red;"><?= $r['keluar'] ?></td>-->
        <td align="center" style="background-color: #fffdf0; font-weight:bold; mso-number-format:'\#\,\#\#0';"><?= $r['stok_akhir'] ?></td>
        <td style="background-color: #ffffff;"></td>
        <td align="center"></td>
    </tr>
    <?php endwhile; ?>
</table>