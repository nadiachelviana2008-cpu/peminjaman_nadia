
<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? '');
$keyword = "%$cari%";

// AMBIL DATA LOGIN
$query = $koneksi->prepare("
    SELECT
        l.id_log,
        u.nama,
        u.username,
        u.level,
        l.aktivitas,
        l.waktu
    FROM log_aktivitas l
    JOIN pengguna u ON l.id_user = u.id_user
    WHERE l.aktivitas = 'Login ke sistem'
      AND (
          u.nama LIKE ?
          OR u.username LIKE ?
          OR u.level LIKE ?
      )
    ORDER BY l.waktu DESC
");

$query->bind_param("sss", $keyword, $keyword, $keyword);
$query->execute();
$log = $query->get_result();

// JUMLAH LOGIN TERCATAT
$total_login = $koneksi->query("
    SELECT COUNT(*) AS total
    FROM log_aktivitas
    WHERE aktivitas = 'Login ke sistem'
")->fetch_assoc()['total'];

// JUMLAH PENGGUNA YANG PERNAH LOGIN
$total_pengguna = $koneksi->query("
    SELECT COUNT(DISTINCT id_user) AS total
    FROM log_aktivitas
    WHERE aktivitas = 'Login ke sistem'
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Log Aktivitas - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f7f1e8;
            color: #49372b;
        }

        .navbar {
            background: #654832;
        }

        .navbar a {
            color: white;
            text-decoration: none;
        }

        .judul {
            color: #654832;
            font-weight: 700;
        }

        .card {
            border: 1px solid #e4d5c4;
            border-radius: 12px;
        }

        .btn-cokelat {
            background: #654832;
            color: white;
        }

        .btn-cokelat:hover {
            background: #4c3425;
            color: white;
        }

        .table thead th {
            background: #eee1d2;
            color: #49372b;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

<nav class="navbar px-4 py-3">
    <div class="container-fluid">
        <a href="index.php" class="fw-bold fs-5">TECHLOAN</a>

        <div class="text-white">
            <?= e($_SESSION['nama'] ?? 'Admin') ?>
            |
            <a href="index.php">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">

    <div class="mb-4">
        <h3 class="judul">Log Aktivitas Login</h3>
        <p class="text-secondary">
            Riwayat pengguna yang berhasil login ke sistem TECHLOAN.
        </p>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Total Login Tercatat</p>
                    <h3 class="judul mb-0">
                        <?= (int) $total_login ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-secondary mb-1">Pengguna yang Pernah Login</p>
                    <h3 class="judul mb-0">
                        <?= (int) $total_pengguna ?>
                    </h3>
                </div>
            </div>
        </div>

    </div>

    <!-- TABEL LOG LOGIN -->
    <div class="card shadow-sm">
        <div class="card-body p-4">

            <form method="get" class="row g-2 mb-3">
                <div class="col-md-5">
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Cari nama, username, atau level..."
                        value="<?= e($cari) ?>"
                    >
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-cokelat">
                        Cari
                    </button>

                    <a href="log_aktivitas.php"
                       class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Level</th>
                            <th>Aktivitas</th>
                            <th>Waktu Login</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($log->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($data = $log->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td><?= e($data['nama']) ?></td>

                                <td><?= e($data['username']) ?></td>

                                <td>
                                    <?php
                                    $warna = 'secondary';

                                    if ($data['level'] === 'Admin') {
                                        $warna = 'danger';
                                    } elseif ($data['level'] === 'Petugas') {
                                        $warna = 'warning';
                                    } elseif ($data['level'] === 'Peminjam') {
                                        $warna = 'success';
                                    }
                                    ?>

                                    <span class="badge text-bg-<?= e($warna) ?>">
                                        <?= e($data['level']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge text-bg-success">
                                        <?= e($data['aktivitas']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd-m-Y H:i:s',
                                        strtotime($data['waktu'])
                                    )) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6"
                                class="text-center text-secondary py-4">
                                Belum ada data login yang ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

</body>
</html>
