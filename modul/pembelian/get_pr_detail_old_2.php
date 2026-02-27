<?php
/**
 * get_pr_detail.php
 * Dipanggil via AJAX dari index.php
 * Perubahan v2:
 * - Kolom UNIT → dropdown master_mobil (plat_nomor), default "-" jika bukan kendaraan
 * - Kolom KATEGORI → read-only dari tr_request.kategori_pr (KECIL/BESAR)
 * - Kolom ALOKASI BARANG → tambahan kategori_barang dari tr_request_detail
 * Total kolom tabel: 12 kolom + 1 aksi = 13
 */
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<tr><td colspan="13" class="text-center text-danger">ID tidak valid.</td></tr>';
    exit;
}

$id_request = (int)$_GET['id'];

// Header PR
$q_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = $id_request LIMIT 1");
$header   = mysqli_fetch_assoc($q_header);
if (!$header) {
    echo '<tr><td colspan="13" class="text-center text-danger">PR tidak ditemukan.</td></tr>';
    exit;
}
$kategori_pr = strtoupper($header['kategori_pr'] ?? 'KECIL');

// Daftar mobil aktif untuk dropdown
$q_mobil    = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor, jenis_kendaraan FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
$list_mobil = [];
while ($m = mysqli_fetch_assoc($q_mobil)) {
    $list_mobil[] = $m;
}

