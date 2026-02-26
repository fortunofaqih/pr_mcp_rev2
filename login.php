<?php
session_start();
include 'config/koneksi.php';

/* ======================================
   CEGAH USER SUDAH LOGIN MASUK LOGIN LAGI
====================================== */
if (isset($_SESSION['status']) && $_SESSION['status'] == 'login') {
    header("location:index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mutiara Cahaya Plastindo</title>
    <link rel="icon" type="image/png" href="<?= $base_url; ?>assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --mcp-blue: #0000FF;
            --mcp-dark: #00008B;
        }

        body {
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background-color: white;
            border-bottom: none;
            padding-top: 30px;
            text-align: center;
        }

        .logo-img {
            max-width: 120px;
            margin-bottom: 15px;
        }

        .btn-mcp {
            background-color: var(--mcp-blue);
            color: white;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 12px;
            border-radius: 8px;
            transition: 0.3s;
        }

        .btn-mcp:hover {
            background-color: var(--mcp-dark);
            color: white;
            transform: translateY(-2px);
        }

        .form-control {
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: var(--mcp-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 255, 0.1);
        }

        .footer-text {
            font-size: 0.85rem;
            color: #666;
            text-align: center;
            margin-top: 20px;
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }
    </style>
</head>

<body>

<div class="login-card card">
    <div class="card-header">
        <img src="assets/logo-1.png" alt="MCP Logo" class="logo-img">
        <h4 class="fw-bold" style="color: var(--mcp-blue);">PURCHASE SYSTEM</h4>
        <p class="text-muted small">Mutiaracahaya Plastindo</p>
    </div>

    <div class="card-body px-4 pb-4">

        <form action="auth/cek_login.php" method="POST">

            <!-- PESAN ERROR -->
            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
                <div class="alert alert-danger text-center small py-2">
                    USERNAME ATAU PASSWORD SALAH!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'nonaktif'): ?>
                <div class="alert alert-warning text-center small py-2">
                    AKUN ANDA TIDAK AKTIF. HUBUNGI ADMINISTRATOR.
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label small fw-bold">USERNAME</label>
                <input type="text" name="username" class="form-control"
                       placeholder="MASUKKAN USERNAME" required autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">PASSWORD</label>
                <div class="password-container">
                    <input type="password" name="password" id="password"
                           class="form-control" placeholder="MASUKKAN PASSWORD" required>
                    <i class="fa-solid fa-eye toggle-password" id="eyeIcon"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-mcp w-100">
                MASUK KE SISTEM
            </button>
        </form>

        <div class="footer-text">
            &copy; 2026 MUTIARACAHAYA PLASTINDO
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    eyeIcon.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password'
            ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>
