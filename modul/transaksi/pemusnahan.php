<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$username = $_SESSION['username'];
$id_user_logged = $_SESSION['id_user'];

// --- 1. LOGIKA GENERATE NO. PEMUSNAHAN ---
$bulan = date('Ym');
$query_no = mysqli_query($koneksi, "SELECT MAX(no_pemusnahan) as max_no FROM tr_pemusnahan WHERE no_pemusnahan LIKE 'PMS-$bulan-%'");
$data_no = mysqli_fetch_array($query_no);
$no_urut = (int) substr($data_no['max_no'] ?? '', -4);
$no_urut++;
$no_pms = "PMS-" . $bulan . "-" . sprintf("%04s", $no_urut);

// --- 2. PROSES SIMPAN PEMUSNAHAN ---
if(isset($_POST['simpan_pemusnahan'])){
    $no_pms_input = mysqli_real_escape_string($koneksi, $_POST['no_pemusnahan']);
    $tgl          = $_POST['tgl_pemusnahan'];
    $id_barang    = (int)$_POST['id_barang'];
    $qty          = (float)$_POST['qty_dimusnahkan'];
    $satuan       = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $metode       = mysqli_real_escape_string($koneksi, $_POST['metode_pemusnahan']);
    $nilai_jual   = (int)($_POST['nilai_jual_scrap'] ?? 0);
    $alasan       = mysqli_real_escape_string($koneksi, strtoupper($_POST['alasan_pemusnahan']));

    // Mulai Transaksi
    mysqli_begin_transaction($koneksi);

    try {
        // Set variabel user untuk trigger (jika ada)
        mysqli_query($koneksi, "SET @user_aksi = '$username'");

        // 1. Potong Stok di Master Barang
        $q_upd = mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty WHERE id_barang = $id_barang");
        if(!$q_upd) throw new Exception("Gagal update stok di tabel master.");

        // 2. Simpan ke Riwayat Transaksi Pemusnahan
        $sql_ins = "INSERT INTO tr_pemusnahan (no_pemusnahan, tgl_pemusnahan, id_barang, qty_dimusnahkan, satuan, metode_pemusnahan, nilai_jual_scrap, alasan_pemusnahan, id_user) 
                    VALUES ('$no_pms_input', '$tgl', $id_barang, $qty, '$satuan', '$metode', $nilai_jual, '$alasan', $id_user_logged)";
        if(!mysqli_query($koneksi, $sql_ins)) throw new Exception("Gagal simpan ke tabel tr_pemusnahan.");

        // 3. Log Kartu Stok (Penting agar sinkron dengan Kartu Stok)
        $keterangan_log = "PEMUSNAHAN ($metode): $alasan - REF: $no_pms_input";
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                    VALUES ($id_barang, NOW(), 'KELUAR', $qty, '$keterangan_log', '$username')";
        if(!mysqli_query($koneksi, $sql_log)) throw new Exception("Gagal membuat log kartu stok.");

        // Jika semua berhasil, simpan permanen
        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil! Stok berkurang dan log tercatat.'); window.location='pemusnahan.php';</script>";

    } catch (Exception $e) {
        // Jika ada satu saja yang gagal, batalkan semua
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal Simpan: ".$e->getMessage()."'); window.location='pemusnahan.php';</script>";
    }
}

