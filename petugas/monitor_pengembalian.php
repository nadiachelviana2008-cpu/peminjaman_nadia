
<?php
session_start();
include "../koneksi.php";

// HANYA PETUGAS
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Petugas') {
    header("Location: ../login.php");
    exit;
}

// Fungsi untuk mengamankan teks
function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

$cari = trim($_GET['cari'] ?? '');

// QUERY SEMUA DATA PEMINJAMAN
$sql = "SELECT
            p.id_peminjaman,
            p.kode_peminjaman,
            p.tanggal_pengajuan,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            u.nama,
            u.kelas,
            pg.tanggal_dikembalikan,
            pg.total_denda
        FROM peminjaman p
        JOIN pengguna u ON p.id_user = u.id_user
        LEFT JOIN pengembalian pg
            ON p.id_peminjaman = pg.id_peminjaman
        WHERE 1=1";

// PENCARIAN
if ($cari !== '') {
    $sql .= " AND (
        p.kode_peminjaman LIKE ?
        OR u.nama LIKE ?
        OR u.kelas LIKE ?
    )";
}

// URUTKAN DATA
$sql .= " ORDER BY
            CASE
                WHEN p.status = 'Menunggu' THEN 1
                WHEN p.status = 'Disetujui' THEN 2
                WHEN p.status = 'Dipinjam' THEN 3
                WHEN p.status = 'Selesai' THEN 4
                WHEN p.status = 'Ditolak' THEN 5
                ELSE 6
            END,
            p.tanggal_kembali ASC";

$stmt = mysqli_prepare($koneksi, $sql);

if (!$stmt) {
    die("Terjadi kesalahan saat menyiapkan data.");
}

if ($cari !== '') {
    $keyword = "%".$cari."%";
    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $keyword,
        $keyword,
        $keyword
    );
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Monitor Pengembalian</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f7f2ea;
            font-family: Arial, sans-serif;
            color: #49372b;
        }

        .container-main {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 18px;
        }

        .header-card {
            background: #ead5bd;
            border: 1px solid #d8b998;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 22px;
        }

        .header-card h3 {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .btn-kembali {
            display: inline-block;
            background: white;
            color: #49372b;
            border: 1px solid #cbb49c;
            border-radius: 8px;
            padding: 9px 15px;
            text-decoration: none;
            margin-top: 12px;
        }

        .btn-kembali:hover {
            background: #5b4030;
            color: white;
        }

        .panel {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(75, 52, 35, 0.08);
        }

        .form-control {
            border-radius: 8px;
        }

        .btn-cari {
            background: #5b4030;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
        }

        .btn-cari:hover {
            background: #765640;
            color: white;
        }

        .btn-reset {
            background: #eee5db;
            color: #49372b;
            border-radius: 8px;
            padding: 9px 16px;
            text-decoration: none;
        }

        .table thead th {
            background: #ead5bd;
            color: #49372b;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #fff0c9;
            color: #805b10;
        }

        .status-disetujui {
            background: #dce8f7;
            color: #244f83;
        }

        .status-ditolak {
            background: #f4d6d2;
            color: #8a3027;
        }

        .status-dipinjam {
            background: #f8e4bd;
            color: #805510;
        }

        .status-selesai {
            background: #dcebd9;
            color: #315d31;
        }

        .status-terlambat {
            background: #f4d6d2;
            color: #8a3027;
        }

        .kosong {
            padding: 30px !important;
            color: #88796c;
        }

        @media (max-width: 768px) {
            .container-main {
                margin-top: 18px;
            }

            .panel {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<div class="container-main">

    <!-- HEADER -->
    <div class="header-card">
        <h3>Monitor Pengembalian</h3>

        <p class="mb-0">
            Pantau seluruh data peminjaman dan status pengembalian alat.
        </p>

        <a href="/UKK/peminjaman_nadia/petugas/index.php"
           class="btn-kembali">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- DATA PEMINJAMAN -->
    <div class="panel">

        <h5 class="mb-3">Data Peminjaman dan Pengembalian</h5>

        <!-- PENCARIAN -->
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-9">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari kode peminjaman, nama, atau kelas..."
                    value="<?= e($cari) ?>"
                >
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn-cari">
                    Cari
                </button>

                <a href="monitor_pengembalian.php" class="btn-reset">
                    Reset
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Kelas</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Tanggal Pinjam</th>
                        <th>Batas Pengembalian</th>
                        <th>Tanggal Dikembalikan</th>
                        <th>Status</th>
                        <th>Total Denda</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($result)): ?>

                        <?php
                        $status = $data['status'];

                        $terlambat =
                            $status === 'Dipinjam'
                            && date('Y-m-d') > $data['tanggal_kembali'];

                        $class_status = 'status-menunggu';

                        if ($status === 'Disetujui') {
                            $class_status = 'status-disetujui';
                        } elseif ($status === 'Ditolak') {
                            $class_status = 'status-ditolak';
                        } elseif ($status === 'Dipinjam') {
                            $class_status = 'status-dipinjam';
                        } elseif ($status === 'Selesai') {
                            $class_status = 'status-selesai';
                        }
                        ?>

                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= e($data['kode_peminjaman']) ?>
                            </td>

                            <td>
                                <?= e($data['nama']) ?>
                            </td>

                            <td>
                                <?= e($data['kelas'] ?: '-') ?>
                            </td>

                            <td>
                                <?= e($data['tanggal_pengajuan'] ?: '-') ?>
                            </td>

                            <td>
                                <?= e($data['tanggal_pinjam'] ?: '-') ?>
                            </td>

                            <td>
                                <?= e($data['tanggal_kembali'] ?: '-') ?>
                            </td>

                            <td>
                                <?= e($data['tanggal_dikembalikan'] ?: '-') ?>
                            </td>

                            <td>
                                <?php if ($terlambat): ?>
                                    <span class="badge-status status-terlambat">
                                        Terlambat
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status <?= e($class_status) ?>">
                                        <?= e($status) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                Rp <?= number_format(
                                    (float) ($data['total_denda'] ?? 0),
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center kosong">
                            Tidak ada data peminjaman yang ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <small class="text-muted">
            Halaman ini hanya untuk pemantauan. Persetujuan peminjaman
            dilakukan melalui menu Persetujuan, sedangkan proses
            pengembalian dilakukan oleh Admin.
        </small>

    </div>
</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
