
<?php
session_start();
include "../koneksi.php";

// HANYA PEMINJAM
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Peminjam') {
    header("Location: ../login.php");
    exit;
}

$id_user = $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Peminjam';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT p.*, pg.tanggal_dikembalikan, pg.total_denda
        FROM peminjaman p
        LEFT JOIN pengembalian pg
            ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.id_user = ?";

if ($cari != '') {
    $sql .= " AND (p.kode_peminjaman LIKE ? OR p.status LIKE ?)";
}

$sql .= " ORDER BY p.id_peminjaman DESC";

$stmt = mysqli_prepare($koneksi, $sql);

if ($cari != '') {
    $keyword = "%$cari%";
    mysqli_stmt_bind_param($stmt, "iss", $id_user, $keyword, $keyword);
} else {
    mysqli_stmt_bind_param($stmt, "i", $id_user);
}

mysqli_stmt_execute($stmt);
$data_peminjaman = mysqli_stmt_get_result($stmt);

function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function warnaStatus($status) {
    switch ($status) {
        case 'Menunggu':
            return 'menunggu';
        case 'Disetujui':
            return 'disetujui';
        case 'Dipinjam':
            return 'dipinjam';
        case 'Selesai':
            return 'selesai';
        case 'Ditolak':
            return 'ditolak';
        default:
            return 'menunggu';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Saya - TECHLOAN</title>
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
            width: 235px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #3f2b20;
            padding: 25px 16px;
            color: white;
        }

        .brand {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .brand-info {
            font-size: 12px;
            color: #d7c5b5;
            margin-bottom: 30px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e8ddd3;
            text-decoration: none;
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .menu-link:hover,
        .menu-link.active {
            background: #79583f;
            color: white;
        }

        .menu-link svg {
            width: 18px;
            height: 18px;
        }

        .keluar {
            position: absolute;
            bottom: 22px;
            left: 16px;
            right: 16px;
        }

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

        .topbar h4 {
            margin: 0;
            font-weight: bold;
        }

        .user-info {
            background: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 13px;
        }

        .judul {
            background: linear-gradient(120deg, #50392c, #8b674e);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 22px;
        }

        .judul h3 {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .judul p {
            margin: 0;
            color: #f0e4d9;
            font-size: 14px;
        }

        .panel {
            background: white;
            padding: 22px;
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(63, 43, 32, 0.06);
            margin-bottom: 20px;
        }

        .form-control {
            border-color: #e3d6c9;
            padding: 10px 12px;
        }

        .btn-cokelat {
            background: #604532;
            color: white;
            border: none;
        }

        .btn-cokelat:hover {
            background: #493324;
            color: white;
        }

        .tabel {
            width: 100%;
            border-collapse: collapse;
        }

        .tabel th {
            background: #f4ece3;
            color: #604532;
            padding: 12px;
            font-size: 13px;
            text-align: left;
            white-space: nowrap;
        }

        .tabel td {
            padding: 13px 12px;
            border-bottom: 1px solid #eee5dc;
            font-size: 13px;
            vertical-align: top;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .menunggu {
            background: #fff0c2;
            color: #896000;
        }

        .disetujui {
            background: #dceafe;
            color: #2456a6;
        }

        .dipinjam {
            background: #fce2c9;
            color: #97521b;
        }

        .selesai {
            background: #d9f1df;
            color: #236b37;
        }

        .ditolak {
            background: #f8dada;
            color: #a12b2b;
        }

        .kode {
            font-weight: bold;
            color: #604532;
        }

        .detail-alat {
            margin: 0;
            padding-left: 17px;
        }

        .detail-alat li {
            margin-bottom: 5px;
        }

        .kosong {
            text-align: center;
            padding: 35px 10px;
            color: #88786c;
        }

        .peringatan {
            color: #a12b2b;
            font-size: 11px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 65px;
                padding: 20px 8px;
            }

            .brand {
                font-size: 12px;
                overflow-wrap: anywhere;
            }

            .brand-info,
            .menu-link span {
                display: none;
            }

            .menu-link {
                justify-content: center;
                padding: 13px 5px;
            }

            .keluar {
                left: 8px;
                right: 8px;
            }

            .main {
                margin-left: 65px;
                padding: 15px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .panel {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">TECHLOAN</div>
    <div class="brand-info">Sistem Peminjaman Alat</div>

    <a href="index.php" class="menu-link">
        <i data-lucide="layout-dashboard"></i>
        <span>Dashboard</span>
    </a>

    <a href="daftar_alat.php" class="menu-link">
        <i data-lucide="package"></i>
        <span>Daftar Alat</span>
    </a>

    <a href="peminjaman_saya.php" class="menu-link active">
        <i data-lucide="clipboard-list"></i>
        <span>Peminjaman Saya</span>
    </a>

    <a href="../logout.php" class="menu-link keluar">
        <i data-lucide="log-out"></i>
        <span>Keluar</span>
    </a>
</aside>

<main class="main">

    <div class="topbar">
        <h4>Peminjaman Saya</h4>
        <div class="user-info">
            <i data-lucide="user-round" style="width:15px;height:15px;vertical-align:middle"></i>
            <?= e($nama) ?> · Peminjam
        </div>
    </div>

    <div class="judul">
        <h3>Riwayat Peminjaman</h3>
        <p>Lihat daftar pengajuan, alat yang dipinjam, status, dan informasi pengembalianmu.</p>
    </div>

    <?php if (isset($_GET['berhasil'])): ?>
        <div class="alert alert-success">
            Pengajuan peminjaman berhasil dikirim dan sedang menunggu persetujuan Petugas.
        </div>
    <?php endif; ?>

    <div class="panel">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-9">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari kode peminjaman atau status..."
                    value="<?= e($cari) ?>"
                >
            </div>

            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-cokelat">
                    <i data-lucide="search" style="width:16px;height:16px;vertical-align:middle"></i>
                    Cari
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode & Tanggal</th>
                        <th>Alat Dipinjam</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Pengembalian</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;

                if (mysqli_num_rows($data_peminjaman) > 0):
                    while ($p = mysqli_fetch_assoc($data_peminjaman)):
                ?>
                    <tr>
                        <td><?= $no++ ?></td>

                        <td>
                            <div class="kode"><?= e($p['kode_peminjaman']) ?></div>
                            <small class="text-muted">
                                Diajukan:
                                <?= !empty($p['tanggal_pengajuan'])
                                    ? date('d-m-Y', strtotime($p['tanggal_pengajuan']))
                                    : '-' ?>
                            </small>
                        </td>

                        <td>
                            <?php
                            $id_peminjaman = (int) $p['id_peminjaman'];

                            $sql_detail = "SELECT a.nama_alat, a.kode_alat,
                                                  dp.jumlah_pinjam
                                           FROM detail_peminjaman dp
                                           JOIN alat a ON dp.id_alat = a.id_alat
                                           WHERE dp.id_peminjaman = ?";

                            $stmt_detail = mysqli_prepare($koneksi, $sql_detail);
                            mysqli_stmt_bind_param($stmt_detail, "i", $id_peminjaman);
                            mysqli_stmt_execute($stmt_detail);
                            $data_detail = mysqli_stmt_get_result($stmt_detail);
                            ?>

                            <ul class="detail-alat">
                                <?php while ($d = mysqli_fetch_assoc($data_detail)): ?>
                                    <li>
                                        <?= e($d['nama_alat']) ?>
                                        <br>
                                        <small class="text-muted">
                                            <?= e($d['kode_alat']) ?> ·
                                            <?= (int) $d['jumlah_pinjam'] ?> unit
                                        </small>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </td>

                        <td>
                            <small>Pinjam:</small><br>
                            <strong>
                                <?= date('d-m-Y', strtotime($p['tanggal_pinjam'])) ?>
                            </strong>
                            <br><br>

                            <small>Kembali:</small><br>
                            <strong>
                                <?= date('d-m-Y', strtotime($p['tanggal_kembali'])) ?>
                            </strong>

                            <?php if (
                                $p['status'] === 'Dipinjam' &&
                                strtotime(date('Y-m-d')) > strtotime($p['tanggal_kembali'])
                            ): ?>
                                <div class="peringatan">Melewati tanggal kembali</div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status <?= warnaStatus($p['status']) ?>">
                                <?= e($p['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($p['tanggal_dikembalikan'])): ?>
                                <small>Tanggal kembali:</small><br>
                                <strong>
                                    <?= date('d-m-Y', strtotime($p['tanggal_dikembalikan'])) ?>
                                </strong>
                                <br><br>
                                <small>Total denda:</small><br>
                                <strong>
                                    Rp<?= number_format((float) ($p['total_denda'] ?? 0), 0, ',', '.') ?>
                                </strong>
                            <?php elseif ($p['status'] === 'Ditolak'): ?>
                                <span class="text-muted">Pengajuan ditolak</span>
                            <?php elseif ($p['status'] === 'Selesai'): ?>
                                <span class="text-muted">Selesai</span>
                            <?php else: ?>
                                <span class="text-muted">Belum dikembalikan</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="6">
                            <div class="kosong">
                                <i data-lucide="clipboard-list" style="width:35px;height:35px"></i>
                                <p class="mt-2 mb-1">Belum ada data peminjaman.</p>
                                <small>Ajukan peminjaman melalui menu Daftar Alat.</small>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>

</body>
</html>
