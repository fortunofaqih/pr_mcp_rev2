<?php
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request (PR) - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
        .btn-mcp { background: var(--mcp-blue); color: white; border-radius: 8px; font-weight: bold; }
        .btn-mcp:hover { background: var(--mcp-dark); color: white; }
        .status-badge { font-size: 0.75rem; font-weight: 700; padding: 6px 15px; border-radius: 20px; text-transform: uppercase; }
        .card-stats { border: none; border-radius: 10px; transition: transform 0.2s; }
        .card-stats:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-file-invoice me-2"></i> PURCHASE REQUEST SYSTEM</span>
        <div>
            <a href="../../index.php" class="btn btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
            <a href="tambah_request.php" class="btn btn-sm btn-warning fw-bold"><i class="fas fa-plus-circle"></i> BUAT REQUEST BARU (BARANG KECIL)</a>
            <button type="button" class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalCetakTanggal">
                <i class="fas fa-print"></i> CETAK PER TANGGAL
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <?php
        $count_pending = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request WHERE status_request='PENDING'"));
       $count_proses = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request WHERE status_request='SELESAI'"));
        $count_total = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request"));
         // Hitung Persentase
        $persen_selesai = ($count_total > 0) ? round(($count_proses / $count_total) * 100, 1) : 0;
        
        ?>
       
        <div class="col-md-3 col-6">
            <div class="card card-stats shadow-sm border-start border-warning border-4">
                <div class="card-body">
                    <div class="text-muted small fw-bold">ON PROGRESS</div>
                    <h3 class="fw-bold mb-0"><?= $count_pending ?> <small class="text-muted fs-6">PR</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-stats shadow-sm border-start border-primary border-4">
                <div class="card-body">
                    <div class="text-muted small fw-bold">DONE</div>
                    <h3 class="fw-bold mb-0"><?= $count_proses ?> <small class="text-muted fs-6">PR</small></h3>
                </div>
            </div>
        </div>
    <div class="col-md-4 col-12 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div style="width: 80px; height: 80px;" class="me-3">
                        <canvas id="chartProgres"></canvas>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">PROGRES PENYELESAIAN</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $persen_selesai ?>%</h3>
                        <div class="text-muted small">Dari total <?= $count_total ?> pengajuan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_sukses'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Berhasil!</strong> Data request telah dihapus secara permanen.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablePR">
                <thead class="bg-light">
                    <tr class="small text-uppercase">
                        <th class="text-center">No</th>
                        <th>No. Request</th>
                        <th>Tanggal</th>
                        <th>Admin</th>
                        <th class="text-center">Total Item</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php
                    $no = 1;
                    $sql = "SELECT * FROM tr_request ORDER BY id_request DESC";
                    $query = mysqli_query($koneksi, $sql);
                    while($row = mysqli_fetch_array($query)) {
                        $id_req = $row['id_request'];
                        $jml_item = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_detail FROM tr_request_detail WHERE id_request='$id_req'"));
                        
                        // Logika Warna Status
                        switch($row['status_request']) {
                            case 'PENDING': $badge_color = 'bg-warning text-dark'; break;
                            case 'PROSES' : $badge_color = 'bg-primary'; break;
                            case 'SELESAI': $badge_color = 'bg-success'; break;
                            case 'BATAL'  : $badge_color = 'bg-danger'; break;
                            default       : $badge_color = 'bg-secondary';
                        }
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td class="fw-bold text-primary"><?= $row['no_request'] ?></td>
                        <td><?= date('d/m/Y', strtotime($row['tgl_request'])) ?></td>
                        <td class="text-uppercase"><?= $row['nama_pemesan'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3"><?= $jml_item ?> Item</span>
                        </td>
                       <td class="text-center">
                            <span class="badge status-badge <?= $badge_color ?> d-block mb-1">
                                <?= $row['status_request'] ?>
                            </span>
                            
                            <?php if(!empty($row['nama_pembeli'])): 
                            $p = strtoupper($row['nama_pembeli']);
                            $c = "bg-info"; // Default
                            if($p == "GANG") $c = "bg-danger";
                            if($p == "HENDRO") $c = "bg-success text-dark";
                        ?>
                            <span class="badge <?= $c ?>" style="font-size: 0.65rem;">
                                <i class="fas fa-user me-1"></i><?= $p ?>
                            </span>
                        <?php else: ?>
                            <div class="small text-danger" style="font-size: 0.7rem;">
                                <i class="fas fa-exclamation-circle me-1"></i>BELUM DISET
                            </div>
                        <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                               <button type="button" class="btn btn-sm btn-dark text-white btn-view-detail" 
                                        data-id="<?= $row['id_request'] ?>" 
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="cetak_pr.php?id=<?= $row['id_request'] ?>" 
                                    target="_blank" 
                                    class="btn btn-sm btn-info text-white" 
                                    title="Cetak PDF">
                                        <i class="fas fa-print"></i>
                                    </a>
                                
                                <?php if($row['status_request'] == 'PENDING'): ?>
                                    <a href="edit_request.php?id=<?= $row['id_request'] ?>" 
                                    class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                     <a href="hapus_request.php?id=<?= $row['id_request'] ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('Hapus seluruh form request ini?')" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="modalDetailPR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title small fw-bold"><i class="fas fa-info-circle me-2"></i>DETAIL ITEM PURCHASE REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="kontenDetail">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 small text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal">TUTUP</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalCetakTanggal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title small"><i class="fas fa-filter me-2"></i> FILTER CETAK REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="cetak_pr_bulk.php" method="GET" target="_blank">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small fw-bold">PILIH TANGGAL REQUEST</label>
                        <input type="date" name="tgl" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">STATUS REQUEST</label>
                        <select name="status" class="form-select">
                            <option value="PENDING">PENDING (Menunggu)</option>
                            <option value="SELESAI">SELESAI (Sudah Dibelikan)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100 fw-bold">PROSES & CETAK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // 1. Gabungkan inisialisasi DataTable menjadi SATU saja
    const table = $('#tablePR').DataTable({
        "destroy": true, // Menghapus inisialisasi lama jika ada
        "pageLength": 10,
        "order": [[ 0, "asc" ]],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "columnDefs": [
            { "orderable": false, "targets": [4, 5, 6] }
        ]
    });

    // 2. Gunakan Event Delegation untuk tombol mata
    // Caranya: $(document).on('click', 'selector', function...)
    // Ini lebih aman agar tombol tetap berfungsi meski tabel di-filter atau pindah halaman
    $(document).on('click', '.btn-view-detail', function() {
        const id = $(this).data('id');
        
        // Tampilkan Modal & Loading Spinner
        $('#modalDetailPR').modal('show');
        $('#kontenDetail').html('<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2 small text-muted">Memuat data...</p></div>');
        
        // Ambil data via AJAX
        $.ajax({
            url: 'get_detail_pr.php',
            type: 'GET',
            data: { id: id },
            success: function(response) {
                $('#kontenDetail').html(response);
            },
            error: function() {
                $('#kontenDetail').html('<div class="alert alert-danger m-3">Gagal mengambil data.</div>');
            }
        });
    });
});
</script>
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');
    const no_pr = urlParams.get('no'); // Mengambil parameter 'no'

    if (pesan === 'berhasil') {
        // Logika pengaman: jika no_pr kosong atau bernilai string 'null', tampilkan pesan umum
        let textTampil = (no_pr && no_pr !== 'null') 
            ? `Request <b>${no_pr}</b> telah berhasil dibuat dan disimpan.` 
            : `Request telah berhasil dibuat dan disimpan ke sistem.`;

        Swal.fire({
            icon: 'success',
            title: 'BERHASIL!',
            html: textTampil,
            confirmButtonColor: '#0000FF'
        });
    } else if (pesan === 'gagal') {
        Swal.fire({
            icon: 'error',
            title: 'GAGAL!',
            text: 'Terjadi kesalahan sistem saat menyimpan data.',
            confirmButtonColor: '#d33'
        });
    }

    // Bersihkan URL agar saat di-refresh alert tidak muncul lagi
    window.history.replaceState({}, document.title, window.location.pathname);
</script>
<script>
const ctx = document.getElementById('chartProgres').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        datasets: [{
            data: [<?= $count_proses ?>, <?= $count_pending ?>],
            backgroundColor: ['#0d6efd', '#ffc107'], // Biru (Selesai), Kuning (Pending)
            borderWidth: 0,
            cutout: '70%' // Membuat lubang tengah lebih besar (donat tipis)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: { enabled: false } // Matikan tooltip agar simpel
        }
    }
});
</script>
</body>
</html>