<?php
session_start();
include '../../config/koneksi.php';

// Proteksi Login
if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// --- KAMUS BARANG ---
$daftar_master = mysqli_query($koneksi, "SELECT nama_barang FROM master_barang WHERE status_aktif='AKTIF' ORDER BY nama_barang ASC");
$kamus_barang = "";
while($m = mysqli_fetch_array($daftar_master)){
    $kamus_barang .= '<option value="'.strtoupper($m['nama_barang']).'">';
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
        :root { --mcp-blue: #0000FF; --mcp-dark: #1a1a1a; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.9rem; }
        input, textarea { text-transform: uppercase; }
        .nav-tabs .nav-link.active { background-color: var(--mcp-blue); color: white; border: none; }
        .nav-tabs .nav-link { color: #555; font-weight: bold; border: none; margin-right: 5px; }
        .modal-xl { max-width: 98%; }
        .bg-waiting { background-color: #fffdf0; }
        .table th { vertical-align: middle; font-size: 12px; }
        @media (min-width: 992px) { .modal-body { max-height: 80vh; overflow-y: auto; } }
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
// Query mengambil kolom nama_pembeli sesuai request Anda
$q_req = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE status_request = 'PENDING' ORDER BY id_request DESC");
while($r = mysqli_fetch_array($q_req)) :
    $pembeli = (!empty($r['nama_pembeli'])) ? strtoupper($r['nama_pembeli']) : "-";
    
    // LOGIKA WARNA PEMBELI
    $badge_color = "bg-light text-muted"; // Warna default jika kosong (-)
    if ($pembeli == "GANG") {
        $badge_color = "bg-danger"; // Merah
    } elseif ($pembeli == "HENDRO") {
        $badge_color = "bg-warning text-dark"; // Kuning (text-dark agar tulisan hitam mudah dibaca)
    } elseif ($pembeli != "-") {
        $badge_color = "bg-info"; // Warna biru untuk staf lain selain Gang/Hendro
    }

    $boleh_beli = true;
    $bg_row = "";
    if($r['kategori_pr'] == 'BESAR' && $r['status_approval'] == 'PENDING') {
        $boleh_beli = false;
        $bg_row = "bg-waiting";
    }
?>
<tr class="<?= $bg_row ?>">
    <td class="ps-3 fw-bold text-primary">
        <?= $r['no_request'] ?><br>
        <span class="badge <?= $r['kategori_pr'] == 'BESAR' ? 'bg-danger' : 'bg-success' ?>" style="font-size: 0.65rem;"><?= $r['kategori_pr'] ?></span>
    </td>
    <td><?= date('d/m/Y', strtotime($r['tgl_request'])) ?></td>
    <td><span class="fw-bold"><?= strtoupper($r['nama_pemesan']) ?></span></td>
    <td>
        <span class="badge <?= $badge_color ?>" style="font-size: 0.85rem; padding: 5px 10px;">
            <i class="fas fa-user-tag me-1"></i><?= $pembeli ?>
        </span>
    </td>
    <td class="text-center">
        <button onclick="viewPR(<?= $r['id_request'] ?>)" class="btn btn-sm btn-info text-white me-1" title="Detail"><i class="fas fa-eye"></i></button>
        <a href="../transaksi/cetak_pr.php?id=<?= $r['id_request'] ?>" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Cetak"><i class="fas fa-print"></i></a>
        <?php if($boleh_beli): ?>
            <button onclick="prosesBeli(<?= $r['id_request'] ?>)" class="btn btn-sm btn-primary px-3 fw-bold shadow-sm"><i class="fas fa-shopping-cart me-1"></i> Beli</button>
        <?php else: ?>
            <button class="btn btn-sm btn-secondary px-3" disabled><i class="fas fa-lock me-1"></i> Lock</button>
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

        <div class="tab-pane fade" id="pembelian-list">
             <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <table id="tabelRealisasi" class="table table-hover table-bordered w-100" style="font-size: 0.75rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Tgl Beli</th><th>No. PR</th><th>Supplier</th><th>Nama Barang</th><th>Qty</th><th>Harga</th><th>Total</th><th>Kategori</th><th>Alokasi</th><th>Keterangan</th><th>Pemesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q = mysqli_query($koneksi, "SELECT * FROM pembelian ORDER BY id_pembelian DESC LIMIT 1000");
                            while($d = mysqli_fetch_array($q)){
                                $total = $d['qty'] * $d['harga'];
                                echo "<tr>
                                    <td>".date('d-m-Y', strtotime($d['tgl_beli']))."</td>
                                    <td>".($d['no_request'] ?? '-')."</td>
                                    <td>".$d['supplier']."</td>
                                    <td>".$d['nama_barang_beli']."</td>
                                    <td class='text-center'>".(float)$d['qty']."</td>
                                    <td class='text-end'>".number_format($d['harga'])."</td>
                                    <td class='text-end fw-bold'>".number_format($total)."</td>
                                    <td>".$d['kategori_beli']."</td>
                                    <td><span class='badge ".($d['alokasi_stok'] == 'MASUK STOK' ? 'bg-info' : 'bg-secondary')."'>".$d['alokasi_stok']."</span></td>
                                    <td>".$d['keterangan']."</td>
                                    <td>".$d['nama_pemesan']."</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <form action="proses_tambah.php" method="POST" id="formBeli">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fw-bold small"><i class="fas fa-shopping-bag me-2"></i>FORM REALISASI PEMBELIAN</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="row g-2 mb-3 bg-light p-2 rounded small">
                        <div class="col-md-3">
                            <label class="fw-bold">PR TERKAIT</label>
                            <select id="pilih_pr" name="id_request" class="form-select form-select-sm border-primary">
                                <option value="">-- BELANJA --</option>
                                <?php
                                $sql_opt = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE status_request = 'PENDING' AND (kategori_pr='KECIL' OR status_approval='APPROVED')");
                                while($opt = mysqli_fetch_array($sql_opt)){
                                    echo "<option value='".$opt['id_request']."'>".$opt['no_request']." (".$opt['nama_pemesan'].")</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold text-muted">USER PEMESAN</label>
                            <input type="text" name="nama_pemesan" id="nama_pemesan" class="form-control form-control-sm bg-white" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold text-primary">STAF PEMBELI (YG BERTUGAS)</label>
                            <input type="text" name="nama_pembeli" id="input_nama_pembeli" class="form-control form-control-sm border-primary fw-bold" placeholder="NAMA STAF" required onkeyup="this.value = this.value.toUpperCase()">
                        </div>
                        
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="tabelBeli">
                            <thead class="table-dark text-center" style="font-size: 0.7rem;">
                                <tr>
                                    <th width="10%">TGL NOTA</th>
                                    <th width="16%">NAMA BARANG</th>
                                    <th width="8%">UNIT</th>
                                    <th width="10%">SUPPLIER</th>
                                    <th width="6%">QTY</th>
                                    <th width="10%">HARGA</th>
                                    <th width="10%">KATEGORI</th>
                                    <th width="10%">ALOKASI</th>
                                    <th width="10%">SUBTOTAL</th>
                                    <th width="10%">KETERANGAN</th>
                                    <th width="2%"></th>
                                </tr>
                            </thead>
                            <tbody id="containerBarang">
                                </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between bg-light">
                    <h3 class="mb-0 fw-bold text-primary" id="grandTotalDisplay">Rp 0</h3>
                    <div>
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">BATAL</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow">SIMPAN REALISASI</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>DETAIL PURCHASE REQUEST</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="kontenView">
                </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// FUNGSI INI DI LUAR READY AGAR BISA DIPANGGIL ONCLICK
function viewPR(id) {
    $('#modalView').modal('show');
    $('#kontenView').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Memuat Data...</div>');
    
    $.ajax({
        url: 'ajax_view_pr.php',
        type: 'GET',
        data: { id: id },
        success: function(res) {
            $('#kontenView').html(res);
        },
        error: function() {
            $('#kontenView').html('<div class="alert alert-danger">Gagal memuat data. Periksa file ajax_view_pr.php</div>');
        }
    });
}
$(document).ready(function() {
    $('#tabelRealisasi').DataTable({ "order": [[0, "desc"]] });

    $(document).on('focus', '.b-tanggal', function() {
        $(this).datepicker({ dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true });
    });

    $(document).on('change', '#pilih_pr', function(){
        let id = $(this).val();
        if(id != "") {
            $.ajax({
                url: 'get_pr_detail.php',
                type: 'GET',
                data: {id: id},
                success: function(html){
                    $("#containerBarang").html(html);
                    hitungSemua();
                }
            });
            // Mengambil data pemesan dan pembeli dari PR
            $.get('get_pr_data.php', {id: id}, function(res){
                if(res.nama_pemesan) { $('#nama_pemesan').val(res.nama_pemesan); }
                if(res.nama_pembeli) { $('#input_nama_pembeli').val(res.nama_pembeli); }
            }, 'json');
        } else {
            $('#nama_pemesan').val("");
            $('#input_nama_pembeli').val("");
            $("#containerBarang").html(""); 
        }
    });

    $(document).on('input', '.b-qty, .b-harga', function(){ hitungSemua(); });
    $(document).on('click', '.remove-baris', function(){ $(this).closest('tr').remove(); hitungSemua(); });
});

function hitungSemua() {
    let grandTotal = 0;
    $('.baris-beli').each(function(){
        let q = parseFloat($(this).find('.b-qty').val()) || 0;
        let h = parseFloat($(this).find('.b-harga').val()) || 0;
        let sub = q * h;
        $(this).find('.b-total').val(sub.toLocaleString('id-ID'));
        grandTotal += sub;
    });
    $('#grandTotalDisplay').text(new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(grandTotal));
}

function prosesBeli(id) {
    $('#formBeli')[0].reset();
    $('#modalTambah').modal('show');
    setTimeout(function(){ $('#pilih_pr').val(id).trigger('change'); }, 300);
}
</script>
</body>
</html>