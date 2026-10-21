
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Peminjam') {
    header("Location: ../login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Peminjam';

function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

$pesan = "";
$jenis_pesan = "danger";
$cari = trim($_GET['cari'] ?? '');

// PROSES PENGAJUAN PEMINJAMAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan'])) {
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';
    $jumlah_alat = $_POST['jumlah'] ?? [];

    if (!is_array($jumlah_alat)) {
        $jumlah_alat = [];
    }

    $alat_dipilih = [];

    foreach ($jumlah_alat as $id_alat => $jumlah) {
        $id_alat = filter_var($id_alat, FILTER_VALIDATE_INT);
        $jumlah = filter_var($jumlah, FILTER_VALIDATE_INT);

        if ($id_alat && $jumlah && $jumlah > 0) {
            $alat_dipilih[$id_alat] = $jumlah;
        }
    }

    if (
        !$tanggal_pinjam ||
        !$tanggal_kembali ||
        $tanggal_kembali <= $tanggal_pinjam
    ) {
        $pesan = "Tanggal kembali harus setelah tanggal pinjam.";
    } elseif (empty($alat_dipilih)) {
        $pesan = "Pilih minimal satu alat dan masukkan jumlahnya.";
    } else {
        $tanggal_sekarang = date('Y-m-d');

        if ($tanggal_pinjam < $tanggal_sekarang) {
            $pesan = "Tanggal pinjam tidak boleh sebelum hari ini.";
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                // KODE PEMINJAMAN
                $kode = "PJM" . date('YmdHis') . random_int(100, 999);

                // SIMPAN PENGAJUAN
                $sql = "INSERT INTO peminjaman
                        (kode_peminjaman, id_user, tanggal_pengajuan,
                         tanggal_pinjam, tanggal_kembali, status, catatan)
                        VALUES (?, ?, CURDATE(), ?, ?, 'Menunggu', NULL)";

                $stmt = mysqli_prepare($koneksi, $sql);
                mysqli_stmt_bind_param(
                    $stmt,
                    "siss",
                    $kode,
                    $id_user,
                    $tanggal_pinjam,
                    $tanggal_kembali
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Pengajuan gagal disimpan.");
                }

                $id_peminjaman = mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);

                // SIMPAN DETAIL ALAT
                foreach ($alat_dipilih as $id_alat => $jumlah) {
                    $sql_alat = "SELECT id_alat, nama_alat, stok
                                 FROM alat
                                 WHERE id_alat = ?
                                 FOR UPDATE";

                    $stmt_alat = mysqli_prepare($koneksi, $sql_alat);
                    mysqli_stmt_bind_param($stmt_alat, "i", $id_alat);
                    mysqli_stmt_execute($stmt_alat);

                    $hasil_alat = mysqli_stmt_get_result($stmt_alat);
                    $alat = mysqli_fetch_assoc($hasil_alat);
                    mysqli_stmt_close($stmt_alat);

                    if (!$alat) {
                        throw new Exception("Ada alat yang tidak ditemukan.");
                    }

                    if ($jumlah > (int) $alat['stok']) {
                        throw new Exception(
                            "Stok alat " . $alat['nama_alat'] .
                            " tidak mencukupi."
                        );
                    }

                    $sql_detail = "INSERT INTO detail_peminjaman
                                   (id_peminjaman, id_alat, jumlah_pinjam)
                                   VALUES (?, ?, ?)";

                    $stmt_detail = mysqli_prepare($koneksi, $sql_detail);
                    mysqli_stmt_bind_param(
                        $stmt_detail,
                        "iii",
                        $id_peminjaman,
                        $id_alat,
                        $jumlah
                    );

                    if (!mysqli_stmt_execute($stmt_detail)) {
                        throw new Exception("Detail alat gagal disimpan.");
                    }

                    mysqli_stmt_close($stmt_detail);
                }

                mysqli_commit($koneksi);

                header("Location: peminjaman_saya.php?berhasil=1");
                exit;

            } catch (Throwable $error) {
                mysqli_rollback($koneksi);
                $pesan = $error->getMessage();
            }
        }
    }
}

