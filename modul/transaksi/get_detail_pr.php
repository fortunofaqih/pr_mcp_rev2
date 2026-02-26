<?php
include '../../config/koneksi.php';

if (!isset($_GET['id'])) {
    exit("<div class='p-4 text-center text-danger'>ID tidak ditemukan.</div>");
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// 1. Ambil data header
$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_header);

if (!$h) {
    echo "<div class='p-4 text-center text-danger'>Data tidak ditemukan.</div>";
    exit;
}
?>

<div class="p-3 bg-light border-bottom">
    <div class="row small fw-bold text-uppercase">
        <div class="col-md-4">
            <span class="text-muted d-block" style="font-size: 10px;">No. Request:</span>
            <span class="text-primary" style="font-size: 14px;"><?= $h['no_request'] ?></span>
        </div>
        <div class="col-md-4 text-center border-start border-end">
            <span class="text-muted d-block" style="font-size: 10px;">Pemesan:</span>
            <span><?= strtoupper($h['nama_pemesan']) ?></span>
        </div>
        <div class="col-md-4 text-end">
            <span class="text-muted d-block" style="font-size: 10px;">Tanggal:</span>
            <span><?= date('d/m/Y', strtotime($h['tgl_request'])) ?></span>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover mb-0" style="font-size: 0.8rem;">
        <thead class="table-dark text-uppercase" style="font-size: 0.7rem;">
            <tr>
                <th class="text-center" width="40">NO</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th class="text-center">Unit/Mobil</th>
                <th class="text-center">Tipe</th>
                <th class="text-center">Qty</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            
            // Query JOIN ke master_barang dan master_mobil
            // Pastikan alias m.plat_nomor benar-benar terambil
            $sql_detail = "SELECT d.*, m.plat_nomor, b.nama_barang as nama_barang_master
                           FROM tr_request_detail d
                           LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil
                           LEFT JOIN master_barang b ON d.id_barang = b.id_barang
                           WHERE d.id_request = '$id' 
                           ORDER BY d.id_detail ASC";
            
            $query_detail = mysqli_query($koneksi, $sql_detail);

            if(mysqli_num_rows($query_detail) == 0) {
                echo '<tr><td colspan="7" class="text-center py-3">Tidak ada detail item.</td></tr>';
            }

            while($d = mysqli_fetch_array($query_detail)) {
                // LOGIKA NAMA BARANG: Utamakan nama dari master jika tersedia
                $nama_tampil = !empty($d['nama_barang_master']) ? $d['nama_barang_master'] : $d['nama_barang_manual'];

                // LOGIKA UNIT: Langsung cek plat_nomor hasil JOIN
                // Jika plat_nomor tidak kosong (berhasil join), maka tampilkan
                $unit_tampil = (!empty($d['plat_nomor'])) ? $d['plat_nomor'] : "-";
            ?>
            <tr>
                <td class="text-center text-muted"><?= $no++ ?></td>
                <td class="fw-bold text-dark"><?= strtoupper($nama_tampil) ?></td>
                <td><small><?= strtoupper($d['kategori_barang']) ?></small></td>
                <td class="text-center">
                    <?php if($unit_tampil != "-"): ?>
                        <span class="badge bg-light text-dark border"><?= $unit_tampil ?></span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge <?= $d['tipe_request'] == 'LANGSUNG' ? 'bg-outline-danger text-danger' : 'bg-outline-primary text-primary' ?> border" style="font-size: 10px;">
                        <?= $d['tipe_request'] ?>
                    </span>
                </td>
                <td class="text-center fw-bold">
                    <?= (float)$d['jumlah'] ?> <small class="text-muted"><?= $d['satuan'] ?></small>
                </td>
                <td><small><?= $d['keterangan'] ?: '-' ?></small></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div class="p-2 bg-white text-end border-top">
    <small class="text-muted italic">* Tampilan ini adalah ringkasan item Purchase Request tanpa menampilkan estimasi harga.</small>
</div>