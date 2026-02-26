<?php
session_start();
include '../config/koneksi.php';

// Validasi method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:../login.php");
    exit();
}

// Ambil input
$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$password = $_POST['password'];

// Query user
$query = mysqli_query(
    $koneksi,
    "SELECT * FROM users WHERE username='$username' LIMIT 1"
);

if (mysqli_num_rows($query) === 1) {

    $data = mysqli_fetch_assoc($query);

    // Verifikasi password
    if (password_verify($password, $data['password'])) {

        // Cek status akun
        if ($data['status_aktif'] !== 'AKTIF') {
            header("location:../login.php?pesan=nonaktif");
            exit();
        }

        // Set session
        $_SESSION['id_user']  = $data['id_user'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['nama']     = $data['nama_lengkap'];
        $_SESSION['role']     = $data['role'];
        $_SESSION['bagian']   = $data['bagian'];
        $_SESSION['status']   = 'login';

        // Redirect berdasarkan role
            if ($data['role'] === 'administrator') {
                header("location:../modul/master/users.php");
            } else {
                header("location:../index.php");
            }
            exit();

    } else {
        header("location:../login.php?pesan=gagal");
        exit();
    }

} else {
    header("location:../login.php?pesan=gagal");
    exit();
}
