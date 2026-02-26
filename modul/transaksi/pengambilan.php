<?php
session_start();
include '../../config/koneksi.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// 1. Generate No. Permintaan Otomatis
$bulan_sekarang = date('Ym');
$query_no = mysqli_query($koneksi, "SELECT MAX(no_permintaan) as max_no FROM bon_permintaan WHERE no_permintaan LIKE 'PB-$bulan_sekarang-%'");
$data_no = mysqli_fetch_array($query_no);
$no_urut = (int) substr($data_no['max_no'] ?? '', -4);
$no_urut++;
$no_permintaan = "PB-" . $bulan_sekarang . "-" . sprintf("%04s", $no_urut);

// 2. Proses Simpan Multi Barang
if(isset($_POST['simpan'])){
    $no_req     = $_POST['no_permintaan'];
    $tgl        = $_POST['tgl_keluar'];
    $penerima   = mysqli_real_escape_string($koneksi, strtoupper($_POST['penerima']));
    
    // Ambil array per-item
    $id_barangs     = $_POST['id_barang'] ?? [];
    $qty_keluars    = $_POST['qty_keluar'] ?? [];
    $plat_nomors    = $_POST['plat_nomor_item'] ?? [];
    $keperluan_items= $_POST['keperluan_item'] ?? [];
    
    // Validasi minimal 1 barang
    $valid_items = array_filter($id_barangs);
    if(empty($valid_items)){
        echo "<script>alert('Pilih minimal 1 barang!'); window.location='pengambilan.php';</script>";
        exit;
    }
    
    // --- A. CEK STOK SEMUA BARANG DULU ---
    $error_stok = [];
    foreach($id_barangs as $idx => $id_barang){
        if(empty($id_barang)) continue;
        $qty = (float)($qty_keluars[$idx] ?? 0);
        if($qty <= 0) continue;
        
        $sql_cek = "SELECT 
                    (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = '$id_barang' AND tipe_transaksi = 'MASUK') as t_masuk,
                    (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = '$id_barang' AND tipe_transaksi = 'KELUAR') as t_keluar";
        $res_cek = mysqli_fetch_array(mysqli_query($koneksi, $sql_cek));
        $stok_sebenarnya = ($res_cek['t_masuk'] ?? 0) - ($res_cek['t_keluar'] ?? 0);
        
        if($qty > $stok_sebenarnya){
            $nama_barang = mysqli_fetch_array(mysqli_query($koneksi, "SELECT nama_barang FROM master_barang WHERE id_barang='$id_barang'"))['nama_barang'];
            $error_stok[] = "$nama_barang (Stok: $stok_sebenarnya, Diminta: $qty)";
        }
    }
    
    if(!empty($error_stok)){
        echo "<script>alert('Gagal! Stok tidak mencukupi untuk:\\n- ".implode("\\n- ", $error_stok)."'); window.location='pengambilan.php';</script>";
        exit;
    }
    
    // --- B. PROSES SIMPAN DENGAN TRANSACTION ---
    mysqli_query($koneksi, "BEGIN");
    $success = true;
    $id_cetak = null;
    
    foreach($id_barangs as $idx => $id_barang){
        if(empty($id_barang)) continue;
        $qty = (float)($qty_keluars[$idx] ?? 0);
        if($qty <= 0) continue;
        
        $plat_item  = mysqli_real_escape_string($koneksi, trim($plat_nomors[$idx] ?? ''));
        $keperluan  = mysqli_real_escape_string($koneksi, strtoupper(trim($keperluan_items[$idx] ?? '')));
        
        // Insert ke bon_permintaan
        $query = mysqli_query($koneksi, "INSERT INTO bon_permintaan (no_permintaan, id_barang, tgl_keluar, qty_keluar, penerima, keperluan, plat_nomor) 
                  VALUES ('$no_req', '$id_barang', '$tgl', '$qty', '$penerima', '$keperluan', '$plat_item')");
        
        if(!$query){ $success = false; break; }
        if(is_null($id_cetak)) $id_cetak = mysqli_insert_id($koneksi); 
        
        // Update stok cadangan
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty WHERE id_barang='$id_barang'");

        // Catat ke tr_stok_log
        $info_plat = ($plat_item != "") ? " [UNIT: $plat_item]" : "";
        $keterangan_log = "PENGAMBILAN: $penerima ($keperluan)$info_plat";
        $waktu_sekarang = date('H:i:s');
        
        mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                  VALUES ('$id_barang', '$tgl $waktu_sekarang', 'KELUAR', '$qty', '$keterangan_log')");
    }
    
    if($success){
        mysqli_query($koneksi, "COMMIT");
        echo "<script>
                if(confirm('✅ Berhasil Simpan ".count($valid_items)." Barang!\\n\\nCetak Bukti Pengambilan?')){
                    window.open('cetak_permintaan.php?id=$id_cetak', '_blank');
                }
                window.location='pengambilan.php';
              </script>";
    } else {
        mysqli_query($koneksi, "ROLLBACK");
        echo "<script>alert('❌ Gagal menyimpan. Silakan coba lagi.'); window.location='pengambilan.php';</script>";
    }
}
?>      
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Barang - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-mcp { background-color: var(--mcp-blue) !important; color: white; }
        input, select, textarea { text-transform: uppercase; }
        .btn-mcp { background-color: var(--mcp-blue); color: white; }
        .stok-label { background: #e7f0ff; padding: 8px; border-radius: 6px; border-left: 4px solid blue; font-size: 0.9rem; }
        
        /* Style untuk item barang dinamis */
        .item-barang-row {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 12px;
            background: #fff;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .item-barang-row:hover { border-color: #0d6efd; }
        .btn-hapus-item {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 14px;
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .max-items-warning { font-size: 11px; color: #dc3545; font-weight: 500; }
        .item-header { font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; }
        
        @media (max-width: 768px) {
            .item-barang-row .col-md-2, .item-barang-row .col-md-3 { width: 100%; margin-bottom: 8px; }
        }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    
    <!-- Statistik -->
    <div class="row mb-4">
        <?php
            $sql_top_barang = "SELECT m.nama_barang, COUNT(b.id_barang) as total_transaksi 
                               FROM bon_permintaan b JOIN master_barang m ON b.id_barang = m.id_barang 
                               GROUP BY b.id_barang ORDER BY total_transaksi DESC LIMIT 5";
            $res_top = mysqli_query($koneksi, $sql_top_barang);
            $labels_top = []; $data_top = [];
            while($row = mysqli_fetch_array($res_top)){
                $labels_top[] = $row['nama_barang'];
                $data_top[] = $row['total_transaksi'];
            }
            $labels_tren = []; $data_tren = [];
            for ($i = 6; $i >= 0; $i--) {
                $tgl = date('Y-m-d', strtotime("-$i days"));
                $labels_tren[] = date('d M', strtotime($tgl));
                $sql_t = mysqli_query($koneksi, "SELECT COUNT(*) as total_transaksi FROM bon_permintaan WHERE tgl_keluar = '$tgl'");
                $dt_t = mysqli_fetch_array($sql_t);
                $data_tren[] = $dt_t['total_transaksi'] ?? 0;
            }
        ?>
         <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 fw-bold text-dark"><i class="fas fa-chart-area me-2 text-primary"></i>STATISTIK PENGAMBILAN</h5>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 text-muted"><i class="fas fa-chart-bar me-2 text-primary"></i>TOP 5 BARANG PALING SERING KELUAR</h6>
                    <div style="height: 250px;"><canvas id="chartTopBarang"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 text-muted"><i class="fas fa-line-chart me-2 text-success"></i>TREN FREKUENSI PENGAMBILAN (7 HARI)</h6>
                    <div style="height: 250px;"><canvas id="chartTrenHarian"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Histori -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i>RIWAYAT PENGAMBILAN</h5>
                <div class="d-flex gap-2">
                    <a href="../../index.php" class="btn btn-sm btn-danger px-3"><i class="fas fa-arrow-left"></i> KEMBALI</a>
                    <button type="button" class="btn btn-sm btn-mcp px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAmbil">
                        <i class="fas fa-plus-circle me-1"></i> BUAT PERMINTAAN
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="tabelHistori">
                    <thead class="bg-light">
                        <tr class="text-center small fw-bold">
                            <th>NO. PB</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>PENERIMA</th>
                            <th>UNIT</th>
                            <th>KEPERLUAN</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $histori = mysqli_query($koneksi, "SELECT b.*, m.nama_barang, m.satuan FROM bon_permintaan b JOIN master_barang m ON b.id_barang=m.id_barang ORDER BY b.id_bon DESC");
                        while($h = mysqli_fetch_array($histori)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-primary"><?= $h['no_permintaan'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_keluar'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center text-danger fw-bold"><?= number_format($h['qty_keluar'], 2, ',', '.') ?> <?= $h['satuan'] ?></td>
                            <td><?= $h['penerima'] ?></td>
                            <td class="small"><?= $h['plat_nomor'] ?: '-' ?></td>
                            <td class="small"><?= $h['keperluan'] ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="cetak_permintaan.php?id=<?= $h['id_bon'] ?>" target="_blank" class="btn btn-sm btn-success" title="Cetak">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                            data-id="<?= $h['id_bon'] ?>" 
                                            data-barang="<?= $h['nama_barang'] ?>"
                                            data-qty="<?= $h['qty_keluar'] ?>" 
                                            data-penerima="<?= $h['penerima'] ?>"
                                            data-keperluan="<?= $h['keperluan'] ?>"
                                            data-tgl="<?= date('Y-m-d', strtotime($h['tgl_keluar'])) ?>"
                                            data-plat="<?= $h['plat_nomor'] ?>"
                                            title="Edit"> 
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="proses_hapus_pengambilan.php?id=<?= $h['id_bon'] ?>" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('BATALKAN PENGAMBILAN?\n\nBarang: <?= $h['nama_barang'] ?>\nQty: <?= $h['qty_keluar'] ?>\n\nStok akan dikembalikan!')"
                                    title="Hapus">
                                        <i class="fas fa-trash"></i>
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

<!-- MODAL FORM PENGELUARAN BARANG (MULTI-ITEM) -->
<div class="modal fade" id="modalAmbil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-mcp">
                <h6 class="modal-title fw-bold text-white">FORM PENGELUARAN BARANG</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="formPengambilan">
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 mb-3" style="font-size: 11px;">
                        <i class="fas fa-info-circle me-1"></i> 
                        <strong>Multi-Item:</strong> Tambah hingga 5 barang. Setiap barang bisa memiliki Unit Mobil & Keperluan berbeda.
                    </div>

                    <!-- HEADER: Data Umum -->
                    <div class="row g-3 mb-4 pb-3 border-bottom">
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">NO. PERMINTAAN</label>
                            <input type="text" name="no_permintaan" class="form-control fw-bold bg-light" value="<?= $no_permintaan ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">TANGGAL AMBIL</label>
                            <input type="date" name="tgl_keluar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">PENERIMA BARANG</label>
                            <input type="text" name="penerima" class="form-control" required placeholder="NAMA KARYAWAN / TEKNISI">
                        </div>
                    </div>

                    <!-- CONTAINER ITEM BARANG DINAMIS -->
                    <div class="mb-3">
                        <label class="small fw-bold mb-2 text-primary">
                            <i class="fas fa-boxes me-1"></i> DAFTAR BARANG 
                            <span class="max-items-warning">(Maksimal 5 Item)</span>
                        </label>
                        
                        <div id="container-items">
                            <!-- Item Row 1 (Template) -->
                            <div class="item-barang-row" data-index="0">
                                <div class="item-header">ITEM #<span class="item-number">1</span></div>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-item" onclick="hapusItem(this)" title="Hapus" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="small fw-bold mb-1">BARANG</label>
                                        <select name="id_barang[]" class="form-select select2-barang-item border-primary" onchange="cekStokItem(this)" required>
                                            <option value="">-- PILIH BARANG --</option>
                                            <?php
                                            $sql_load = "SELECT b.id_barang, b.nama_barang, b.satuan,
                                                        (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'MASUK') as masuk,
                                                        (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'KELUAR') as keluar
                                                        FROM master_barang b ORDER BY b.nama_barang ASC";
                                            $res_load = mysqli_query($koneksi, $sql_load);
                                            while($b = mysqli_fetch_array($res_load)){
                                                $sisa = ($b['masuk'] ?? 0) - ($b['keluar'] ?? 0);
                                                if($sisa > 0) {
                                                    echo "<option value='{$b['id_barang']}' data-stok='{$sisa}' data-satuan='{$b['satuan']}'>{$b['nama_barang']} (Stok: $sisa)</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1">STOK</label>
                                        <div class="stok-label text-center">
                                            <span class="txt_stok fw-bold text-primary">0</span> <span class="txt_satuan small"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-danger mb-1">QTY</label>
                                        <input type="number" name="qty_keluar[]" class="form-control form-control-sm fw-bold border-danger qty-input" 
                                               min="0.01" step="0.01" placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1 text-primary">UNIT</label>
                                        <select name="plat_nomor_item[]" class="form-select form-select-sm select2-mobil-item">
                                            <option value="">-- UMUM --</option>
                                            <?php
                                            $mobil = mysqli_query($koneksi, "SELECT plat_nomor, merk_tipe FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC");
                                            while($m = mysqli_fetch_array($mobil)){
                                                echo "<option value='{$m['plat_nomor']}'>{$m['plat_nomor']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1">KEPERLUAN</label>
                                        <input type="text" name="keperluan_item[]" class="form-control form-control-sm" placeholder="Kebutuhan">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnTambahItem" onclick="tambahItem()">
                            <i class="fas fa-plus me-1"></i> + Tambah Barang
                        </button>
                        <span id="itemCount" class="small text-muted ms-2">1 dari 5 item</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan" class="btn btn-mcp fw-bold py-2 px-4">
                        <i class="fas fa-save me-1"></i> SIMPAN & POTONG STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT (SINGLE ITEM) -->
<div class="modal fade" id="modalEditAmbil" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title fw-bold">KOREKSI PENGAMBILAN</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_edit_pengambilan.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_bon" id="edit_id">
                    <div class="mb-3">
                        <label class="small fw-bold">BARANG</label>
                        <input type="text" id="edit_barang" class="form-control bg-light" readonly>
                        <small class="text-muted">*Barang tidak dapat diubah</small>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-danger">TANGGAL</label>
                        <input type="date" name="tgl_keluar" id="edit_tgl" class="form-control border-danger fw-bold" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">PENERIMA</label>
                        <input type="text" name="penerima" id="edit_penerima" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-primary">UNIT MOBIL</label>
                        <select name="plat_nomor" id="edit_plat" class="form-select">
                            <option value="">-- UMUM --</option>
                            <?php
                            $mobil_edit = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC");
                            while($me = mysqli_fetch_array($mobil_edit)){
                                echo "<option value='{$me['plat_nomor']}'>{$me['plat_nomor']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-danger">QTY</label>
                        <input type="number" name="qty_baru" id="edit_qty" class="form-control fw-bold border-danger" step="0.01" required>
                        <input type="hidden" name="qty_lama" id="edit_qty_lama">
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">KEPERLUAN</label>
                        <textarea name="keperluan" id="edit_keperluan" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">SIMPAN PERUBAHAN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let itemCount = 1;
    const MAX_ITEMS = 5;

    $(document).ready(function() {
        // DataTables
        $('#tabelHistori').DataTable({ "order": [[0, "desc"]] });

        // Init Select2 untuk item pertama saat modal buka
        initSelect2Barang('.select2-barang-item');
        initSelect2Mobil('.select2-mobil-item');
        initSelect2Mobil('#edit_plat');

        // Reset form saat modal ditutup
        $('#modalAmbil').on('hidden.bs.modal', function () {
            $('#container-items').html('');
            itemCount = 0;
            tambahItem();
            $('#formPengambilan')[0].reset();
        });

        // Edit Button Handler
        $(document).on('click', '.btn-edit', function() {
            var d = $(this).data();
            $('#edit_id').val(d.id);
            $('#edit_barang').val(d.barang);
            $('#edit_penerima').val(d.penerima);
            $('#edit_qty').val(d.qty);
            $('#edit_qty_lama').val(d.qty);
            $('#edit_keperluan').val(d.keperluan);
            $('#edit_tgl').val(d.tgl);
            $('#edit_plat').val(d.plat).trigger('change');
            $('#modalEditAmbil').modal('show');
        });
    });

    // Init Select2 Barang
    function initSelect2Barang(selector) {
        $(selector).select2({
            theme: 'bootstrap-5',
            placeholder: '-- CARI BARANG --',
            allowClear: true,
            dropdownParent: $('#modalAmbil'),
            width: '100%'
        }).on('select2:select', function (e) { 
            cekStokItem(this); 
        });
    }

    // Init Select2 Mobil
    function initSelect2Mobil(selector) {
        $(selector).select2({
            theme: 'bootstrap-5',
            placeholder: '-- PILIH UNIT --',
            allowClear: true,
            dropdownParent: $('#modalAmbil'),
            width: '100%'
        });
    }

    // Tambah Item Baru (FIXED)
    function tambahItem() {
        if(itemCount >= MAX_ITEMS) { 
            alert('Maksimal 5 barang!'); 
            return; 
        }
        
        itemCount++;
        const index = itemCount - 1;
        
        // Buat HTML item baru (bukan clone, tapi buat fresh agar Select2 bersih)
        const newItemHtml = `
        <div class="item-barang-row" data-index="${index}">
            <div class="item-header">ITEM #<span class="item-number">${itemCount}</span></div>
            <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-item" onclick="hapusItem(this)" title="Hapus">
                <i class="fas fa-times"></i>
            </button>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="small fw-bold mb-1">BARANG</label>
                    <select name="id_barang[]" class="form-select select2-barang-item border-primary" required>
                        <option value="">-- PILIH BARANG --</option>
                        <?php
                        $sql_load = "SELECT b.id_barang, b.nama_barang, b.satuan,
                                    (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'MASUK') as masuk,
                                    (SELECT COALESCE(SUM(qty),0) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'KELUAR') as keluar
                                    FROM master_barang b ORDER BY b.nama_barang ASC";
                        $res_load = mysqli_query($koneksi, $sql_load);
                        while($b = mysqli_fetch_array($res_load)){
                            $sisa = ($b['masuk'] ?? 0) - ($b['keluar'] ?? 0);
                            if($sisa > 0) {
                                echo "<option value='{$b['id_barang']}' data-stok='{$sisa}' data-satuan='{$b['satuan']}'>{$b['nama_barang']} (Stok: $sisa)</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold mb-1">STOK</label>
                    <div class="stok-label text-center">
                        <span class="txt_stok fw-bold text-primary">0</span> <span class="txt_satuan small"></span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-danger mb-1">QTY</label>
                    <input type="number" name="qty_keluar[]" class="form-control form-control-sm fw-bold border-danger qty-input" 
                           min="0.01" step="0.01" placeholder="0.00" required>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold mb-1 text-primary">UNIT</label>
                    <select name="plat_nomor_item[]" class="form-select form-select-sm select2-mobil-item">
                        <option value="">-- UMUM --</option>
                        <?php
                        $mobil = mysqli_query($koneksi, "SELECT plat_nomor, merk_tipe FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC");
                        while($m = mysqli_fetch_array($mobil)){
                            echo "<option value='{$m['plat_nomor']}'>{$m['plat_nomor']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold mb-1">KEPERLUAN</label>
                    <input type="text" name="keperluan_item[]" class="form-control form-control-sm" placeholder="Kebutuhan">
                </div>
            </div>
        </div>`;
        
        // Append ke container
        $('#container-items').append(newItemHtml);
        
        // Init Select2 untuk elemen baru
        const newItem = $('#container-items .item-barang-row').last();
        initSelect2Barang(newItem.find('.select2-barang-item'));
        initSelect2Mobil(newItem.find('.select2-mobil-item'));
        
        updateItemCountDisplay();
    }

    // Hapus Item
    function hapusItem(btn) {
        if(itemCount <= 1) { 
            alert('Minimal 1 barang!'); 
            return; 
        }
        if(confirm('Hapus baris ini?')) {
            $(btn).closest('.item-barang-row').remove();
            itemCount--;
            updateItemCountDisplay();
            
            // Renumber item labels
            $('.item-number').each(function(idx) {
                $(this).text(idx + 1);
            });
        }
    }

    function updateItemCountDisplay() {
        $('#itemCount').text(`${itemCount} dari ${MAX_ITEMS} item`);
        $('#btnTambahItem').prop('disabled', itemCount >= MAX_ITEMS);
        
        $('.btn-hapus-item').each(function() {
            $(this).prop('disabled', itemCount <= 1);
        });
    }

    // Cek Stok per Item
    function cekStokItem(selectElement) {
        const selected = selectElement.options[selectElement.selectedIndex];
        const stok = parseFloat(selected.getAttribute('data-stok')) || 0;
        const satuan = selected.getAttribute('data-satuan') || "";
        
        const row = $(selectElement).closest('.item-barang-row');
        row.find('.txt_stok').text(stok);
        row.find('.txt_satuan').text(satuan);
        
        const qtyInput = row.find('.qty-input');
        qtyInput.attr('max', stok);
        qtyInput.val("");
        
        // Validasi real-time + normalisasi koma ke titik
        qtyInput.on('input', function() {
            this.value = this.value.replace(',', '.');
            if(parseFloat(this.value) > stok) {
                alert(`Stok hanya ${stok} ${satuan}!`);
                this.value = stok;
            }
        });
    }

    // Normalisasi desimal untuk semua qty input
    $(document).on('input', '.qty-input', function() {
        this.value = this.value.replace(',', '.');
    });
</script>
<!-- Chart Scripts (FIXED) -->
<script>
$(document).ready(function() {
    // 1. Chart Top Barang (Horizontal Bar)
    const ctxTop = document.getElementById('chartTopBarang').getContext('2d');
    new Chart(ctxTop, {
        type: 'bar',
        data: {  // ✅ FIX: Tambahkan "data:" di sini
            labels: <?= json_encode($labels_top) ?>,
            datasets: [{
                label: 'Total Transaksi',
                data: <?= json_encode($data_top) ?>,
                backgroundColor: 'rgba(0, 0, 255, 0.7)',
                borderRadius: 5,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false } 
            }
        }
    });

    // 2. Chart Tren Harian (Line)
    const ctxTren = document.getElementById('chartTrenHarian').getContext('2d');
    new Chart(ctxTren, {
        type: 'line',
        data: {  // ✅ FIX: Tambahkan "data:" di sini
            labels: <?= json_encode($labels_tren) ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= json_encode($data_tren) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#198754'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false } 
            },
            scales: {
                y: { 
                    beginAtZero: true 
                }
            }
        }
    });
});
</script>
</body>
</html>