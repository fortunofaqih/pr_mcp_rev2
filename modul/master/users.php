<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
/* =======================
   PROTEKSI HALAMAN
======================= */
if (!isset($_SESSION['status']) || $_SESSION['status'] != 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SESSION['role'] != 'administrator') {
    header("location:../../index.php");
    exit;
}

/* =======================
   PROSES TAMBAH USER
======================= */
if (isset($_POST['simpan'])) {
    $username      = mysqli_real_escape_string($koneksi, $_POST['username']);
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $password      = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role          = $_POST['role'];
    $bagian        = $_POST['bagian'];

    mysqli_query($koneksi, "
        INSERT INTO users 
        (username,password,nama_lengkap,role,bagian,status_aktif)
        VALUES
        ('$username','$password','$nama_lengkap','$role','$bagian','aktif')
    ");

    header("location:users.php?pesan=berhasil");
    exit;
}

/* =======================
   PROSES UPDATE USER
======================= */
if (isset($_POST['update'])) {
    $id_user      = $_POST['id_user'];
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $role         = $_POST['role'];
    $bagian       = $_POST['bagian'];
    $status       = $_POST['status_aktif'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($koneksi, "
            UPDATE users SET
            nama_lengkap='$nama_lengkap',
            password='$password',
            role='$role',
            bagian='$bagian',
            status_aktif='$status'
            WHERE id_user='$id_user'
        ");
    } else {
        mysqli_query($koneksi, "
            UPDATE users SET
            nama_lengkap='$nama_lengkap',
            role='$role',
            bagian='$bagian',
            status_aktif='$status'
            WHERE id_user='$id_user'
        ");
    }

    header("location:users.php?pesan=update");
    exit;
}

/* =======================
   PROSES HAPUS USER
======================= */
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($koneksi, "DELETE FROM users WHERE id_user='$id'");
    header("location:users.php?pesan=hapus");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manajemen User</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
   :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; background: var(--mcp-blue); color: white; transition: 0.3s; width: 260px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); font-weight: 500; text-transform: uppercase; font-size: 0.75rem; padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: var(--mcp-dark); }
        .sidebar-heading { padding: 20px; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.1); }
        .nav-category { padding: 15px 20px 5px; font-size: 0.65rem; font-weight: bold; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px; }
        .topbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 15px; }
        .main-content { padding: 30px; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .icon-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
         .logo-mcp {
            width: 32px;        /* ukuran logo */
            height: auto;
        }
         .sidebar-footer {
            margin-top: auto;          /* KUNCI: dorong ke bawah */
            padding: 15px 10px;
            font-size: 0.7rem;
            text-align: center;
            color: rgba(255,255,255,0.7);
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar-footer .heart {
            display: inline-block;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
</style>
</head>

<body>
<div class="d-flex">
<div class="sidebar d-flex flex-column flex-shrink-0 p-0">
    <!-- SIDEBAR -->
    
       <div class="sidebar-heading">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <img src="../../assets/img/logo_mcp.png" alt="MCP Logo" class="logo-mcp">
                <h5 class="m-0 fw-bold">MCP SYSTEM</h5>
            </div>
            <small class="opacity-75 d-block mt-1">INVENTORY & PR</small>
        </div>
       <ul class="nav nav-pills flex-column mb-auto">
          
            <li class="nav-item">
                <a href="users.php" class="nav-link text-warning"><i class="fas fa-users-cog me-2"></i> Manajemen User</a>
            </li>
             <li class="nav-item">
                <a href="log_activity.php" class="nav-link text-warning"><i class="fas fa-file me-2"></i> Activity Log</a>
            </li>
           
       </ul>
        <div class="sidebar-footer">
            &copy; <?= date("Y"); ?> MutiaraCahaya<br>
            Made with <span class="heart">❤️</span> by Team
        </div>
                
            
    </div>
    


    <!-- CONTENT -->
    <div class="flex-grow-1">
        <div class="topbar d-flex justify-content-between">
            <div class="fw-bold text-primary">
                <i class="fas fa-users"></i> Manajemen User Sistem
            </div>
           
            <div class="small text-muted">
                <?= $_SESSION['nama']; ?> | <?= strtoupper($_SESSION['role']); ?>
            </div>
            <div class="small text-muted fw-bold">
                <a href="../../auth/logout.php" class="btn btn-danger btn-sm fw-bold"><i class="fas fa-sign-out-alt me-2"></i> KELUAR</a>
                <i class="far fa-calendar-alt ms-3 me-1"></i> <?php echo date('d F Y'); ?>
            </div>
        </div>
         

        <div class="p-4">

            <!-- ALERT -->
            <?php if(isset($_GET['pesan'])): ?>
                <div class="alert alert-success small">
                    Proses berhasil dilakukan.
                </div>
            <?php endif; ?>

            <!-- FORM TAMBAH USER -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white fw-bold text-primary">
                    Tambah User Baru
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="admin_gudang">admin_gudang</option>
                                    <option value="bagian_pembelian">bagian_pembelian</option>
                                    <option value="manager">manager</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Bagian</label>
                                <select name="bagian" class="form-select" required>
                                    <option value="Gudang">Gudang</option>
                                    <option value="Pembelian">Pembelian</option>
                                    <option value="Manager">Manager</option>
                                    <option value="IT">IT</option>
                                </select>
                            </div>
                        </div>
                        <button name="simpan" class="btn btn-primary fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan User
                        </button>
                    </form>
                </div>
            </div>

            <!-- TABEL USER -->
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold text-primary">
                    Daftar User
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light small">
                            <tr>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Role</th>
                                <th>Bagian</th>
                                <th>Status</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $q = mysqli_query($koneksi,"SELECT * FROM users ORDER BY id_user DESC");
                        while($u = mysqli_fetch_assoc($q)):
                        ?>
                            <tr>
                                <td><?= $u['username']; ?></td>
                                <td><?= $u['nama_lengkap']; ?></td>
                                <td><span class="badge bg-info"><?= $u['role']; ?></span></td>
                                <td><?= $u['bagian']; ?></td>
                                <td>
                                    <span class="badge <?= $u['status_aktif']=='aktif'?'bg-success':'bg-success'; ?>">
                                        <?= strtoupper($u['status_aktif']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="?hapus=<?= $u['id_user']; ?>" 
                                       onclick="return confirm('Hapus user ini?')" 
                                       class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
         
    </div>
   
</div>

</body>
</html>
