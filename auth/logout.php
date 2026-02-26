<?php
// Memulai session
session_start();

// Menghapus semua variabel session
session_unset();

// Menghancurkan session yang ada
session_destroy();

// Mengarahkan kembali ke halaman login dengan pesan logout
header("location:../login.php?pesan=logout");
exit;
?>