// --- 3. PROSES HAPUS (BATAL & KEMBALIKAN STOK) ---
if(isset($_GET['aksi']) && $_GET['aksi'] == 'hapus'){
    $id_hps = (int)$_GET['id'];
    
    mysqli_begin_transaction($koneksi);

    try {
        // Ambil detail data sebelum dihapus
        $data_lama = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM tr_pemusnahan WHERE id_pemusnahan = $id_hps FOR UPDATE"));
        if(!$data_lama) throw new Exception("Data pemusnahan tidak ditemukan.");

        $brg_id      = $data_lama['id_barang'];
        $qty_kembali = $data_lama['qty_dimusnahkan'];
        $no_pms_lama = $data_lama['no_pemusnahan'];

        // 1. Kembalikan stok ke master_barang
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_kembali WHERE id_barang = $brg_id");

        // 2. Tambahkan log kartu stok (Masuk kembali sebagai pembatalan)
        $ket_batal = "PEMBATALAN PEMUSNAHAN: $no_pms_lama";
        mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                      VALUES ($brg_id, NOW(), 'MASUK', $qty_kembali, '$ket_batal', '$username')");

        // 3. Hapus data dari tr_pemusnahan
        mysqli_query($koneksi, "DELETE FROM tr_pemusnahan WHERE id_pemusnahan = $id_hps");

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil dihapus & stok dikembalikan ke gudang!'); window.location='pemusnahan.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal Hapus: ".$e->getMessage()."'); window.location='pemusnahan.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemusnahan Barang - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-red: #d63031; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-red { background-color: var(--mcp-red) !important; color: white; }
        .btn-red { background-color: var(--mcp-red); color: white; }
        input, select, textarea { text-transform: uppercase; }
        .stok-info { background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; }
        .table thead { background-color: #f1f3f5; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 fw-bold text-dark"><i class="fas fa-trash-alt me-2 text-danger"></i>RIWAYAT PEMUSNAHAN BARANG</h5>
            <div class="gap-2 d-flex">
                <a href="../../index.php" class="btn btn-sm btn-outline-secondary px-3"><i class="fas fa-arrow-left"></i> KEMBALI</a>
                <button class="btn btn-sm btn-red px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalPms">
                    <i class="fas fa-plus-circle me-1"></i> INPUT PEMUSNAHAN
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="tabelPms">
                    <thead class="text-center small fw-bold">
                        <tr>
                            <th>NO. TRANSAKSI</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>METODE</th>
                            <th>PETUGAS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $q = mysqli_query($koneksi, "SELECT p.*, m.nama_barang, u.nama_lengkap 
                             FROM tr_pemusnahan p 
                             JOIN master_barang m ON p.id_barang = m.id_barang 
                             JOIN users u ON p.id_user = u.id_user 
                             ORDER BY p.id_pemusnahan DESC");
                        while($h = mysqli_fetch_array($q)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-danger"><?= $h['no_pemusnahan'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_pemusnahan'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center"><?= number_format($h['qty_dimusnahkan'], 2) ?> <?= $h['satuan'] ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $h['metode_pemusnahan'] ?></span></td>
                            <td class="text-center small"><?= $h['nama_lengkap'] ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="cetak_pms.php?id=<?= $h['id_pemusnahan'] ?>" target="_blank" class="btn btn-sm btn-primary" title="Cetak Bukti">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="?aksi=hapus&id=<?= $h['id_pemusnahan'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data ini? Stok akan dikembalikan ke Master Barang secara otomatis.')" title="Hapus & Kembalikan Stok">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPms" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-red">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-fire me-2"></i>FORM PENGHAPUSAN / PEMUSNAHAN BARANG</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">NO. TRANSAKSI</label>
                            <input type="text" name="no_pemusnahan" class="form-control fw-bold text-danger bg-light" value="<?= $no_pms ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">TANGGAL TRANSAKSI</label>
                            <input type="date" name="tgl_pemusnahan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">PILIH BARANG GUDANG</label>
                        <select name="id_barang" id="id_barang" class="form-select select2-barang" required>
                            <option value="">-- KETIK NAMA BARANG --</option>
                            <?php
                            $brg = mysqli_query($koneksi, "SELECT id_barang, nama_barang, satuan, stok_akhir FROM master_barang WHERE stok_akhir > 0 ORDER BY nama_barang ASC");
                            while($b = mysqli_fetch_array($brg)){
                                echo "<option value='{$b['id_barang']}' data-satuan='{$b['satuan']}' data-stok='{$b['stok_akhir']}'>
                                        {$b['nama_barang']} (Stok: {$b['stok_akhir']} {$b['satuan']})
                                      </option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="stok-info mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <label class="small fw-bold text-danger">QTY DIMUSNAHKAN</label>
                                <input type="number" step="0.01" name="qty_dimusnahkan" id="qty_input" class="form-control form-control-lg fw-bold" min="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">SATUAN</label>
                                <input type="text" name="satuan" id="satuan_input" class="form-control form-control-lg bg-white text-center" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">METODE PEMUSNAHAN</label>
                                <select name="metode_pemusnahan" class="form-select form-select-lg" required>
                                    <option value="DIHANCURKAN">DIHANCURKAN (SCRAP)</option>
                                    <option value="DIJUAL">DIJUAL (ROMBENG)</option>
                                    <option value="DIBUANG">DIBUANG (SAMPAH)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">NILAI JUAL SCRAP (ISI 0 JIKA TIDAK DIJUAL)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="nilai_jual_scrap" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="small fw-bold mb-1">ALASAN PEMUSNAHAN / KERUSAKAN</label>
                        <textarea name="alasan_pemusnahan" class="form-control" rows="3" placeholder="Contoh: Barang rusak terkena air / expired" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" name="simpan_pemusnahan" class="btn btn-sm btn-red px-4 fw-bold">
                        <i class="fas fa-check-circle me-1"></i> KONFIRMASI PEMUSNAHAN
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelPms').DataTable({
        "order": [[0, "desc"]],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" }
    });

    $('.select2-barang').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#modalPms')
    });

    $('.select2-barang').on('select2:select', function (e) {
        const data = e.params.data.element;
        const stok = parseFloat(data.getAttribute('data-stok'));
        const satuan = data.getAttribute('data-satuan');
        
        $('#satuan_input').val(satuan);
        $('#qty_input').attr('max', stok).attr('placeholder', 'MAX: ' + stok).val('');
    });

    $('#qty_input').on('input', function() {
        const maxStok = parseFloat($(this).attr('max'));
        const inputVal = parseFloat($(this).val());

        if (inputVal > maxStok) {
            alert('Stok tidak cukup! Maksimal tersedia: ' + maxStok);
            $(this).val(maxStok);
        }
    });
});
</script>
</body>
</html>