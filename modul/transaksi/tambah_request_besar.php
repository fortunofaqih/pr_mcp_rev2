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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Request Baru - MCP System</title>
   <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f4f7f6; font-size: 0.85rem; }
        .card-header { background: white; border-bottom: 2px solid #eee; }
        .table-input thead { background: #dc3545; color: white; font-size: 0.75rem; text-transform: uppercase; }
        
        /* Pengaturan Responsif & Lebar Kolom */
        .table-responsive { border-radius: 8px; overflow-x: auto; }
        .table-input { min-width: 1600px; table-layout: fixed; }
        
        /* Definisi Lebar Tiap Kolom */
        .col-brg { width: 220px; }
        .col-kat { width: 140px; }
        .col-kwal { width: 160px; }
        .col-mbl { width: 130px; }
        .col-tip { width: 100px; }
        .col-qty { width: 80px; }
        .col-sat { width: 110px; }
        .col-hrg { width: 130px; }
        .col-tot { width: 130px; }
        .col-ket { width: 300px; } /* Kolom keterangan per item */
        .col-aks { width: 50px; }

        input, select, textarea { text-transform: uppercase; font-size: 0.8rem !important; }
        .bg-autonumber { background-color: #e9ecef; border-style: dashed; color: #00008B; font-weight: bold; }
        
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 31px !important;
            padding: 2px 5px !important;
        }

        textarea.input-keterangan { 
            resize: vertical; 
            min-height: 35px; 
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .container-fluid { padding: 5px; }
        }
        textarea.input-keterangan:focus { min-height: 80px; transition: 0.3s; }
    </style>
</head>

<body class="py-4">
<div class="container-fluid">
    <form action="proses_simpan_besar.php" method="POST">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header py-3">
                        <h5 class="fw-bold m-0 text-danger"><i class="fas fa-boxes-stacked me-2"></i> FORM REQUEST BARANG BESAR / INVESTASI</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">NOMOR REQUEST</label>
                                <input type="text" class="form-control bg-autonumber" value="[ GENERATE OTOMATIS ]" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">TANGGAL REQUEST</label>
                                <input type="date" name="tgl_request" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted">NAMA PEMESAN / BAGIAN</label>
                                <input type="text" name="nama_pemesan" class="form-control" placeholder="CONTOH: BAPAK BUDI / PRODUKSI" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="small fw-bold text-danger">KEPERLUAN PEMBELIAN</label>
                                <textarea name="keterangan_umum" class="form-control" rows="2" placeholder="JELASKAN TUJUAN PEMBELIAN INI..." required></textarea>
                            </div>
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-bordered table-input align-middle" id="tableItem">
                                <thead>
                                    <tr class="text-center">
                                        <th class="col-brg">Nama Barang</th>
                                        <th class="col-kat">Kategori</th>
                                        <th class="col-kwal">Kwalifikasi</th>
                                        <th class="col-mbl">Unit/Mobil</th>
                                        <th class="col-tip">Tipe</th>
                                        <th class="col-qty">Qty</th>
                                        <th class="col-sat">Satuan</th>
                                        <th class="col-hrg">Harga (Est)</th>
                                        <th class="col-tot">Total (Rp)</th>
                                        <th class="col-ket">Catatan Detail</th>
                                        <th class="col-aks"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="item-row">
                                        <td>
                                            <select name="nama_barang[]" class="form-select form-select-sm select-barang" required>
                                                <option value="">-- PILIH BARANG --</option>
                                                <?php
                                                $brg = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE status_aktif='AKTIF' ORDER BY nama_barang ASC");
                                                while($b = mysqli_fetch_array($brg)){
                                                    echo "<option value='".$b['nama_barang']."' 
                                                            data-satuan='".strtoupper($b['satuan'])."' 
                                                            data-merk='".strtoupper($b['merk'])."' 
                                                            data-kategori='".strtoupper($b['kategori'])."'
                                                            data-harga='".$b['harga_beli']."'>".$b['nama_barang']."</option>";
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="kategori_request[]" class="form-select form-select-sm select-kategori" required>
                                                <option value="">- PILIH -</option>
                                                <optgroup label="BENGKEL">
                                                    <option value="BENGKEL MOBIL">BENGKEL MOBIL</option>
                                                    <option value="BENGKEL LISTRIK">BENGKEL LISTRIK</option>
                                                    <option value="BENGKEL DINAMO">BENGKEL DINAMO</option>
                                                    <option value="BENGKEL BUBUT">BENGKEL BUBUT</option>
                                                </optgroup>
                                                <optgroup label="UMUM">
                                                    <option value="KANTOR">KANTOR</option>
                                                    <option value="BANGUNAN">BANGUNAN</option>
                                                    <option value="UMUM">UMUM</option>
                                                </optgroup>
                                            </select>
                                        </td>
                                        <td><input type="text" name="kwalifikasi[]" class="form-control form-control-sm input-kwalifikasi" placeholder="Merk/Spek"></td>
                                        <td>
                                            <select name="id_mobil[]" class="form-select form-select-sm select-mobil">
                                                <option value="0">NON MOBIL</option>
                                                <?php
                                                $mbl = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
                                                while($m = mysqli_fetch_array($mbl)){
                                                    echo "<option value='".$m['id_mobil']."'>".$m['plat_nomor']."</option>";
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="tipe_request[]" class="form-select form-select-sm select-tipe">
                                                <option value="LANGSUNG" selected>LANGSUNG</option>
                                                <option value="STOK">STOK</option>
                                            </select>
                                        </td>
                                        <td><input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="0.01" value="1" required></td>
                                        <td>
                                            <select name="satuan[]" class="form-select form-select-sm select-satuan" required>
                                                <option value="">- PILIH -</option>
                                                <option value="PCS">PCS</option>
                                                <option value="DUS">DUS</option>
                                                <option value="KG">KG</option>
                                                 <option value="ONS">ONS</option>
                                                <option value="LITER">LITER</option>
                                                <option value="METER">METER</option>
                                                <option value="CM">CM</option>
                                                <option value="LONJOR">LONJOR</option>
                                                <option value="SET">SET</option>
                                                <option value="ROLL">ROLL</option>
                                                <option value="PAX">PAX</option>
                                                <option value="UNIT">UNIT</option>
                                                <option value="DRUM">DRUM</option>
                                                <option value="SAK">SAK</option>
                                                <option value="PAIL">PAIL</option>
                                                <option value="CAN">CAN</option>
                                                <option value="BOTOL">BOTOL</option>
                                                <option value="TUBE">TUBE</option>
                                                <option value="GALON">GALON</option>
                                                <option value="IKAT">IKAT</option>
                                                <option value="LEMBAR">LEMBAR</option>
                                                <option value="BATANG">BATANG</option>
                                                <option value="KOTAK">KOTAK</option>
                                                <option value="COLT">COLT</option>
                                                <option value="JURIGEN">JURIGEN</option>
                                            </select>
                                        </td>
                                        <td><input type="number" name="harga[]" class="form-control form-control-sm input-harga text-end" placeholder="0"></td>
                                        <td><input type="text" class="form-control form-control-sm input-subtotal text-end bg-light" value="0" readonly></td>
                                        <td>
                                            <textarea name="keterangan_item[]" class="form-control form-control-sm input-keterangan" rows="1" placeholder="Spek mendalam..."></textarea>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row border-0"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="button" id="addRow" class="btn btn-sm btn-success fw-bold px-3 mt-2 shadow-sm">
                            <i class="fas fa-plus me-1"></i> Tambah Baris
                        </button>

                        <div class="row mt-3 justify-content-end">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-danger text-white fw-bold">TOTAL ESTIMASI</span>
                                    <input type="text" id="grandTotal" class="form-control form-control-lg text-end fw-bold bg-white" value="Rp 0" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white py-3">
                        <button type="submit" class="btn btn-primary fw-bold px-5 shadow-sm">
                            <i class="fas fa-save me-1"></i> SIMPAN REQUEST BESAR
                        </button>
                        <a href="pr.php" class="btn btn-danger fw-bold px-4">BATAL</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function(){
    
    // Inisialisasi Select2
    function initSelect2() {
        $('.select-barang, .select-kategori, .select-mobil, .select-tipe, .select-satuan').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: "-- PILIH --"
        });
    }
    initSelect2();

    // Hitung-hitungan
    function hitungSubtotal(row) {
        var qty = parseFloat(row.find('.input-qty').val()) || 0;
        var harga = parseFloat(row.find('.input-harga').val()) || 0;
        var subtotal = qty * harga;
        row.find('.input-subtotal').val(subtotal.toLocaleString('id-ID'));
        hitungGrandTotal();
    }

    function hitungGrandTotal() {
        var grandTotal = 0;
        $('.input-subtotal').each(function() {
            var sub = parseFloat($(this).val().replace(/\./g, '').replace(/,/g, '.')) || 0;
            grandTotal += sub;
        });
        $('#grandTotal').val("Rp " + grandTotal.toLocaleString('id-ID'));
    }

    // Auto Fill
    $(document).on('change', '.select-barang', function(){
        var row = $(this).closest('tr');
        var selected = $(this).find(':selected');
        row.find('.input-kwalifikasi').val(selected.data('merk'));
        row.find('.input-harga').val(selected.data('harga'));
        if(selected.data('kategori')) row.find('.select-kategori').val(selected.data('kategori')).trigger('change.select2');
        if(selected.data('satuan')) row.find('.select-satuan').val(selected.data('satuan')).trigger('change.select2');
        hitungSubtotal(row);
    });

    $(document).on('input', '.input-qty, .input-harga', function(){
        hitungSubtotal($(this).closest('tr'));
    });

    // Tambah Baris
    $("#addRow").click(function(){
        $('.select-barang, .select-kategori, .select-mobil, .select-tipe, .select-satuan').select2('destroy');
        var newRow = $('.item-row:last').clone(); 
        newRow.find('input').val('');
        newRow.find('textarea').val('');
        newRow.find('.input-qty').val('1');
        newRow.find('.input-subtotal').val('0');
        newRow.find('select').val('').trigger('change');
        newRow.find('.select-mobil').val('0');
        newRow.find('.select-tipe').val('LANGSUNG');
        $("#tableItem tbody").append(newRow);
        initSelect2();
    });

    // Hapus Baris
    $(document).on('click', '.remove-row', function(){
        if($("#tableItem tbody tr").length > 1){
            $(this).closest('tr').remove();
            hitungGrandTotal();
        }
    });

    // Validasi & Simpan dengan LOADING
    $('form').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var total = $('#grandTotal').val();

        Swal.fire({
            title: 'Konfirmasi Request Besar',
            html: "Pengajuan barang besar/investasi dengan total estimasi: <br><b class='text-danger'>" + total + "</b>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Proses Sekarang!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Data...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                form.submit();
            }
        });
    });
});
</script>
<script>
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');

    if (pesan === 'berhasil') {
        Swal.fire({
            icon: 'success',
            title: 'BERHASIL DISIMPAN!',
            text: 'Data Purchase Request telah berhasil masuk ke sistem.',
            confirmButtonColor: '#0000FF'
        });
    }
    
    // Penting: Hapus parameter dari URL agar notifikasi tidak muncul lagi saat refresh
    window.history.replaceState({}, document.title, window.location.pathname);
});
</script>
</body>
</html>