// Item detail PR
$q_detail = mysqli_query($koneksi, "
    SELECT d.*, mb.nama_barang AS nama_barang_master
    FROM tr_request_detail d
    LEFT JOIN master_barang mb ON mb.id_barang = d.id_barang
    WHERE d.id_request = $id_request
      AND d.status_item != 'REJECTED'
    ORDER BY d.id_detail ASC
");

if (mysqli_num_rows($q_detail) == 0) {
    echo '<tr><td colspan="13" class="text-center text-muted">Tidak ada item pada PR ini.</td></tr>';
    exit;
}

$today = date('d-m-Y');

while ($d = mysqli_fetch_assoc($q_detail)):
    $nama_barang     = !empty($d['nama_barang_manual']) ? strtoupper($d['nama_barang_manual']) : strtoupper($d['nama_barang_master'] ?? '-');
    $kategori_barang = strtoupper($d['kategori_barang'] ?? '-');
    $is_terbeli      = ($d['status_item'] === 'TERBELI');
    $id_mobil_pr     = (int)($d['id_mobil'] ?? 0);

    // Data pembelian sebelumnya (jika sudah terbeli)
    $data_beli = null;
    if ($is_terbeli) {
        $qb        = mysqli_query($koneksi, "SELECT * FROM pembelian WHERE id_request_detail = {$d['id_detail']} ORDER BY id_pembelian DESC LIMIT 1");
        $data_beli = mysqli_fetch_assoc($qb);
    }

    $row_class = $is_terbeli ? 'table-success opacity-75' : 'baris-beli';
?>
<tr class="<?= $row_class ?>" data-id-detail="<?= $d['id_detail'] ?>">

<?php if ($is_terbeli): ?>
<!-- ======================================================= -->
<!-- READ-ONLY: SUDAH TERBELI                                -->
<!-- ======================================================= -->
    <td class="text-center small"><?= $data_beli ? date('d-m-Y', strtotime($data_beli['tgl_beli_barang'])) : '-' ?></td>
    <td><strong><?= $nama_barang ?></strong><br><small class="text-muted"><?= $d['satuan'] ?></small></td>

    <!-- Unit/Mobil -->
    <td class="text-center">
        <?php
        $plat_beli = $data_beli['plat_nomor'] ?? '-';
        if ($plat_beli && $plat_beli !== '-') {
            echo '<span class="badge bg-primary small">'.$plat_beli.'</span>';
        } else {
            echo '<span class="text-muted small">-</span>';
        }
        ?>
    </td>

    <td class="small"><?= $data_beli ? strtoupper($data_beli['supplier']) : '-' ?></td>
    <td class="text-center"><?= $data_beli ? (float)$data_beli['qty'] : (float)$d['jumlah'] ?></td>
    <td class="text-end small"><?= $data_beli ? number_format($data_beli['harga']) : '-' ?></td>

    <!-- Kategori PR -->
    <td class="text-center">
        <span class="badge <?= $kategori_pr == 'BESAR' ? 'bg-danger' : 'bg-success' ?> small"><?= $kategori_pr ?></span>
    </td>

    <!-- Alokasi Stok -->
    <td class="text-center">
        <?php if ($data_beli): ?>
            <span class="badge <?= $data_beli['alokasi_stok'] == 'MASUK STOK' ? 'bg-info' : 'bg-secondary' ?> small">
                <?= $data_beli['alokasi_stok'] ?>
            </span>
        <?php else: echo '-'; endif; ?>
    </td>

    <!-- Kategori Barang -->
    <td class="text-center small text-muted"><?= $kategori_barang ?></td>

    <td class="text-end fw-bold text-success small">
        <?= $data_beli ? number_format((float)$data_beli['qty'] * (float)$data_beli['harga']) : '-' ?>
    </td>
    <td class="text-muted small"><?= $data_beli ? $data_beli['keterangan'] : '-' ?></td>
    <td class="text-center">
        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>SUDAH DIBELI</span>
    </td>

<?php else: ?>
<!-- ======================================================= -->
<!-- FORM AKTIF: BELUM TERBELI                               -->
<!-- ======================================================= -->
    <!-- Hidden fields -->
    <input type="hidden" class="f-id-detail"      value="<?= $d['id_detail'] ?>">
    <input type="hidden" class="f-id-barang"       value="<?= $d['id_barang'] ?? '' ?>">
    <input type="hidden" class="f-id-request"      value="<?= $id_request ?>">
    <input type="hidden" class="f-nama-pemesan"    value="<?= strtoupper($header['nama_pemesan']) ?>">
    <input type="hidden" class="f-kategori-pr"     value="<?= $kategori_pr ?>">
    <input type="hidden" class="f-kategori-barang" value="<?= $kategori_barang ?>">

    <!-- TGL NOTA -->
    <td>
        <input type="text"
               class="form-control form-control-sm b-tanggal text-center"
               value="<?= $today ?>"
               placeholder="dd-mm-yyyy"
               style="min-width:110px; text-transform:none;">
    </td>

    <!-- NAMA BARANG (readonly, dari PR) -->
    <td>
        <input type="text"
               class="form-control form-control-sm fw-bold"
               value="<?= $nama_barang ?>"
               list="list_barang_master"
               readonly
               style="min-width:140px;">
        <small class="text-muted">Qty PR: <?= (float)$d['jumlah'] ?> <?= $d['satuan'] ?></small>
    </td>

    <!-- UNIT / MOBIL / PLAT NOMOR -->
    <td>
        <select class="form-select form-select-sm b-id-mobil" style="min-width:130px;">
            <option value="0" data-plat="-">- BUKAN KENDARAAN -</option>
            <?php foreach ($list_mobil as $mob): ?>
                <option value="<?= $mob['id_mobil'] ?>"
                        data-plat="<?= strtoupper($mob['plat_nomor']) ?>"
                        <?= ($id_mobil_pr > 0 && $id_mobil_pr == $mob['id_mobil']) ? 'selected' : '' ?>>
                    <?= strtoupper($mob['plat_nomor']) ?>
                    <?= !empty($mob['jenis_kendaraan']) ? ' — '.strtoupper($mob['jenis_kendaraan']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>

    <!-- TOKO / SUPPLIER -->
    <td>
        <input type="text"
               class="form-control form-control-sm b-supplier"
               placeholder="NAMA TOKO"
               style="min-width:110px;">
    </td>

    <!-- QTY BELI -->
    <td>
        <input type="number"
               class="form-control form-control-sm text-center b-qty"
               value="<?= (float)$d['jumlah'] ?>"
               min="0.01" step="0.01"
               style="min-width:65px;">
    </td>

    <!-- HARGA SATUAN -->
    <td>
        <input type="number"
               class="form-control form-control-sm text-end b-harga"
               value="0"
               min="0" step="1"
               style="min-width:100px;">
    </td>

    <!-- KATEGORI PR (read-only badge) -->
    <td class="text-center">
        <span class="badge <?= $kategori_pr == 'BESAR' ? 'bg-danger' : 'bg-success' ?>">
            <?= $kategori_pr ?>
        </span>
    </td>

    <!-- ALOKASI STOK -->
    <td>
        <select class="form-select form-select-sm b-alokasi" style="min-width:110px;">
            <option value="LANGSUNG PAKAI">LANGSUNG PAKAI</option>
            <option value="MASUK STOK">MASUK STOK</option>
        </select>
    </td>

    <!-- KATEGORI BARANG (dari tr_request_detail, read-only badge) -->
    <td class="text-center">
        <span class="badge bg-secondary small px-2"><?= $kategori_barang ?></span>
    </td>

    <!-- SUBTOTAL -->
    <td>
        <input type="text"
               class="form-control form-control-sm text-end fw-bold b-total"
               value="0"
               readonly
               style="min-width:100px; background:#fffde7;">
    </td>

    <!-- KETERANGAN -->
   <!-- KETERANGAN -->
        <td>
            <input type="text"
                class="form-control form-control-sm b-keterangan"
                placeholder="Keperluan"
                style="min-width:100px;"
                value="<?= strtoupper($d['keterangan'] ?? '') ?>" 
                required>
        </td>

    <!-- TOMBOL SIMPAN PER BARIS -->
    <td class="text-center">
        <button type="button"
                class="btn btn-success btn-sm fw-bold btn-simpan-baris px-2"
                title="Simpan baris ini"
                style="white-space:nowrap;">
            <i class="fas fa-save me-1"></i>Simpan
        </button>
    </td>

<?php endif; ?>
</tr>
<?php endwhile; ?>