// DAFTAR ALAT
$sql_alat = "SELECT
                a.id_alat,
                a.kode_alat,
                a.nama_alat,
                a.merk,
                a.stok,
                a.kondisi,
                a.lokasi,
                k.nama_kategori
             FROM alat a
             LEFT JOIN kategori k
                ON a.id_kategori = k.id_kategori
             WHERE a.stok > 0";

if ($cari !== '') {
    $sql_alat .= " AND (
        a.nama_alat LIKE ?
        OR a.kode_alat LIKE ?
        OR a.merk LIKE ?
        OR k.nama_kategori LIKE ?
    )";
}

$sql_alat .= " ORDER BY a.nama_alat ASC";

$stmt_alat = mysqli_prepare($koneksi, $sql_alat);

if ($cari !== '') {
    $keyword = "%" . $cari . "%";
    mysqli_stmt_bind_param(
        $stmt_alat,
        "ssss",
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );
}

mysqli_stmt_execute($stmt_alat);
$result_alat = mysqli_stmt_get_result($stmt_alat);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Daftar Alat - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            margin: 0;
            background: #f7f2ea;
            color: #49372b;
            font-family: Arial, sans-serif;
        }

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
        }

        .menu-link:hover,
        .menu-link.active {
            background: #72533e;
            color: white;
        }

        .menu-link svg {
            width: 18px;
            height: 18px;
        }

        .main {
            margin-left: 235px;
            padding: 28px;
        }

        .header-card {
            background: #ead5bd;
            border: 1px solid #d8b998;
            border-radius: 15px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .header-card h3 {
            font-weight: bold;
        }

        .panel {
            background: white;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #eee3d7;
            box-shadow: 0 4px 12px rgba(75, 52, 35, 0.05);
            margin-bottom: 22px;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
        }

        .btn-cokelat {
            background: #5b4030;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
        }

        .btn-cokelat:hover {
            background: #765640;
            color: white;
        }

        .btn-reset {
            background: #eee5db;
            color: #49372b;
            border-radius: 8px;
            padding: 10px 16px;
            text-decoration: none;
        }

        .table thead th {
            background: #ead5bd;
            color: #49372b;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .jumlah-input {
            width: 85px;
            min-width: 70px;
        }

        .stok-badge {
            display: inline-block;
            background: #e2eddd;
            color: #355b30;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
        }

        @media (max-width: 850px) {
            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 18px;
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
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <div class="brand">
        <h4>TECHLOAN</h4>
        <small>Sistem Peminjaman Alat</small>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <a href="index.php" class="menu-link">
        <i data-lucide="layout-dashboard"></i>
        <span>Dashboard</span>
    </a>

    <a href="daftar_alat.php" class="menu-link active">
        <i data-lucide="package"></i>
        <span>Daftar Alat</span>
    </a>

    <a href="peminjaman_saya.php" class="menu-link">
        <i data-lucide="clipboard-list"></i>
        <span>Peminjaman Saya</span>
    </a>

    <div class="menu-title">AKUN</div>

    <a href="../logout.php" class="menu-link"
       onclick="return confirm('Yakin ingin keluar?')">
        <i data-lucide="log-out"></i>
        <span>Keluar</span>
    </a>
</div>

<div class="main">

    <div class="header-card">
        <h3>Daftar Alat</h3>
        <p class="mb-0">
            Pilih alat yang ingin dipinjam, tentukan jumlah dan tanggalnya,
            lalu kirim pengajuan kepada Petugas.
        </p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= e($jenis_pesan) ?> alert-dismissible fade show">
            <?= e($pesan) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h5 class="mb-3">Cari Alat</h5>

        <form method="GET" class="row g-2">
            <div class="col-md-9">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari nama, kode, merek, atau kategori alat..."
                    value="<?= e($cari) ?>"
                >
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn-cokelat">
                    Cari
                </button>

                <a href="daftar_alat.php" class="btn-reset">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <form method="POST" id="formPeminjaman">

        <div class="panel">
            <h5 class="mb-3">Pilih Alat</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode</th>
                            <th>Nama Alat</th>
                            <th>Kategori</th>
                            <th>Merek</th>
                            <th>Kondisi</th>
                            <th>Lokasi</th>
                            <th>Stok Tersedia</th>
                            <th>Jumlah Pinjam</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (mysqli_num_rows($result_alat) > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($alat = mysqli_fetch_assoc($result_alat)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($alat['kode_alat']) ?></td>
                                <td><?= e($alat['nama_alat']) ?></td>
                                <td><?= e($alat['nama_kategori'] ?? '-') ?></td>
                                <td><?= e($alat['merk'] ?: '-') ?></td>
                                <td><?= e($alat['kondisi']) ?></td>
                                <td><?= e($alat['lokasi'] ?: '-') ?></td>

                                <td>
                                    <span class="stok-badge">
                                        <?= (int) $alat['stok'] ?>
                                    </span>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        name="jumlah[<?= (int) $alat['id_alat'] ?>]"
                                        class="form-control jumlah-input"
                                        min="0"
                                        max="<?= (int) $alat['stok'] ?>"
                                        value="0"
                                    >
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                Tidak ada alat tersedia yang cocok dengan pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h5 class="mb-3">Tanggal Peminjaman</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tanggal Pinjam</label>
                    <input
                        type="date"
                        name="tanggal_pinjam"
                        class="form-control"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tanggal Kembali</label>
                    <input
                        type="date"
                        name="tanggal_kembali"
                        class="form-control"
                        min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                        required
                    >
                </div>
            </div>

            <div class="alert alert-warning mt-3 mb-3">
                Pastikan jumlah alat dan tanggal yang dipilih sudah benar.
                Pengajuan akan berstatus Menunggu sampai diperiksa Petugas.
            </div>

            <button
                type="submit"
                name="ajukan"
                value="1"
                class="btn-cokelat"
                onclick="return konfirmasiPeminjaman()"
            >
                <i data-lucide="send" class="me-1"
                   style="width:16px;height:16px;"></i>
                Ajukan Peminjaman
            </button>

            <a href="index.php" class="btn-reset ms-1">
                Kembali
            </a>
        </div>

    </form>

</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    function konfirmasiPeminjaman() {
        const form = document.getElementById('formPeminjaman');
        const inputs = form.querySelectorAll('input[name^="jumlah["]');
        let dipilih = false;

        inputs.forEach(function(input) {
            if (parseInt(input.value, 10) > 0) {
                dipilih = true;
            }
        });

        if (!dipilih) {
            alert('Pilih minimal satu alat dan masukkan jumlahnya.');
            return false;
        }

        const pinjam = form.querySelector('[name="tanggal_pinjam"]').value;
        const kembali = form.querySelector('[name="tanggal_kembali"]').value;

        if (!pinjam || !kembali || kembali <= pinjam) {
            alert('Tanggal kembali harus setelah tanggal pinjam.');
            return false;
        }

        return confirm('Yakin ingin mengajukan peminjaman alat?');
    }

    const tanggalPinjam = document.querySelector('[name="tanggal_pinjam"]');
    const tanggalKembali = document.querySelector('[name="tanggal_kembali"]');

    tanggalPinjam.addEventListener('change', function() {
        if (this.value) {
            const tanggal = new Date(this.value + 'T00:00:00');
            tanggal.setDate(tanggal.getDate() + 1);

            const tahun = tanggal.getFullYear();
            const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
            const hari = String(tanggal.getDate()).padStart(2, '0');
            const minimal = `${tahun}-${bulan}-${hari}`;

            tanggalKembali.min = minimal;

            if (tanggalKembali.value && tanggalKembali.value < minimal) {
                tanggalKembali.value = minimal;
            }
        }
    });
</script>

</body>
</html>

<?php
mysqli_stmt_close($stmt_alat);
?>
