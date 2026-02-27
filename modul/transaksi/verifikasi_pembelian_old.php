<?php
/**
 * verifikasi_pembelian.php
 * Halaman khusus admin gudang untuk verifikasi data pembelian staging
 * Fitur: lihat detail, edit semua field, approve, tolak
 */
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] != 'login') {
    header("location:../../login.php?pesan=belum_login"); exit;
}

// Hitung total menunggu untuk info
$q_count = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pembelian_staging WHERE status_staging='MENUNGGU'");
$total_menunggu = mysqli_fetch_assoc($q_count)['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VERIFIKASI PEMBELIAN - ADMIN GUDANG</title>
    <link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        :root { --gudang-green: #198754; }
        body { background:#f0f4f8; font-family:'Inter',sans-serif; font-size:0.9rem; }
        input:not([readonly]):not([type=number]), textarea { text-transform:uppercase; }
        .table th { vertical-align:middle; font-size:12px; }
        .modal-xl { max-width:96%; }
        @media(min-width:992px){ .modal-body{ max-height:80vh; overflow-y:auto; } }
        .badge-menunggu { background:#ffc107; color:#000; }
        .card-stat { border-left:4px solid var(--gudang-green); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark mb-4 shadow-sm" style="background:var(--gudang-green);">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold">
            <i class="fas fa-clipboard-check me-2"></i>VERIFIKASI PEMBELIAN — ADMIN GUDANG
        </span>
        <a href="../../index.php" class="btn btn-danger btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">

    <!-- Stat Card -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm card-stat">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">MENUNGGU VERIFIKASI</div>
                            <div class="fs-3 fw-bold text-warning" id="counterMenunggu"><?= $total_menunggu ?></div>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3 shadow-sm bg-white p-2 rounded-3">
        <li class="nav-item">
            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-menunggu">
                <i class="fas fa-hourglass-half me-1 text-warning"></i>MENUNGGU
                <span class="badge bg-warning text-dark ms-1" id="badgeMenunggu"><?= $total_menunggu ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-riwayat">
                <i class="fas fa-history me-1"></i>RIWAYAT VERIFIKASI
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- TAB MENUNGGU -->
        <div class="tab-pane fade show active" id="tab-menunggu">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabelMenunggu">
                            <thead class="table-warning">
                                <tr>
                                    <th class="ps-3">NO. PR</th>
                                    <th>TGL NOTA</th>
                                    <th>NAMA BARANG</th>
                                    <th>TOKO</th>
                                    <th class="text-center">QTY</th>
                                    <th class="text-end">HARGA</th>
                                    <th class="text-end fw-bold">SUBTOTAL</th>
                                    <th>ALOKASI</th>
                                    <th>PETUGAS</th>
                                    <th class="text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_stg = mysqli_query($koneksi, "
                                    SELECT s.*, r.nama_pemesan AS pemesan_pr
                                    FROM pembelian_staging s
                                    LEFT JOIN tr_request r ON r.id_request = s.id_request
                                    WHERE s.status_staging = 'MENUNGGU'
                                    ORDER BY s.created_at ASC
                                ");
                                while ($s = mysqli_fetch_assoc($q_stg)):
                                    $subtotal = $s['qty'] * $s['harga'];
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary small"><?= $s['no_request'] ?? '-' ?></td>
                                    <td class="small"><?= date('d/m/Y', strtotime($s['tgl_beli_barang'])) ?></td>
                                    <td class="fw-bold"><?= $s['nama_barang_beli'] ?></td>
                                    <td class="small"><?= $s['supplier'] ?></td>
                                    <td class="text-center"><?= (float)$s['qty'] ?></td>
                                    <td class="text-end small"><?= number_format($s['harga']) ?></td>
                                    <td class="text-end fw-bold text-primary"><?= number_format($subtotal) ?></td>
                                    <td>
                                        <span class="badge <?= $s['alokasi_stok']=='MASUK STOK'?'bg-info':'bg-secondary' ?> small">
                                            <?= $s['alokasi_stok'] ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= $s['driver'] ?></td>
                                    <td class="text-center">
                                        <button onclick="bukaVerifikasi(<?= $s['id_staging'] ?>)"
                                                class="btn btn-sm btn-success fw-bold px-3">
                                            <i class="fas fa-check-double me-1"></i>Verifikasi
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

       <!-- TAB RIWAYAT -->
        <div class="tab-pane fade" id="tab-riwayat">
            <div class="card border-0 shadow-sm mt-2">
                <div class="card-body">
                    <table id="tabelRiwayat" class="table table-hover table-bordered w-100" style="font-size:0.75rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Tgl Verifikasi</th><th>No. PR</th><th>Barang</th>
                                <th>Toko</th><th>Qty</th><th>Harga</th><th>Total</th>
                                <th>Alokasi</th><th>Status</th><th>Catatan</th><th>Verifikator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // JOIN ke pembelian agar data yang tampil adalah data SETELAH diedit saat verifikasi
                            $q_riwayat = mysqli_query($koneksi, "
                                SELECT 
                                    s.id_staging,
                                    s.no_request,
                                    s.status_staging,
                                    s.catatan_verifikasi,
                                    s.verified_by,
                                    s.verified_at,
                                    -- Ambil data aktual dari tabel pembelian jika DISETUJUI
                                    COALESCE(p.tgl_beli, s.tgl_beli_barang)       AS tgl_final,
                                    COALESCE(p.nama_barang_beli, s.nama_barang_beli) AS nama_final,
                                    COALESCE(p.supplier, s.supplier)               AS supplier_final,
                                    COALESCE(p.qty, s.qty)                         AS qty_final,
                                    COALESCE(p.harga, s.harga)                     AS harga_final,
                                    COALESCE(p.alokasi_stok, s.alokasi_stok)       AS alokasi_final
                                FROM pembelian_staging s
                                LEFT JOIN pembelian p ON p.id_request_detail = s.id_request_detail
                                                      AND p.no_request = s.no_request
                                WHERE s.status_staging IN ('DISETUJUI','DITOLAK')
                                ORDER BY s.verified_at DESC 
                                LIMIT 500
                            ");
                            while ($rv = mysqli_fetch_assoc($q_riwayat)):
                                $total = $rv['qty_final'] * $rv['harga_final'];
                                $badge = $rv['status_staging'] == 'DISETUJUI' ? 'bg-success' : 'bg-danger';
                            ?>
                            <tr>
                                <td><?= $rv['verified_at'] ? date('d/m/Y H:i', strtotime($rv['verified_at'])) : '-' ?></td>
                                <td><?= $rv['no_request'] ?? '-' ?></td>
                                <td class="fw-bold"><?= $rv['nama_final'] ?></td>
                                <td><?= $rv['supplier_final'] ?></td>
                                <td class="text-center"><?= (float)$rv['qty_final'] ?></td>
                                <td class="text-end"><?= number_format($rv['harga_final']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($total) ?></td>
                                <td>
                                    <span class="badge <?= $rv['alokasi_final']=='MASUK STOK'?'bg-info':'bg-secondary' ?> small">
                                        <?= $rv['alokasi_final'] ?>
                                    </span>
                                </td>
                                <td><span class="badge <?= $badge ?>"><?= $rv['status_staging'] ?></span></td>
                                <td class="text-muted small"><?= $rv['catatan_verifikasi'] ?? '-' ?></td>
                                <td class="small"><?= $rv['verified_by'] ?? '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL VERIFIKASI -->
<div class="modal fade" id="modalVerifikasi" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header py-2" style="background:var(--gudang-green);">
                <h5 class="modal-title text-white fw-bold small">
                    <i class="fas fa-clipboard-check me-2"></i>FORM VERIFIKASI PEMBELIAN
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="kontenVerifikasi">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastNotif" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="toastMsg">OK</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const toastEl = document.getElementById('toastNotif');

function showToast(msg, type='success') {
    const map = {success:'bg-success', error:'bg-danger', warning:'bg-warning text-dark'};
    toastEl.className = 'toast align-items-center text-white border-0 ' + (map[type]||'bg-success');
    document.getElementById('toastMsg').innerText = msg;
    bootstrap.Toast.getOrCreateInstance(toastEl, {delay:3500}).show();
}

function bukaVerifikasi(id_staging) {
    $('#kontenVerifikasi').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Memuat data...</div>');
    $('#modalVerifikasi').modal('show');
    $.get('ajax_form_verifikasi.php', {id: id_staging}, function(html){
        $('#kontenVerifikasi').html(html);
        // Init datepicker di dalam modal
        $('#modal_tgl_nota').datepicker({dateFormat:'dd-mm-yy', changeMonth:true, changeYear:true});
        // Hitung subtotal awal
        hitungSubtotal();
    }).fail(function(){
        $('#kontenVerifikasi').html('<div class="alert alert-danger">Gagal memuat form verifikasi.</div>');
    });
}

function hitungSubtotal() {
    const qty   = parseFloat($('#modal_qty').val())   || 0;
    const harga = parseFloat($('#modal_harga').val()) || 0;
    const sub   = qty * harga;
    $('#modal_subtotal').text(new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(sub));
}

$(document).off('input', '#modal_qty, #modal_harga').on('input', '#modal_qty, #modal_harga', hitungSubtotal);

// APPROVE
$(document).off('click', '#btnApprove').on('click', '#btnApprove', function() {
    const id = $(this).data('id');
    if (!confirm('Setujui dan simpan data pembelian ini ke buku realisasi?')) return;

    const payload = {
        id_staging : id,
        aksi       : 'APPROVE',
        tgl_nota   : $('#modal_tgl_nota').val(),
        supplier   : $('#modal_supplier').val(),
        nama_barang: $('#modal_nama_barang').val(),
        qty        : $('#modal_qty').val(),
        harga      : $('#modal_harga').val(),
        alokasi    : $('#modal_alokasi').val(),
        keterangan : $('#modal_keterangan').val(),
        catatan    : $('#modal_catatan').val(),
        id_mobil   : $('#modal_id_mobil').val(),
    };

    kirimVerifikasi(payload);
});

// TOLAK
$(document).off('click', '#btnTolak').on('click', '#btnTolak', function() {
    const id      = $(this).data('id');
    const catatan = $('#modal_catatan').val().trim();
    if (!catatan) {
        showToast('Isi catatan alasan penolakan terlebih dahulu!', 'warning');
        $('#modal_catatan').focus(); return;
    }
    if (!confirm('Tolak data ini? Item akan kembali ke antrean PR petugas pembelian.')) return;

    kirimVerifikasi({ id_staging: id, aksi: 'TOLAK', catatan });
});

function kirimVerifikasi(payload) {
    $.ajax({
        url: 'proses_verifikasi.php',
        type: 'POST',
        dataType: 'json',
        data: payload,
        success: function(res) {
            if (res.status === 'ok') {
                showToast((res.aksi === 'APPROVE' ? '✅ ' : '❌ ') + res.message, res.aksi === 'APPROVE' ? 'success' : 'warning');
                $('#modalVerifikasi').modal('hide');

                // Hapus baris dari tabel menunggu
                $('#tabelMenunggu tbody tr').filter(function(){
                    return $(this).find('button[onclick="bukaVerifikasi('+payload.id_staging+')"]').length > 0;
                }).fadeOut(400, function(){ $(this).remove(); updateCounter(); });

            } else {
                showToast('❌ ' + res.message, 'error');
            }
        },
        error: function(){ showToast('❌ Terjadi kesalahan server.', 'error'); }
    });
}

function updateCounter() {
    const sisa = $('#tabelMenunggu tbody tr:visible').length;
    $('#counterMenunggu, #badgeMenunggu').text(sisa);
}

$(document).ready(function(){
    $('#tabelRiwayat').DataTable({order:[[0,'desc']], language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'}});
});
</script>
</body>
</html>