<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'Admin') {
    header("Location: ../login.php");
    exit;
}

// MENGHITUNG DATA
$alat = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM alat"
));

$pengguna = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM pengguna"
));

$menunggu = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM peminjaman
     WHERE status='Menunggu'"
));

$disetujui = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total FROM peminjaman
     WHERE status='Disetujui'"
));

// MENGHITUNG DENDA BELUM LUNAS
$denda = mysqli_fetch_assoc(mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM pembayaran_denda
     WHERE status='Belum Dibayar'"
));

$nama = htmlspecialchars(
    $_SESSION['nama'] ?? 'Admin',
    ENT_QUOTES,
    'UTF-8'
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Admin - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f7f2eb;
            color: #403027;
        }

        .sidebar {
            background: #3c2b23;
            min-height: 100vh;
            color: white;
        }

        .brand {
            color: #e7c39d;
            font-weight: bold;
            font-size: 23px;
        }

        .menu {
            display: block;
            color: #e9ddd2;
            text-decoration: none;
            padding: 11px 14px;
            border-radius: 9px;
            margin-bottom: 5px;
        }

        .menu:hover,
        .menu.aktif {
            background: #705342;
            color: white;
        }

        .konten {
            padding: 28px;
        }

        .sambutan {
            background: linear-gradient(120deg, #50392d, #89674f);
            color: white;
            padding: 28px;
            border-radius: 18px;
        }

        .statistik {
            background: white;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #eee3d7;
            height: 100%;
        }

        .ikon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1e4d5;
            border-radius: 12px;
            font-size: 23px;
        }

        .angka {
            font-size: 29px;
            font-weight: bold;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
            }

            .konten {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 sidebar p-3">

            <div class="brand mb-1">▣ TECHLOAN</div>
            <small class="text-white-50">
                Technology Lending System
            </small>

            <hr>

            <small class="text-white-50">MENU UTAMA</small>

            <a href="index.php" class="menu aktif">
                ⌂ Dashboard
            </a>

            <small class="text-white-50 d-block mt-4 mb-2">
                PENGELOLAAN
            </small>

            <a href="pengguna.php" class="menu">
                ♙ Data Pengguna
            </a>

            <a href="kategori.php" class="menu">
                ▤ Kategori Alat
            </a>

            <a href="alat.php" class="menu">
                ▣ Data Alat
            </a>

            <a href="peminjaman.php" class="menu">
                ▧ Data Peminjaman
            </a>

            <a href="pengembalian.php" class="menu">
                ↩ Pengembalian
            </a>

            <a href="pembayaran_denda.php" class="menu">
                💰 Pembayaran Denda
                <?php if ((int)$denda['total'] > 0): ?>
                    <span class="badge bg-warning text-dark float-end">
                        <?= (int)$denda['total']; ?>
                    </span>
                <?php endif; ?>
            </a>

            <small class="text-white-50 d-block mt-4 mb-2">
                LAINNYA
            </small>

            <a href="log_aktivitas.php" class="menu">
                ◷ Log Aktivitas
            </a>

            <a href="laporan.php" class="menu">
                ▥ Laporan
            </a>

            <hr>

            <a href="../logout.php"
               class="menu"
               onclick="return confirm('Yakin ingin logout?')">
                ⇥ Logout
            </a>

        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <div class="d-flex justify-content-between
                        align-items-center mb-4">

                <div>
                    <h4 class="fw-bold mb-1">Dashboard</h4>
                    <small class="text-muted">
                        Ringkasan sistem peminjaman alat
                    </small>
                </div>

                <div class="text-end">
                    <strong><?= $nama; ?></strong><br>
                    <small class="text-muted">Administrator</small>
                </div>

            </div>

            <!-- SAMBUTAN -->
            <div class="sambutan mb-4">

                <small style="color:#e7c39d;">
                    ADMIN DASHBOARD
                </small>

                <h2 class="fw-bold mt-2">
                    Selamat datang, <?= $nama; ?>!
                </h2>

                <p class="mb-0">
                    Kelola perangkat, pengguna, peminjaman,
                    pengembalian, dan pembayaran denda
                    melalui TECHLOAN.
                </p>

            </div>

            <!-- STATISTIK -->
            <h5 class="fw-bold mb-3">Ringkasan Sistem</h5>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">
                    <div class="statistik">
                        <div class="ikon mb-3">▣</div>
                        <div class="text-muted small">Total Alat</div>
                        <div class="angka">
                            <?= (int)$alat['total']; ?>
                        </div>
                        <small class="text-muted">
                            Jenis data alat
                        </small>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="statistik">
                        <div class="ikon mb-3">♙</div>
                        <div class="text-muted small">
                            Total Pengguna
                        </div>
                        <div class="angka">
                            <?= (int)$pengguna['total']; ?>
                        </div>
                        <small class="text-muted">
                            Semua akun
                        </small>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="statistik">
                        <div class="ikon mb-3">◷</div>
                        <div class="text-muted small">Menunggu</div>
                        <div class="angka">
                            <?= (int)$menunggu['total']; ?>
                        </div>
                        <small class="text-muted">
                            Perlu diperiksa
                        </small>
                    </div>
                </div>

                <div class="col-6 col-xl-3">
                    <div class="statistik">
                        <div class="ikon mb-3">✓</div>
                        <div class="text-muted small">Disetujui</div>
                        <div class="angka">
                            <?= (int)$disetujui['total']; ?>
                        </div>
                        <small class="text-muted">
                            Peminjaman disetujui
                        </small>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="text-center mt-5">
                <small class="text-muted">
                    © <?= date('Y'); ?> TECHLOAN
                    — Technology Lending System
                </small>
            </div>

        </div>
    </div>
</div>

</body>
</html>
