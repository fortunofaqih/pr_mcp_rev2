<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
// Proteksi Halaman
if ($_SESSION['status'] != "login" || ($_SESSION['role'] != 'manager' )) {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Antrean Approval - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-mcp shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../../index.php"><i class="fas fa-rotate-left me-2"></i> KEMBALI KE DASHBOARD</a>
        <span class="navbar-text text-white">Pimpinan: <strong><?= $_SESSION['nama'] ?></strong></span>
    </div>
</nav>

<div class="container">
    <h3 class="fw-bold mb-4">Daftar Antrean PR (Besar)</h3>
    
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">No. Request</th>
                        <th>Tanggal</th>
                        <th>Pemesan</th>
                        <th>Keperluan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE kategori_pr = 'BESAR' AND status_approval = 'PENDING' ORDER BY tgl_request ASC");
                    if (mysqli_num_rows($query) > 0) {
                        while ($data = mysqli_fetch_array($query)) {
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary"><?= $data['no_request'] ?></td>
                        <td><?= date('d/m/Y', strtotime($data['tgl_request'])) ?></td>
                        <td><?= $data['nama_pemesan'] ?></td>
                        <td><?= $data['keterangan'] ?></td>
                        <td class="text-center">
                            <a href="approval_pimpinan_detail.php?id=<?= $data['id_request'] ?>" class="btn btn-primary btn-sm px-4 shadow-sm">
                                Review Detail <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>Belum ada request besar yang memerlukan persetujuan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>