<?php
include '../../config/koneksi.php';

if (!isset($_GET['id'])) { exit('ID tidak ditemukan'); }
$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Query ambil detail PR, join ke master_barang untuk memastikan ID Barang terbawa
$q = mysqli_query($koneksi, "SELECT rd.*, m.plat_nomor, b.id_barang, b.nama_barang as nama_master
                             FROM tr_request_detail rd
                             LEFT JOIN master_mobil m ON rd.id_mobil = m.id_mobil
                             LEFT JOIN master_barang b ON rd.id_barang = b.id_barang
                             WHERE rd.id_request = '$id' 
                             AND rd.status_item IN ('PENDING', 'APPROVED')
                             AND rd.status_item != 'TERBELI'"); 

if(mysqli_num_rows($q) == 0){
    echo '<tr><td colspan="11" class="text-center text-danger py-3">Tidak ada item yang perlu direalisasikan.</td></tr>';
    exit;
}

while($d = mysqli_fetch_array($q)){
    $kat_db = strtoupper($d['kategori_barang'] ?? ''); 
    $tipe_pr = strtoupper($d['tipe_request'] ?? ''); 
    $subtotal = $d['jumlah'] * $d['harga_satuan_estimasi'];
    $unit = ($d['plat_nomor'] != "") ? $d['plat_nomor'] : "-";
    
    // Gunakan nama dari master jika ada, jika tidak gunakan input manual PR
    $nama_tampil = ($d['nama_master'] != "") ? $d['nama_master'] : $d['nama_barang_manual'];

    echo '<tr class="baris-beli">
        <td>
            <input type="hidden" name="id_request_detail[]" value="'.$d['id_detail'].'">
            <input type="hidden" name="id_barang[]" value="'.$d['id_barang'].'">
            <input type="text" name="tgl_beli_barang[]" class="form-control form-control-sm b-tanggal" value="'.date('d-m-Y').'" required>
        </td>
        <td>
            <input type="text" name="nama_barang[]" class="form-control form-control-sm bg-light fw-bold" value="'.strtoupper($nama_tampil).'" readonly>
        </td>
        <td>
            <input type="text" name="plat_nomor[]" class="form-control form-control-sm bg-light text-center" value="'.$unit.'" readonly>
        </td>
        <td><input type="text" name="supplier[]" class="form-control form-control-sm" required onkeyup="this.value = this.value.toUpperCase()"></td>
        <td><input type="number" name="qty[]" class="form-control form-control-sm b-qty text-center" step="0.01" value="'.(float)$d['jumlah'].'" required></td>
        <td><input type="number" name="harga_satuan[]" class="form-control form-control-sm b-harga text-end" value="'.$d['harga_satuan_estimasi'].'" required></td>
        <td>
            <select name="kategori_beli[]" class="form-select form-select-sm" required>
                <option value="BENGKEL MOBIL" '.($kat_db == "BENGKEL MOBIL" ? "selected" : "").'>BENGKEL MOBIL</option>
                <option value="KANTOR" '.($kat_db == "KANTOR" ? "selected" : "").'>KANTOR</option>
                <option value="UMUM" '.($kat_db == "UMUM" ? "selected" : "").'>UMUM</option>
            </select>
        </td>
        <td>
            <select name="alokasi_stok[]" class="form-select form-select-sm">
                <option value="LANGSUNG PAKAI" '.($tipe_pr == "LANGSUNG" ? "selected" : "").'>LANGSUNG PAKAI</option>
                <option value="MASUK STOK" '.($tipe_pr == "STOK" ? "selected" : "").'>MASUK STOK</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm b-total bg-light fw-bold text-end" value="'.number_format($subtotal, 0, ',', '.').'" readonly></td>
        <td><input type="text" name="keterangan[]" class="form-control form-control-sm text-uppercase" value="'.strtoupper($d['keterangan']).'" required></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-baris border-0"><i class="fas fa-times"></i></button></td>
    </tr>';
}
?>