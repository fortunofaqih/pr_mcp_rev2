<?php
/**
 * pembelian.php
 * Perubahan:
 * - Tombol PR di-lock "Menunggu Verifikasi" HANYA jika SEMUA item
 *   sudah berstatus TERBELI atau MENUNGGU VERIFIKASI
 *   (tidak ada item yang masih PENDING / APPROVED / status lain)
 * - Jika masih ada item PENDING → tombol "Beli" tetap aktif
 */
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] !== 'login') {
    header('Location: ../../login.php?pesan=belum_login');
    exit;
}

// Kamus barang untuk datalist
$daftar_master = mysqli_query($koneksi, "
    SELECT nama_barang FROM master_barang
    WHERE status_aktif = 'AKTIF'
    ORDER BY nama_barang ASC
");
$kamus_barang = '';
while ($m = mysqli_fetch_array($daftar_master)) {
    $kamus_barang .= '<option value="' . strtoupper($m['nama_barang']) . '">';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DASHBOARD PEMBELIAN - MCP</title>
    <link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.9rem; }
        input:not([readonly]):not([type=number]), textarea { text-transform: uppercase; }
        .nav-tabs .nav-link.active { background-color: var(--mcp-blue); color: white; border: none; }
        .nav-tabs .nav-link { color: #555; font-weight: bold; border: none; margin-right: 5px; }
        .modal-xl { max-width: 98%; }
        .table th { vertical-align: middle; font-size: 12px; }
        @media (min-width: 992px) { .modal-body { max-height: 80vh; overflow-y: auto; } }
        .btn-simpan-baris.loading { pointer-events: none; opacity: .7; }
        @keyframes flashGreen { 0% { background-color: #d1fae5; } 100% { background-color: transparent; } }
        tr.saved-flash { animation: flashGreen 1.2s ease; }
    </style>
</head>
<body>

<datalist id="list_barang_master"><?= $kamus_barang ?></datalist>

<nav class="navbar navbar-dark mb-4 shadow-sm" style="background: var(--mcp-blue);">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold"><i class="fas fa-shopping-cart me-2"></i>MODUL PEMBELIAN</span>
        <a href="../../index.php" class="btn btn-danger btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <ul class="nav nav-tabs mb-3 shadow-sm bg-white p-2 rounded-3" id="pembelianTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#request-list">
                <i class="fas fa-clipboard-list me-2"></i>1. ANTREAN REQUEST (PR)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pembelian-list">
                <i class="fas fa-history me-2"></i>2. BUKU REALISASI PEMBELIAN
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- TAB 1: ANTREAN PR -->
        <div class="tab-pane fade show active" id="request-list">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">NO. PR</th>
                                    <th>TANGGAL</th>
                                    <th>PEMESAN</th>
                                    <th>PEMBELI (TUGAS)</th>
                                    <th class="text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                /*
                                 * Logika penentuan status tombol per PR:
                                 *
                                 * $item_pending  = item yang masih bisa dibeli
                                 *                  (status BUKAN TERBELI, BUKAN MENUNGGU VERIFIKASI, BUKAN REJECTED)
                                 *
                                 * $item_menunggu = item sudah di-staging, menunggu verifikasi admin gudang
                                 *
                                 * Aturan tombol:
                                 *   $item_pending > 0                        → tombol BELI aktif (masih ada yg belum diinput)
                                 *   $item_pending = 0 && $item_menunggu > 0  → LOCK "Menunggu Verifikasi"
                                 *   $item_pending = 0 && $item_menunggu = 0  → semua TERBELI → PR selesai (tidak muncul)
                                 */
                                $q_req = mysqli_query($koneksi, "
                                    SELECT
                                        r.*,
                                        (SELECT COUNT(*)
                                         FROM tr_request_detail
                                         WHERE id_request = r.id_request
                                           AND status_item NOT IN ('TERBELI', 'MENUNGGU VERIFIKASI', 'REJECTED')
                                        ) AS item_pending,

                                        (SELECT COUNT(*)
                                         FROM tr_request_detail
                                         WHERE id_request = r.id_request
                                           AND status_item = 'MENUNGGU VERIFIKASI'
                                        ) AS item_menunggu
                                    FROM tr_request r
                                    WHERE r.status_request IN ('PENDING', 'PROSES')
                                    ORDER BY r.id_request DESC
                                ");

                                while ($r = mysqli_fetch_array($q_req)):
                                    $pembeli     = !empty($r['nama_pembeli']) ? strtoupper($r['nama_pembeli']) : '-';
                                    $badge_color = 'bg-light text-muted';
                                    if ($pembeli === 'GANG')       $badge_color = 'bg-danger';
                                    elseif ($pembeli === 'HENDRO') $badge_color = 'bg-warning text-dark';
                                    elseif ($pembeli !== '-')      $badge_color = 'bg-info';

                                    $is_besar    = ($r['kategori_pr'] === 'BESAR');
                                    $is_approved = in_array($r['status_approval'], ['APPROVED', 'DISETUJUI']);
                                    $boleh_beli  = !$is_besar || $is_approved;

                                    $item_pending  = (int) $r['item_pending'];
                                    $item_menunggu = (int) $r['item_menunggu'];

                                    // Lock hanya jika tidak ada item PENDING lagi
                                    $harus_lock_verif = ($item_pending === 0 && $item_menunggu > 0);

                                    // Warna baris
                                    $bg_row = '';
                                    if ($is_besar && !$is_approved) {
                                        $bg_row = 'style="background:#fff3cd;"';
                                    } elseif ($harus_lock_verif) {
                                        $bg_row = 'style="background:#fff0f0;"';  // semua menunggu verifikasi
                                    } elseif ($item_menunggu > 0) {
                                        $bg_row = 'style="background:#fffdf0;"';  // sebagian menunggu, sebagian masih pending
                                    }
                                ?>
                                <tr <?= $bg_row ?>>
                                    <td class="ps-3 fw-bold text-primary">
                                        <?= $r['no_request'] ?><br>
                                        <span class="badge <?= $r['status_request'] === 'PROSES' ? 'bg-warning text-dark' : 'bg-success' ?>" style="font-size:0.65rem;">
                                            <?= $r['status_request'] ?>
                                        </span>
                                        <span class="badge <?= $is_besar ? 'bg-danger' : 'bg-primary' ?>" style="font-size:0.65rem;">
                                            <?= $r['kategori_pr'] ?>
                                        </span>
                                        <?php if ($item_menunggu > 0): ?>
                                            <span class="badge bg-warning text-dark" style="font-size:0.65rem;">
                                                <i class="fas fa-clock me-1"></i><?= $item_menunggu ?> menunggu verifikasi
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($item_pending > 0): ?>
                                            <span class="badge bg-secondary" style="font-size:0.65rem;">
                                                <i class="fas fa-box-open me-1"></i><?= $item_pending ?> belum diinput
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($r['tgl_request'])) ?></td>
                                    <td><span class="fw-bold"><?= strtoupper($r['nama_pemesan']) ?></span></td>
                                    <td>
                                        <span class="badge <?= $badge_color ?>" style="font-size:0.85rem; padding:5px 10px;">
                                            <i class="fas fa-user-tag me-1"></i><?= $pembeli ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button onclick="viewPR(<?= $r['id_request'] ?>)"
                                                class="btn btn-sm btn-info text-white me-1" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="../transaksi/cetak_pr.php?id=<?= $r['id_request'] ?>"
                                           target="_blank" class="btn btn-sm btn-outline-info me-1" title="Cetak">
                                            <i class="fas fa-print"></i>
                                        </a>

                                        <?php if ($harus_lock_verif): ?>
                                            <!-- LOCK: semua item sudah diinput, belum ada yang TERBELI -->
                                            <button class="btn btn-sm btn-warning px-3 fw-bold" disabled
                                                    title="Semua item sudah diinput. Menunggu verifikasi admin gudang (<?= $item_menunggu ?> item).">
                                                <i class="fas fa-hourglass-half me-1"></i>Menunggu Verifikasi
                                            </button>

                                        <?php elseif (!$boleh_beli): ?>
                                            <!-- LOCK: PR besar belum di-approve manager -->
                                            <button class="btn btn-sm btn-secondary px-3" disabled
                                                    title="Menunggu persetujuan manager">
                                                <i class="fas fa-lock me-1"></i>Menunggu Approval
                                            </button>

                                        <?php else: ?>
                                            <!-- AKTIF: masih ada item yang belum diinput -->
                                            <button onclick="prosesBeli(<?= $r['id_request'] ?>)"
                                                    class="btn btn-sm btn-primary px-3 fw-bold shadow-sm"
                                                    title="<?= $item_menunggu > 0 ? "{$item_menunggu} item menunggu verifikasi, {$item_pending} item belum diinput" : '' ?>">
                                                <i class="fas fa-shopping-cart me-1"></i>Beli
                                                <?php if ($item_menunggu > 0 && $item_pending > 0): ?>
                                                    <span class="badge bg-warning text-dark ms-1"
                                                          style="font-size:0.6rem;"><?= $item_pending ?> sisa</span>
                                                <?php endif; ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: BUKU REALISASI -->
        <div class="tab-pane fade" id="pembelian-list">
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <table id="tabelRealisasi" class="table table-hover table-bordered w-100" style="font-size:0.75rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Tgl Beli</th><th>No. PR</th><th>Supplier</th>
                                <th>Nama Barang</th><th>Qty</th><th>Harga</th>
                                <th>Total</th><th>Kategori</th><th>Alokasi</th>
                                <th>Keterangan</th><th>Pemesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q = mysqli_query($koneksi, "SELECT * FROM pembelian ORDER BY id_pembelian DESC LIMIT 1000");
                            while ($d = mysqli_fetch_array($q)):
                                $total  = $d['qty'] * $d['harga'];
                                $al_cls = $d['alokasi_stok'] === 'MASUK STOK' ? 'bg-info' : 'bg-secondary';
                            ?>
                            <tr>
                                <td><?= date('d-m-Y', strtotime($d['tgl_beli'])) ?></td>
                                <td><?= $d['no_request'] ?? '-' ?></td>
                                <td><?= htmlspecialchars($d['supplier']) ?></td>
                                <td><?= htmlspecialchars($d['nama_barang_beli']) ?></td>
                                <td class="text-center"><?= (float) $d['qty'] ?></td>
                                <td class="text-end"><?= number_format($d['harga']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($total) ?></td>
                                <td><?= $d['kategori_beli'] ?></td>
                                <td><span class="badge <?= $al_cls ?>"><?= $d['alokasi_stok'] ?></span></td>
                                <td><?= htmlspecialchars($d['keterangan']) ?></td>
                                <td><?= htmlspecialchars($d['nama_pemesan']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /container -->

<!-- ── MODAL REALISASI PEMBELIAN ─────────────────────────────── -->
<div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold small">
                    <i class="fas fa-shopping-bag me-2"></i>FORM REALISASI PEMBELIAN
                    <span id="labelNoPR" class="ms-2 badge bg-light text-primary"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">

                <div class="row g-2 mb-3 bg-light p-2 rounded small">
                    <div class="col-md-4">
                        <label class="fw-bold text-muted">USER PEMESAN</label>
                        <input type="text" id="info_nama_pemesan" class="form-control form-control-sm bg-white" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="fw-bold text-primary">
                            STAF PEMBELI (YG BERTUGAS) <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="input_nama_pembeli"
                               class="form-control form-control-sm border-primary fw-bold"
                               placeholder="NAMA STAF"
                               onkeyup="this.value=this.value.toUpperCase()">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="alert alert-info mb-0 py-1 px-2 small w-100">
                            <i class="fas fa-info-circle me-1"></i>
                            Alokasi stok ditentukan otomatis dari tipe PR.
                            Simpan setiap baris terpisah.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle" id="tabelBeli">
                        <thead class="table-dark text-center" style="font-size:0.7rem;">
                            <tr>
                                <th>TGL NOTA</th>
                                <th>NAMA BARANG</th>
                                <th>UNIT / MOBIL</th>
                                <th>TOKO/SUPPLIER</th>
                                <th>QTY</th>
                                <th>HARGA</th>
                                <th>KATEGORI PR</th>
                                <th>ALOKASI STOK</th>
                                <th>KATEGORI BARANG</th>
                                <th>SUBTOTAL</th>
                                <th>KETERANGAN</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="containerBarang">
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">
                                    <i class="fas fa-arrow-up me-1"></i>Pilih PR terlebih dahulu
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer d-flex justify-content-between bg-light">
                <div>
                    <span class="text-muted small me-2">Total tersimpan sesi ini:</span>
                    <strong class="text-primary fs-5" id="grandTotalDisplay">Rp 0</strong>
                </div>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>TUTUP
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL VIEW PR ──────────────────────────────────────────── -->
<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>DETAIL PURCHASE REQUEST</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="kontenView"></div>
        </div>
    </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastNotif" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="toastMsg">Berhasil!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- ── SCRIPTS ────────────────────────────────────────────────── -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Global ───────────────────────────────────────────────────
let currentIdRequest = 0;
let grandTotal       = 0;
const toastEl        = document.getElementById('toastNotif');

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const map = { success: 'bg-success', error: 'bg-danger', warning: 'bg-warning text-dark' };
    toastEl.className = 'toast align-items-center text-white border-0 ' + (map[type] ?? 'bg-success');
    document.getElementById('toastMsg').innerText = msg;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();
}

function rupiah(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
}

// ── View PR ──────────────────────────────────────────────────
function viewPR(id) {
    $('#kontenView').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Memuat...</div>');
    $('#modalView').modal('show');
    $.get('ajax_view_pr.php', { id }, function (res) {
        $('#kontenView').html(res);
    }).fail(function (xhr) {
        $('#kontenView').html('<div class="alert alert-danger">Gagal memuat detail PR.</div>');
        console.error(xhr.responseText);
    });
}

// ── Buka Modal Beli ──────────────────────────────────────────
function prosesBeli(id) {
    currentIdRequest = id;
    grandTotal = 0;
    $('#containerBarang').html('<tr><td colspan="12" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat item PR...</td></tr>');
    $('#info_nama_pemesan, #input_nama_pembeli').val('');
    $('#grandTotalDisplay').text('Rp 0');
    $('#labelNoPR').text('');
    $('#modalTambah').modal('show');

    $.get('get_pr_data.php', { id }, function (res) {
        if (!res) return;
        if (res.no_request)   $('#labelNoPR').text(res.no_request);
        if (res.nama_pemesan) $('#info_nama_pemesan').val(res.nama_pemesan);
        if (res.nama_pembeli) $('#input_nama_pembeli').val(res.nama_pembeli);
    }, 'json').fail(() => {});

    $.ajax({
        url    : 'get_pr_detail.php',
        type   : 'GET',
        data   : { id },
        success: html => { $('#containerBarang').html(html); initDatepicker(); },
        error  : ()   => $('#containerBarang').html(
            '<tr><td colspan="12" class="text-center text-danger">Gagal memuat detail PR.</td></tr>'
        )
    });
}

// ── Datepicker ───────────────────────────────────────────────
function initDatepicker() {
    $('.b-tanggal').datepicker({ dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true });
}

// ── Hitung subtotal live ─────────────────────────────────────
$(document).on('input', '.b-qty, .b-harga', function () {
    const $tr = $(this).closest('tr');
    const sub = (parseFloat($tr.find('.b-qty').val()) || 0)
              * (parseFloat($tr.find('.b-harga').val()) || 0);
    $tr.find('.b-total').val(sub.toLocaleString('id-ID'));
});

// ── Simpan per baris ─────────────────────────────────────────
$(document).on('click', '.btn-simpan-baris', function () {
    const $btn = $(this);
    const $tr  = $btn.closest('tr');

    const id_detail       = $tr.find('.f-id-detail').val();
    const id_request_v    = $tr.find('.f-id-request').val()      || currentIdRequest;
    const id_barang       = $tr.find('.f-id-barang').val();
    const nama_pemesan    = $tr.find('.f-nama-pemesan').val()    || $('#info_nama_pemesan').val();
    const kategori_pr     = $tr.find('.f-kategori-pr').val()     || 'KECIL';
    const kategori_barang = $tr.find('.f-kategori-barang').val() || '-';
    const alokasi_info    = $tr.find('.f-alokasi-otomatis').val() || '-';

    const nama_pembeli = $('#input_nama_pembeli').val().trim();
    const tgl_nota     = $tr.find('.b-tanggal').val();
    const nama_barang  = $tr.find('input[list]').val() || '';
    const id_mobil     = $tr.find('.b-id-mobil').val();
    const supplier     = $tr.find('.b-supplier').val().trim();
    const qty          = $tr.find('.b-qty').val();
    const harga        = $tr.find('.b-harga').val();
    const keterangan   = $tr.find('.b-keterangan').val();

    // Validasi
    if (!nama_pembeli) {
        showToast('Isi nama staf pembeli terlebih dahulu!', 'warning');
        $('#input_nama_pembeli').focus(); return;
    }
    if (!supplier) {
        showToast('Nama toko/supplier wajib diisi!', 'warning');
        $tr.find('.b-supplier').focus(); return;
    }
    if (parseFloat(qty) <= 0) {
        showToast('Qty harus lebih dari 0!', 'warning');
        $tr.find('.b-qty').focus(); return;
    }
    if (parseFloat(harga) <= 0) {
        showToast('Harga harus diisi!', 'warning');
        $tr.find('.b-harga').focus(); return;
    }
    if (!keterangan) {
        showToast('Keterangan wajib diisi!', 'warning');
        $tr.find('.b-keterangan').focus(); return;
    }

    const sub_fmt = (parseFloat(qty) * parseFloat(harga)).toLocaleString('id-ID');
    if (!confirm(
        'Simpan item ini?\n\n' +
        nama_barang + '\n' +
        qty + ' × Rp ' + sub_fmt + '\n' +
        'Dari: ' + supplier + '\n' +
        'Alokasi: ' + alokasi_info
    )) return;

    $btn.addClass('loading').html('<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...');

    $.ajax({
        url     : 'proses_simpan_baris.php',
        type    : 'POST',
        dataType: 'json',
        data    : {
            id_detail,
            id_request   : id_request_v,
            id_barang,
            id_mobil,
            nama_pemesan,
            nama_pembeli,
            tgl_nota,
            nama_barang,
            supplier,
            qty,
            harga,
            keterangan,
            kategori_pr,
            kategori_barang,
            // 'alokasi' tidak dikirim — server ambil dari DB
        },
        success: function (res) {
            if (res.status === 'ok') {
                showToast('✅ ' + (res.message || 'Berhasil disimpan!'), 'success');

                grandTotal += (res.subtotal || 0);
                $('#grandTotalDisplay').text(rupiah(grandTotal));
                $tr.addClass('saved-flash');

                buatBarisTerbeli($tr, {
                    tgl_nota,
                    nama_barang,
                    plat_nomor      : res.plat_nomor      || '-',
                    supplier,
                    qty,
                    harga,
                    kategori_pr     : res.kategori_beli   || kategori_pr,
                    alokasi         : res.alokasi,
                    kategori_barang : res.kategori_barang || kategori_barang,
                    subtotal_fmt    : res.subtotal_fmt,
                    keterangan,
                });

                // Cek sisa baris yang belum disimpan di modal ini
                const sisaPending = $('#containerBarang .btn-simpan-baris').length;

                if (res.pr_selesai) {
                    // Semua item TERBELI → PR selesai, hapus dari antrean
                    setTimeout(function () {
                        showToast('🎉 Semua item PR sudah terbeli! PR otomatis SELESAI.', 'success');
                        hapusBarisPR(currentIdRequest);
                    }, 1000);

                } else if (sisaPending === 0 && res.semua_diinput) {
                    // Semua item sudah di-staging, tidak ada lagi yang pending
                    // → lock tombol Beli di antrean → Menunggu Verifikasi
                    setTimeout(function () {
                        showToast('⏳ Semua item sudah diinput. Menunggu verifikasi admin gudang.', 'warning');
                        lockTombolPR(currentIdRequest, res.item_menunggu ?? 0);
                    }, 800);
                }
                // Jika sisaPending > 0, tombol Beli di antrean tetap aktif — tidak ada perubahan

            } else {
                showToast('❌ ' + (res.message || 'Gagal menyimpan.'), 'error');
                $btn.removeClass('loading').html('<i class="fas fa-save me-1"></i>Simpan');
            }
        },
        error: function (xhr) {
            showToast('❌ Terjadi kesalahan server. Coba lagi.', 'error');
            console.error(xhr.responseText);
            $btn.removeClass('loading').html('<i class="fas fa-save me-1"></i>Simpan');
        }
    });
});

// ── Ubah baris → read-only setelah tersimpan ─────────────────
function buatBarisTerbeli($tr, d) {
    const alokasiClass = (d.alokasi === 'MASUK STOK') ? 'bg-info text-dark' : 'bg-secondary';
    const katPRClass   = (d.kategori_pr === 'BESAR')  ? 'bg-danger'         : 'bg-success';
    const platBadge    = (d.plat_nomor && d.plat_nomor !== '-')
        ? `<span class="badge bg-primary small">${d.plat_nomor}</span>`
        : '<span class="text-muted small">-</span>';

    $tr.removeClass('baris-beli')
       .addClass('table-success opacity-75')
       .html(`
        <td class="text-center small">${d.tgl_nota}</td>
        <td><strong>${d.nama_barang}</strong></td>
        <td class="text-center">${platBadge}</td>
        <td class="small">${d.supplier}</td>
        <td class="text-center">${parseFloat(d.qty)}</td>
        <td class="text-end small">${Number(d.harga).toLocaleString('id-ID')}</td>
        <td class="text-center"><span class="badge ${katPRClass} small">${d.kategori_pr}</span></td>
        <td class="text-center"><span class="badge ${alokasiClass} small">${d.alokasi}</span></td>
        <td class="text-center small text-muted">${d.kategori_barang}</td>
        <td class="text-end fw-bold text-success small">${d.subtotal_fmt}</td>
        <td class="text-muted small">${d.keterangan}</td>
        <td class="text-center">
            <span class="badge bg-warning text-dark">
                <i class="fas fa-hourglass-half me-1"></i>MENUNGGU VERIFIKASI
            </span>
        </td>
    `);
}

// ── Hapus baris PR dari antrean (semua terbeli) ───────────────
function hapusBarisPR(idRequest) {
    $('#request-list tbody tr').filter(function () {
        return $(this).find(`button[onclick="prosesBeli(${idRequest})"]`).length > 0;
    }).fadeOut(600, function () { $(this).remove(); });
}

// ── Lock tombol Beli → Menunggu Verifikasi ────────────────────
// Dipanggil hanya ketika semua item sudah diinput (sisaPending === 0)
function lockTombolPR(idRequest, jumlahMenunggu) {
    $('#request-list tbody tr').each(function () {
        const $row = $(this);
        if ($row.find(`button[onclick="prosesBeli(${idRequest})"]`).length > 0) {
            $row.find(`button[onclick="prosesBeli(${idRequest})"]`)
                .replaceWith(`
                    <button class="btn btn-sm btn-warning px-3 fw-bold" disabled
                            title="Semua item sudah diinput. Menunggu verifikasi admin gudang (${jumlahMenunggu} item).">
                        <i class="fas fa-hourglass-half me-1"></i>Menunggu Verifikasi
                    </button>
                `);
            $row.attr('style', 'background:#fff0f0;');
        }
    });
}

// ── DataTables ───────────────────────────────────────────────
$(document).ready(function () {
    $('#tabelRealisasi').DataTable({
        order   : [[0, 'desc']],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
    });
});
</script>
</body>
</html>