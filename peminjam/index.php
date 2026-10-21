<?php
session_start();
include "../koneksi.php";

// HANYA PEMINJAM
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Peminjam') {
    header("Location: ../login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Peminjam';

// FUNGSI AMAN
function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// STATISTIK PEMINJAMAN
$sql_statistik = "SELECT
    COUNT(*) AS total,
    SUM(status = 'Menunggu') AS menunggu,
    SUM(status = 'Disetujui') AS disetujui,
    SUM(status = 'Dipinjam') AS dipinjam,
    SUM(status = 'Selesai') AS selesai,
    SUM(status = 'Ditolak') AS ditolak
    FROM peminjaman
    WHERE id_user = ?";

$stmt_statistik = mysqli_prepare($koneksi, $sql_statistik);
mysqli_stmt_bind_param($stmt_statistik, "i", $id_user);
mysqli_stmt_execute($stmt_statistik);

$result_statistik = mysqli_stmt_get_result($stmt_statistik);
$statistik = mysqli_fetch_assoc($result_statistik);

$total = (int) ($statistik['total'] ?? 0);
$menunggu = (int) ($statistik['menunggu'] ?? 0);
$disetujui = (int) ($statistik['disetujui'] ?? 0);
$dipinjam = (int) ($statistik['dipinjam'] ?? 0);
$selesai = (int) ($statistik['selesai'] ?? 0);
$ditolak = (int) ($statistik['ditolak'] ?? 0);

// RIWAYAT TERBARU
$sql_riwayat = "SELECT
    id_peminjaman,
    kode_peminjaman,
    tanggal_pengajuan,
    tanggal_pinjam,
    tanggal_kembali,
    status
    FROM peminjaman
    WHERE id_user = ?
    ORDER BY id_peminjaman DESC
    LIMIT 5";

$stmt_riwayat = mysqli_prepare($koneksi, $sql_riwayat);
mysqli_stmt_bind_param($stmt_riwayat, "i", $id_user);
mysqli_stmt_execute($stmt_riwayat);

$result_riwayat = mysqli_stmt_get_result($stmt_riwayat);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Peminjam - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f7f2ea;
            font-family: Arial, sans-serif;
            color: #49372b;
        }

        /* SIDEBAR */
        .sidebar {
            width: 235px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #3f2b20;
            color: white;
            padding: 25px 16px;
            overflow-y: auto;
        }

        .brand {
            padding: 0 10px 25px;
            border-bottom: 1px solid #705847;
            margin-bottom: 22px;
        }

        .brand h4 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .brand small {
            color: #dfc9b2;
        }

        .menu-title {
            color: #c9ae94;
            font-size: 11px;
            margin: 20px 10px 10px;
            letter-spacing: 1px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 6px;
            border-radius: 8px;
            color: #f4e9dd;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu-link:hover,
        .menu-link.active {
            background: #72533e;
            color: white;
        }

        .menu-link svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            stroke-width: 1.8;
        }

        /* KONTEN */
        .main {
            margin-left: 235px;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar h5 {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .topbar small {
            color: #8b7969;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            background: #5b4030;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background: #765640;
            color: white;
        }

        .btn-logout svg {
            width: 16px;
            height: 16px;
        }

        /* BANNER */
        .welcome {
            background: linear-gradient(120deg, #50392c, #8b674e);
            color: white;
            padding: 28px;
            border-radius: 16px;
            margin-bottom: 25px;
        }

        .welcome h3 {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #f2e6da;
            margin-bottom: 18px;
            line-height: 1.7;
        }

        .btn-alat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ead5bd;
            color: #49372b;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 13px;
        }

        .btn-alat:hover {
            background: #f5e6d5;
            color: #49372b;
        }

        .btn-alat svg {
            width: 17px;
            height: 17px;
        }

        /* STATISTIK */
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 16px;
        }

        .stat-card {
            background: white;
            border-radius: 13px;
            padding: 20px;
            height: 100%;
            border: 1px solid #eee3d7;
            box-shadow: 0 4px 12px rgba(75, 52, 35, 0.05);
        }

        .stat-label {
            color: #867465;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #49372b;
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 43px;
            height: 43px;
            border-radius: 11px;
            background: #f1e3d3;
            color: #62442f;
        }

        .stat-icon svg {
            width: 21px;
            height: 21px;
        }

        /* RIWAYAT */
        .panel {
            background: white;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #eee3d7;
            box-shadow: 0 4px 12px rgba(75, 52, 35, 0.05);
            margin-top: 25px;
        }

        .btn-semua {
            color: #60432f;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-semua:hover {
            color: #967052;
        }

        .table thead th {
            background: #ead5bd;
            color: #49372b;
            white-space: nowrap;
            font-size: 13px;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            white-space: nowrap;
        }

        .menunggu {
            background: #fff0c9;
            color: #805b10;
        }

        .disetujui {
            background: #dce8f7;
            color: #244f83;
        }

        .ditolak {
            background: #f4d6d2;
            color: #8a3027;
        }

        .dipinjam {
            background: #f8e4bd;
            color: #805510;
        }

        .selesai {
            background: #dcebd9;
            color: #315d31;
        }

        .empty {
            padding: 30px !important;
            color: #8b7969;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 20px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main {
                margin-left: 0;
                padding: 16px;
            }

            .topbar {
                align-items: flex-start;
            }

            .welcome {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="brand">
        <h4>TECHLOAN</h4>
        <small>Sistem Peminjaman Alat</small>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <a href="index.php" class="menu-link active">
        <i data-lucide="layout-dashboard"></i>
        <span>Dashboard</span>
    </a>

    <a href="daftar_alat.php" class="menu-link">
        <i data-lucide="package"></i>
        <span>Daftar Alat</span>
    </a>

    <a href="peminjaman_saya.php" class="menu-link">
        <i data-lucide="clipboard-list"></i>
        <span>Peminjaman Saya</span>
    </a>

    <!-- MENU PENGEMBALIAN ALAT -->
    <a href="pengembalian_saya.php" class="menu-link">
        <i data-lucide="package-check"></i>
        <span>Pengembalian Alat</span>
    </a>

    <div class="menu-title">AKUN</div>

    <a href="../logout.php" class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i data-lucide="log-out"></i>
        <span>Keluar</span>
    </a>

</div>

<!-- KONTEN UTAMA -->
<div class="main">

    <div class="topbar">
        <div>
            <h5>Dashboard Peminjam</h5>
            <small>Sistem Informasi Peminjaman Alat</small>
        </div>

        <a href="../logout.php"
           class="btn-logout"
           onclick="return confirm('Yakin ingin keluar?')">
            <i data-lucide="log-out"></i>
            Keluar
        </a>
    </div>

    <!-- SAMBUTAN -->
    <div class="welcome">
        <h3>Selamat Datang, <?= e($nama) ?>!</h3>

        <p>
            Selamat datang di TECHLOAN. Kamu dapat melihat alat yang
            tersedia, mengajukan peminjaman, dan memantau status
            pengajuanmu melalui dashboard ini.
        </p>

        <a href="daftar_alat.php" class="btn-alat">
            <i data-lucide="package-search"></i>
            Lihat Daftar Alat
        </a>
    </div>

    <!-- STATISTIK -->
    <h5 class="section-title">Ringkasan Peminjaman</h5>

    <div class="row g-3">

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Pengajuan</div>
                        <div class="stat-number"><?= $total ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="files"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Menunggu</div>
                        <div class="stat-number"><?= $menunggu ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="clock-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Disetujui</div>
                        <div class="stat-number"><?= $disetujui ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Sedang Dipinjam</div>
                        <div class="stat-number"><?= $dipinjam ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="package-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Selesai</div>
                        <div class="stat-number"><?= $selesai ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="check-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Ditolak</div>
                        <div class="stat-number"><?= $ditolak ?></div>
                    </div>
                    <div class="stat-icon">
                        <i data-lucide="x-circle"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- RIWAYAT TERBARU -->
    <div class="panel">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="section-title mb-0">Peminjaman Terbaru</h5>

            <a href="peminjaman_saya.php" class="btn-semua">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Peminjaman</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($result_riwayat) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($result_riwayat)): ?>
                        <?php
                        $class_status = 'menunggu';

                        if ($data['status'] === 'Disetujui') {
                            $class_status = 'disetujui';
                        } elseif ($data['status'] === 'Ditolak') {
                            $class_status = 'ditolak';
                        } elseif ($data['status'] === 'Dipinjam') {
                            $class_status = 'dipinjam';
                        } elseif ($data['status'] === 'Selesai') {
                            $class_status = 'selesai';
                        }
                        ?>

                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($data['kode_peminjaman']) ?></td>
                            <td><?= e($data['tanggal_pengajuan'] ?: '-') ?></td>
                            <td><?= e($data['tanggal_pinjam'] ?: '-') ?></td>
                            <td><?= e($data['tanggal_kembali'] ?: '-') ?></td>
                            <td>
                                <span class="status <?= e($class_status) ?>">
                                    <?= e($data['status']) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center empty">
                            <i data-lucide="clipboard-list"
                               style="width:30px;height:30px;margin-bottom:10px;"></i>
                            <br>
                            Kamu belum memiliki pengajuan peminjaman.
                            <br><br>

                            <a href="daftar_alat.php" class="btn-alat">
                                <i data-lucide="package-search"></i>
                                Mulai Pinjam Alat
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>

    <p class="text-center mt-4 mb-2"
       style="color:#927e6c;font-size:12px;">
        TECHLOAN &copy; <?= date('Y') ?> - Sistem Informasi Peminjaman Alat
    </p>

</div>

<!-- IKON LUCIDE -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>

</body>
</html>

<?php
mysqli_stmt_close($stmt_statistik);
mysqli_stmt_close($stmt_riwayat);
?>