
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

function rupiah($angka) {
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

// FILTER LAPORAN
$cari = trim($_GET['cari'] ?? '');
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

$pesan = '';

$valid_awal = $tanggal_awal === '' ||
    (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal);

$valid_akhir = $tanggal_akhir === '' ||
    (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir);

if (!$valid_awal || !$valid_akhir) {
    $tanggal_awal = '';
    $tanggal_akhir = '';
    $pesan = "Format tanggal tidak valid.";
}

if (
    $tanggal_awal !== '' &&
    $tanggal_akhir !== '' &&
    $tanggal_awal > $tanggal_akhir
) {
    $pesan = "Tanggal awal tidak boleh melebihi tanggal akhir.";
    $tanggal_awal = '';
    $tanggal_akhir = '';
}

// QUERY LAPORAN
$sql = "
    SELECT
        p.id_peminjaman,
        p.kode_peminjaman,
        p.tanggal_pengajuan,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status,
        u.nama,
        u.kelas,
        pg.tanggal_dikembalikan,
        COALESCE(pg.total_denda, 0) AS total_denda
    FROM peminjaman p
    JOIN pengguna u ON p.id_user = u.id_user
    LEFT JOIN pengembalian pg
        ON p.id_peminjaman = pg.id_peminjaman
    WHERE 1 = 1
";

$tipe = '';
$nilai = [];

// Filter tanggal berdasarkan tanggal pengajuan
if ($tanggal_awal !== '') {
    $sql .= " AND DATE(p.tanggal_pengajuan) >= ?";
    $tipe .= "s";
    $nilai[] = $tanggal_awal;
}

if ($tanggal_akhir !== '') {
    $sql .= " AND DATE(p.tanggal_pengajuan) <= ?";
    $tipe .= "s";
    $nilai[] = $tanggal_akhir;
}

// Pencarian
if ($cari !== '') {
    $sql .= " AND (
        p.kode_peminjaman LIKE ? OR
        u.nama LIKE ? OR
        u.kelas LIKE ?
    )";

    $keyword = "%$cari%";
    $tipe .= "sss";
    $nilai[] = $keyword;
    $nilai[] = $keyword;
    $nilai[] = $keyword;
}

$sql .= " ORDER BY p.tanggal_pengajuan DESC, p.id_peminjaman DESC";

$query = $koneksi->prepare($sql);

if ($tipe !== '') {
    $query->bind_param($tipe, ...$nilai);
}

$query->execute();
$laporan = $query->get_result();

// HITUNG STATISTIK DARI DATA YANG SUDAH DIFILTER
$total_peminjaman = 0;
$total_selesai = 0;
$total_dipinjam = 0;
$total_denda = 0;

while ($r = $laporan->fetch_assoc()) {
    $total_peminjaman++;

    if ($r['status'] === 'Selesai') {
        $total_selesai++;
    }

    if ($r['status'] === 'Dipinjam') {
        $total_dipinjam++;
    }

    $total_denda += (float) $r['total_denda'];
}

$laporan->data_seek(0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laporan Peminjaman - TECHLOAN</title>

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

        .form-control {
            border-color: #d8c4ae;
        }

        @media print {
            body {
                background: white;
                color: black;
            }

            .navbar,
            .filter-laporan,
            .aksi,
            .statistik {
                display: none !important;
            }

            .container-fluid {
                padding: 0 !important;
            }

            .card {
                border: none;
                box-shadow: none !important;
            }

            .judul {
                color: black;
            }

            table {
                font-size: 10px;
            }

            .table thead th {
                background: #eee !important;
                color: black !important;
            }
        }
    </style>
</head>

<body>

<nav class="navbar px-4 py-3">
    <div class="container-fluid">
        <a href="index.php" class="fw-bold fs-5">TECHLOAN</a>

        <div class="text-white">
            <?= e($_SESSION['nama'] ?? 'Admin') ?> |
            <a href="index.php">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">

    <div class="mb-4">
        <h3 class="judul">Laporan Peminjaman Alat</h3>
        <p class="text-secondary mb-0">
            Rekap data peminjaman dan pengembalian alat.
        </p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-warning">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4 statistik">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-secondary">Total Peminjaman</div>
                    <h3 class="judul mb-0">
                        <?= $total_peminjaman ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-secondary">Selesai</div>
                    <h3 class="judul mb-0">
                        <?= $total_selesai ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-secondary">Masih Dipinjam</div>
                    <h3 class="judul mb-0">
                        <?= $total_dipinjam ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="judul mb-0">Data Laporan</h5>

                <button onclick="window.print()"
                        class="btn btn-cokelat aksi">
                    Cetak Laporan
                </button>
            </div>

            <form method="get" class="row g-3 mb-4 filter-laporan">
                <div class="col-lg-3">
                    <label class="form-label">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal"
                           class="form-control"
                           value="<?= e($tanggal_awal) ?>">
                </div>

                <div class="col-lg-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir"
                           class="form-control"
                           value="<?= e($tanggal_akhir) ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Cari Peminjaman</label>
                    <input type="text" name="cari"
                           class="form-control"
                           placeholder="Kode, nama, atau kelas"
                           value="<?= e($cari) ?>">
                </div>

                <div class="col-lg-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-cokelat">
                        Filter
                    </button>

                    <a href="laporan.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <p class="text-secondary">
                Periode tanggal berdasarkan tanggal pengajuan.
                Total denda adalah denda keterlambatan yang tercatat pada pengembalian.
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode Peminjaman</th>
                            <th>Nama Peminjam</th>
                            <th>Kelas</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Tanggal Pinjam</th>
                            <th>Batas Kembali</th>
                            <th>Tanggal Dikembalikan</th>
                            <th>Status</th>
                            <th>Denda</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($laporan->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = $laporan->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td><?= e($row['kode_peminjaman']) ?></td>

                                <td><?= e($row['nama']) ?></td>

                                <td><?= e($row['kelas'] ?? '-') ?></td>

                                <td><?= e($row['tanggal_pengajuan']) ?></td>

                                <td><?= e($row['tanggal_pinjam']) ?></td>

                                <td><?= e($row['tanggal_kembali']) ?></td>

                                <td>
                                    <?= $row['tanggal_dikembalikan']
                                        ? e($row['tanggal_dikembalikan'])
                                        : '-' ?>
                                </td>

                                <td>
                                    <?php
                                    $warna = 'secondary';

                                    if ($row['status'] === 'Selesai') {
                                        $warna = 'success';
                                    } elseif ($row['status'] === 'Dipinjam') {
                                        $warna = 'warning';
                                    } elseif ($row['status'] === 'Disetujui') {
                                        $warna = 'primary';
                                    } elseif ($row['status'] === 'Ditolak') {
                                        $warna = 'danger';
                                    }
                                    ?>

                                    <span class="badge bg-<?= e($warna) ?>">
                                        <?= e($row['status']) ?>
                                    </span>
                                </td>

                                <td><?= e(rupiah($row['total_denda'])) ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <tr class="fw-bold">
                            <td colspan="9" class="text-end">
                                Total Denda
                            </td>
                            <td><?= e(rupiah($total_denda)) ?></td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="10"
                                class="text-center text-secondary">
                                Data laporan tidak ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end mt-4">
                <p class="mb-1">Mengetahui,</p>
                <p class="mb-5">Admin TECHLOAN</p>
                <p class="fw-bold mb-0">
                    <?= e($_SESSION['nama'] ?? 'Admin') ?>
                </p>
            </div>

        </div>
    </div>

</div>

</body>
</html>
