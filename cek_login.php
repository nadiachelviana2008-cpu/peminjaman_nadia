<?php
session_start();
include "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$query = mysqli_prepare(
    $koneksi,
    "SELECT * FROM pengguna WHERE username = ? LIMIT 1"
);

mysqli_stmt_bind_param($query, "s", $username);
mysqli_stmt_execute($query);

$hasil = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($hasil);

if (!$user || !hash_equals($user['password'], md5($password))) {
    mysqli_stmt_close($query);
    header("Location: login.php?pesan=gagal");
    exit;
}

if ($user['status'] != 'Aktif') {
    mysqli_stmt_close($query);
    header("Location: login.php?pesan=nonaktif");
    exit;
}

session_regenerate_id(true);

$_SESSION['id_user'] = $user['id_user'];
$_SESSION['nama'] = $user['nama'];
$_SESSION['username'] = $user['username'];
$_SESSION['level'] = $user['level'];

mysqli_stmt_close($query);

// CATAT AKTIVITAS LOGIN SEMUA LEVEL
$id_user = (int) $user['id_user'];
$aktivitas = "Login ke sistem";

$log = mysqli_prepare(
    $koneksi,
    "INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
     VALUES (?, ?, NOW())"
);

mysqli_stmt_bind_param($log, "is", $id_user, $aktivitas);
mysqli_stmt_execute($log);
mysqli_stmt_close($log);

// ARAHKAN SESUAI LEVEL
if ($user['level'] == 'Admin') {
    header("Location: admin/index.php");
} elseif ($user['level'] == 'Petugas') {
    header("Location: petugas/index.php");
} elseif ($user['level'] == 'Peminjam') {
    header("Location: peminjam/index.php");
} else {
    session_destroy();
    header("Location: login.php?pesan=gagal");
    exit;
}

exit;
?>
