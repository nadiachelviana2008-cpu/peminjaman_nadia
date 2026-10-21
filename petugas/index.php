
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'Petugas') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Petugas - TECHLOAN</title>
    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f7f2ea;
            color: #3f2b20;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 225px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #3f2b20;
            padding: 22px 16px;
            overflow-y: auto;
        }

        .brand {
            color: #e9c9a7;
            font-size: 22px;
            font-weight: bold;
        }

        .brand small {
            display: block;
            color: #aab4c2;
            font-size: 13px;
            font-weight: normal;
            margin-top: 12px;
        }

        .menu-label {
            color: #aab4c2;
            font-size: 12px;
            margin: 22px 0 8px;
        }

        .sidebar a {
            display: block;
            padding: 13px 12px;
            margin-bottom: 5px;
            border-radius: 8px;
            text-decoration: none;
            color: #fff;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.aktif {
            background: #705342;
        }

        .ikon {
            display: inline-block;
            width: 20px;
            text-align: center;
            margin-right: 4px;
        }

        .konten {
            margin-left: 225px;
            padding: 30px 28px;
            min-height: 100vh;
        }

        .subjudul {
            color: #526783;
            font-size: 14px;
        }

        .banner {
            background: linear-gradient(110deg, #50392c, #8b674e);
            color: white;
            padding: 30px;
            border-radius: 18px;
            margin: 28px 0 25px;
        }

        .banner small {
            color: #e8c6a3;
        }

        .banner h1 {
            font-size: 30px;
            font-weight: bold;
            margin: 14px 0 8px;
        }

        .panel {
            background: white;
            border: 1px solid #eadfd2;
            border-radius: 15px;
            padding: 25px;
        }

        .panel h5 {
            font-weight: bold;
            margin-bottom: 12px;
        }

        .menu-aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .menu-aksi a {
            display: inline-block;
            background: #6f4e37;
            color: white;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .menu-aksi a:hover {
            background: #50392c;
        }

        .footer {
            text-align: center;
            color: #526783;
            font-size: 13px;
            margin-top: 50px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .konten {
                margin-left: 0;
                padding: 20px 15px;
            }

            .banner h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        ▣ TECHLOAN
        <small>Technology Lending System</small>
    </div>

    <hr style="border-color:#806b5c; margin:20px 0;">

    <div class="menu-label">MENU UTAMA</div>

    <a href="/UKK/peminjaman_nadia/petugas/index.php" class="aktif">
        <span class="ikon">⌂</span> Dashboard
    </a>

    <div class="menu-label">PENGELOLAAN</div>

    <a href="/UKK/peminjaman_nadia/petugas/persetujuan.php">
        <span class="ikon">♙</span> Persetujuan Peminjaman
    </a>

    <a href="/UKK/peminjaman_nadia/petugas/monitor_pengembalian.php">
        <span class="ikon">↩</span> Monitor Pengembalian
    </a>

    <div class="menu-label">LAINNYA</div>

    <a href="/UKK/peminjaman_nadia/logout.php"
       onclick="return confirm('Yakin ingin logout?')">
        <span class="ikon">⇥</span> Logout
    </a>
</aside>

<main class="konten">

    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-1">Dashboard</h3>
            <div class="subjudul">
                Sistem Informasi Peminjaman Alat
            </div>
        </div>

        <div class="text-end">
            <strong>Petugas</strong>
            <div class="subjudul">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </div>
        </div>
    </div>

    <section class="banner">
        <small>PETUGAS DASHBOARD</small>

        <h1>
            Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?>!
        </h1>

        <p class="mb-0">
            Periksa pengajuan peminjaman dan pantau pengembalian alat
            dengan mudah melalui TECHLOAN.
        </p>
    </section>

    <div class="panel shadow-sm">
        <h5>Pengelolaan Peminjaman Alat</h5>

        <p class="subjudul mb-0">
            Gunakan menu berikut untuk memeriksa pengajuan peminjaman
            dan memantau proses pengembalian alat.
        </p>

        <div class="menu-aksi">
            <a href="/UKK/peminjaman_nadia/petugas/persetujuan.php">
                Persetujuan Peminjaman
            </a>

            <a href="/UKK/peminjaman_nadia/petugas/monitor_pengembalian.php">
                Monitor Pengembalian
            </a>
        </div>
    </div>

    <div class="footer">
        © <?= date('Y') ?> TECHLOAN — Technology Lending System
    </div>

</main>

</body>
</html>
