<?php
include '../../config/koneksi.php';
$id = mysqli_real_escape_string($koneksi, $_GET['id']);

$q = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($q);
?>
<style>
    .table-preview { border-collapse: collapse; font-size: 0.8rem; }
    .table-preview th, .table-preview td { 
        border: 1px solid #dee2e6; 
        padding: 6px 8px; 
        vertical-align: top; 
    }
    .table-preview thead tr { background: #00008B; color: white; }
    .table-preview tbody tr:nth-child(even) { background: #f8f9fa; }
    /* Pastikan kolom keterangan tidak terpotong */
    .table-preview td:nth-child(4) { 
        min-width: 150px; 
        white-space: pre-wrap; 
        word-break: break-word; 
    }
</style>

<div class="preview-pr-container shadow-sm p-4 bg-white rounded">
    <div class="text-center mb-4">
        <h5 class="mb-0 fw-bold">PURCHASE REQUEST FORM</h5>
        <p class="text-secondary small mb-2">PT. MUTIARA CAHAYA PLASTINDO</p>
        <div style="border-bottom: 2px double #dee2e6; width: 100%;"></div>
    </div>
    
    <div class="row mb-3 fw-bold small">
        <div class="col-md-3">NO: <span class="text-primary"><?= $h['no_request'] ?></span></div>
        <div class="col-md-3">PEMESAN: <span class="text-dark"><?= strtoupper($h['nama_pemesan']) ?></span></div>
        <div class="col-md-3 text-center">PEMBELI: <span class="badge bg-info"><?= $h['nama_pembeli'] ?: '-' ?></span></div>
        <div class="col-md-3 text-end">TGL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></div>
    </div>

    <!-- Keterangan PR level header jika ada -->
    <?php if(!empty($h['keterangan'])): ?>
    <div class="alert alert-info py-2 small mb-3">
        <i class="fas fa-info-circle me-1"></i> <strong>Catatan PR:</strong> <?= $h['keterangan'] ?>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table-preview w-100">
            <thead>
                <tr>
                    <th width="4%">NO</th>
                    <th width="30%">NAMA BARANG / SPEK</th>
                    <th width="12%">UNIT/MOBIL</th>
                    <th width="28%">KETERANGAN</th>  
                    <th width="10%">QTY</th>
                    <th width="8%">TIPE</th>
                    <th width="8%">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1; 
                $det = mysqli_query($koneksi, "SELECT d.*, m.plat_nomor 
                                               FROM tr_request_detail d 
                                               LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil 
                                               WHERE d.id_request = '$id'");
                
                while($d = mysqli_fetch_array($det)){
                    $status_item = $d['status_item'];
                    $badge_status = match($status_item) {
                        'REJECTED'           => "<span class='badge bg-danger'>DITOLAK</span>",
                        'TERBELI'            => "<span class='badge bg-primary'>TERBELI</span>",
                        'APPROVED'           => "<span class='badge bg-success'>DISETUJUI</span>",
                        'MENUNGGU VERIFIKASI'=> "<span class='badge bg-warning text-dark'>MENUNGGU VER.</span>",
                        default              => "<span class='badge bg-warning text-dark'>PENDING</span>"
                    };
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <div class="fw-bold"><?= strtoupper($d['nama_barang_manual']) ?></div>
                        <?php if(!empty($d['kwalifikasi'])): ?>
                        <small class="text-muted"><?= $d['kwalifikasi'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center small"><?= ($d['plat_nomor'] ?: '-') ?></td>
                    <td class="small">
                        <?php if(!empty($d['keterangan'])): ?>
                            <?= nl2br(htmlspecialchars($d['keterangan'])) ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-bold"><?= $d['jumlah'] ?> <?= $d['satuan'] ?></td>
                    <td class="text-center">
                        <span class="badge <?= $d['tipe_request']=='LANGSUNG'?'bg-danger':'bg-info' ?> small">
                            <?= $d['tipe_request'] ?>
                        </span>
                    </td>
                    <td class="text-center" style="font-size:0.7rem;"><?= $badge_status ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>