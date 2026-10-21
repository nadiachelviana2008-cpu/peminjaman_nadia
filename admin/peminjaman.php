
<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// FUNGSI AMAN
function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// PINDAH HALAMAN
function pindah($pesan)
{
    header("Location: peminjaman.php?pesan=" . urlencode($pesan));
    exit;
}

// TOKEN KEAMANAN
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* =========================
   PROSES FORM
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid. Muat ulang halaman.");
    }

    $aksi = $_POST['aksi'] ?? '';

    /* =========================
       TAMBAH PEMINJAMAN
    ========================= */

    if ($aksi === 'tambah') {
        $id_user = (int) ($_POST['id_user'] ?? 0);
        $tgl_pinjam = $_POST['tanggal_pinjam'] ?? '';
        $tgl_kembali = $_POST['tanggal_kembali'] ?? '';
        $catatan = trim($_POST['catatan'] ?? '');
        $pilih = $_POST['pilih'] ?? [];
        $jumlah = $_POST['jumlah'] ?? [];

        if (
            $id_user < 1 ||
            !$tgl_pinjam ||
            !$tgl_kembali ||
            $tgl_kembali <= $tgl_pinjam ||
            !is_array($pilih) ||
            count($pilih) < 1 ||
            !is_array($jumlah)
        ) {
            pindah('data_tidak_valid');
        }

        mysqli_begin_transaction($koneksi);

        try {
            // CEK PEMINJAM
            $cek_user = mysqli_prepare(
                $koneksi,
                "SELECT id_user
                 FROM pengguna
                 WHERE id_user = ?
                 AND level = 'Peminjam'
                 AND status = 'Aktif'"
            );

            mysqli_stmt_bind_param($cek_user, "i", $id_user);
            mysqli_stmt_execute($cek_user);

            $res_user = mysqli_stmt_get_result($cek_user);
            $valid_user = $res_user &&
                mysqli_num_rows($res_user) === 1;

            if ($res_user) {
                mysqli_free_result($res_user);
            }

            mysqli_stmt_close($cek_user);

            if (!$valid_user) {
                throw new Exception("Peminjam tidak valid.");
            }

            // BUAT KODE PEMINJAMAN
            $kode = 'PMJ' . date('YmdHis') . random_int(100, 999);

            // SIMPAN PEMINJAMAN
            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO peminjaman
                (
                    kode_peminjaman,
                    id_user,
                    tanggal_pengajuan,
                    tanggal_pinjam,
                    tanggal_kembali,
                    status,
                    catatan
                )
                VALUES (?, ?, CURDATE(), ?, ?, 'Menunggu', ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sisss",
                $kode,
                $id_user,
                $tgl_pinjam,
                $tgl_kembali,
                $catatan
            );

            mysqli_stmt_execute($stmt);
            $id_peminjaman = mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmt);

            $ada_alat = false;

            // SIMPAN DETAIL ALAT
            foreach ($pilih as $id => $nilai) {
                $id_alat = (int) $id;
                $jml = (int) ($jumlah[$id] ?? 0);

                if ($id_alat < 1 || $jml < 1) {
                    throw new Exception("Jumlah alat tidak valid.");
                }

                $cek = mysqli_prepare(
                    $koneksi,
                    "SELECT stok, jumlah_baik
                     FROM alat
                     WHERE id_alat = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param($cek, "i", $id_alat);
                mysqli_stmt_execute($cek);

                $res_alat = mysqli_stmt_get_result($cek);
                $alat = $res_alat
                    ? mysqli_fetch_assoc($res_alat)
                    : null;

                if ($res_alat) {
                    mysqli_free_result($res_alat);
                }

                mysqli_stmt_close($cek);

                if (
                    !$alat ||
                    $jml > (int) $alat['stok'] ||
                    $jml > (int) $alat['jumlah_baik']
                ) {
                    throw new Exception(
                        "Stok alat baik tidak mencukupi."
                    );
                }

                $detail = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO detail_peminjaman
                    (
                        id_peminjaman,
                        id_alat,
                        jumlah_pinjam
                    )
                    VALUES (?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $detail,
                    "iii",
                    $id_peminjaman,
                    $id_alat,
                    $jml
                );

                mysqli_stmt_execute($detail);
                mysqli_stmt_close($detail);

                $ada_alat = true;
            }

            if (!$ada_alat) {
                throw new Exception("Pilih minimal satu alat.");
            }

            // CATAT AKTIVITAS
            $id_admin = (int) $_SESSION['id_user'];
            $aktivitas = "Menambahkan peminjaman " . $kode;

            $log = mysqli_prepare(
                $koneksi,
                "INSERT INTO log_aktivitas
                (id_user, aktivitas, waktu)
                VALUES (?, ?, NOW())"
            );

            mysqli_stmt_bind_param(
                $log,
                "is",
                $id_admin,
                $aktivitas
            );

            mysqli_stmt_execute($log);
            mysqli_stmt_close($log);

            mysqli_commit($koneksi);
            pindah('berhasil_tambah');

        } catch (Throwable $ex) {
            try {
                mysqli_rollback($koneksi);
            } catch (Throwable $abaikan) {
            }

            if (strpos($ex->getMessage(), 'Stok') !== false) {
                pindah('stok_tidak_cukup');
            }

            if (strpos($ex->getMessage(), 'Peminjam') !== false) {
                pindah('peminjam_tidak_valid');
            }

            pindah('gagal');
        }
    }

    /* =========================
       EDIT PEMINJAMAN
    ========================= */

    if ($aksi === 'edit') {
        $id = (int) ($_POST['id_peminjaman'] ?? 0);
        $id_user = (int) ($_POST['id_user'] ?? 0);
        $tgl_pinjam = $_POST['tanggal_pinjam'] ?? '';
        $tgl_kembali = $_POST['tanggal_kembali'] ?? '';
        $catatan = trim($_POST['catatan'] ?? '');
        $pilih = $_POST['pilih'] ?? [];
        $jumlah = $_POST['jumlah'] ?? [];

        if (
            $id < 1 ||
            $id_user < 1 ||
            !$tgl_pinjam ||
            !$tgl_kembali ||
            $tgl_kembali <= $tgl_pinjam ||
            !is_array($pilih) ||
            count($pilih) < 1 ||
            !is_array($jumlah)
        ) {
            pindah('data_tidak_valid');
        }

        mysqli_begin_transaction($koneksi);

        try {
            // CEK STATUS PEMINJAMAN
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT status
                 FROM peminjaman
                 WHERE id_peminjaman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($cek, "i", $id);
            mysqli_stmt_execute($cek);

            $res = mysqli_stmt_get_result($cek);
            $data = $res ? mysqli_fetch_assoc($res) : null;

            if ($res) {
                mysqli_free_result($res);
            }

            mysqli_stmt_close($cek);

            if (!$data || $data['status'] !== 'Menunggu') {
                throw new Exception("Status tidak dapat diedit.");
            }

            // CEK PEMINJAM
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT id_user
                 FROM pengguna
                 WHERE id_user = ?
                 AND level = 'Peminjam'
                 AND status = 'Aktif'"
            );

            mysqli_stmt_bind_param($cek, "i", $id_user);
            mysqli_stmt_execute($cek);

            $res = mysqli_stmt_get_result($cek);
            $valid_user = $res &&
                mysqli_num_rows($res) === 1;

            if ($res) {
                mysqli_free_result($res);
            }

            mysqli_stmt_close($cek);

            if (!$valid_user) {
                throw new Exception("Peminjam tidak valid.");
            }

            // UPDATE DATA PEMINJAMAN
            $update = mysqli_prepare(
                $koneksi,
                "UPDATE peminjaman
                 SET id_user = ?,
                     tanggal_pinjam = ?,
                     tanggal_kembali = ?,
                     catatan = ?
                 WHERE id_peminjaman = ?
                 AND status = 'Menunggu'"
            );

            mysqli_stmt_bind_param(
                $update,
                "isssi",
                $id_user,
                $tgl_pinjam,
                $tgl_kembali,
                $catatan,
                $id
            );

            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            // HAPUS DETAIL LAMA
            $hapus = mysqli_prepare(
                $koneksi,
                "DELETE FROM detail_peminjaman
                 WHERE id_peminjaman = ?"
            );

            mysqli_stmt_bind_param($hapus, "i", $id);
            mysqli_stmt_execute($hapus);
            mysqli_stmt_close($hapus);

            // SIMPAN DETAIL BARU
            foreach ($pilih as $id_alat => $nilai) {
                $id_alat = (int) $id_alat;
                $jml = (int) ($jumlah[$id_alat] ?? 0);

                if ($id_alat < 1 || $jml < 1) {
                    throw new Exception("Jumlah alat tidak valid.");
                }

                $cek = mysqli_prepare(
                    $koneksi,
                    "SELECT stok, jumlah_baik
                     FROM alat
                     WHERE id_alat = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param($cek, "i", $id_alat);
                mysqli_stmt_execute($cek);

                $res = mysqli_stmt_get_result($cek);
                $alat = $res ? mysqli_fetch_assoc($res) : null;

                if ($res) {
                    mysqli_free_result($res);
                }

                mysqli_stmt_close($cek);

                if (
                    !$alat ||
                    $jml > (int) $alat['stok'] ||
                    $jml > (int) $alat['jumlah_baik']
                ) {
                    throw new Exception(
                        "Stok alat baik tidak mencukupi."
                    );
                }

                $detail = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO detail_peminjaman
                    (id_peminjaman, id_alat, jumlah_pinjam)
                    VALUES (?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $detail,
                    "iii",
                    $id,
                    $id_alat,
                    $jml
                );

                mysqli_stmt_execute($detail);
                mysqli_stmt_close($detail);
            }

            mysqli_commit($koneksi);
            pindah('berhasil_edit');

        } catch (Throwable $ex) {
            try {
                mysqli_rollback($koneksi);
            } catch (Throwable $abaikan) {
            }

            if (strpos($ex->getMessage(), 'Stok') !== false) {
                pindah('stok_tidak_cukup');
            }

            pindah('gagal');
        }
    }

    /* =========================
       HAPUS PEMINJAMAN
    ========================= */

    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id_peminjaman'] ?? 0);

        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT kode_peminjaman, status
                 FROM peminjaman
                 WHERE id_peminjaman = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($cek, "i", $id);
            mysqli_stmt_execute($cek);

            $res = mysqli_stmt_get_result($cek);
            $data = $res ? mysqli_fetch_assoc($res) : null;

            if ($res) {
                mysqli_free_result($res);
            }

            mysqli_stmt_close($cek);

            if (
                !$data ||
                !in_array(
                    $data['status'],
                    ['Menunggu', 'Ditolak'],
                    true
                )
            ) {
                throw new Exception("Data sudah diproses.");
            }

            // HAPUS DETAIL
            $hapus_detail = mysqli_prepare(
                $koneksi,
                "DELETE FROM detail_peminjaman
                 WHERE id_peminjaman = ?"
            );

            mysqli_stmt_bind_param($hapus_detail, "i", $id);
            mysqli_stmt_execute($hapus_detail);
            mysqli_stmt_close($hapus_detail);

            // HAPUS PEMINJAMAN
            $hapus = mysqli_prepare(
                $koneksi,
                "DELETE FROM peminjaman
                 WHERE id_peminjaman = ?"
            );

            mysqli_stmt_bind_param($hapus, "i", $id);
            mysqli_stmt_execute($hapus);
            mysqli_stmt_close($hapus);

            mysqli_commit($koneksi);
            pindah('berhasil_hapus');

        } catch (Throwable $ex) {
            try {
                mysqli_rollback($koneksi);
            } catch (Throwable $abaikan) {
            }

            pindah('gagal_hapus');
        }
    }
}

/* =========================
   DATA FORM EDIT
========================= */

$edit = null;
$detail_edit = [];

if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT *
         FROM peminjaman
         WHERE id_peminjaman = ?
         AND status = 'Menunggu'"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);
    $edit = $res ? mysqli_fetch_assoc($res) : null;

    if ($res) {
        mysqli_free_result($res);
    }

    mysqli_stmt_close($stmt);

    if ($edit) {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id_alat, jumlah_pinjam
             FROM detail_peminjaman
             WHERE id_peminjaman = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_edit);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt);

        while ($d = mysqli_fetch_assoc($res)) {
            $detail_edit[(int) $d['id_alat']] =
                (int) $d['jumlah_pinjam'];
        }

        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }
}

/* =========================
   DATA PEMINJAM
========================= */

$pengguna = mysqli_query(
    $koneksi,
    "SELECT id_user, nama
     FROM pengguna
     WHERE level = 'Peminjam'
     AND status = 'Aktif'
     ORDER BY nama"
);

$daftar_pengguna = [];

while ($u = mysqli_fetch_assoc($pengguna)) {
    $daftar_pengguna[] = $u;
}

mysqli_free_result($pengguna);

/* =========================
   DATA ALAT
========================= */

$hasil_alat = mysqli_query(
    $koneksi,
    "SELECT id_alat, kode_alat, nama_alat, stok, jumlah_baik
     FROM alat
     WHERE stok > 0
     AND jumlah_baik > 0
     ORDER BY nama_alat"
);

$daftar_alat = [];

while ($a = mysqli_fetch_assoc($hasil_alat)) {
    $daftar_alat[] = $a;
}

mysqli_free_result($hasil_alat);

/* =========================
   PENCARIAN DAN FILTER
========================= */

$cari = trim($_GET['cari'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT
            p.*,
            u.nama,
            GROUP_CONCAT(
                CONCAT(a.nama_alat, ' (', d.jumlah_pinjam, ')')
                SEPARATOR ', '
            ) AS daftar_alat
        FROM peminjaman p
        JOIN pengguna u ON p.id_user = u.id_user
        LEFT JOIN detail_peminjaman d
            ON p.id_peminjaman = d.id_peminjaman
        LEFT JOIN alat a ON d.id_alat = a.id_alat
        WHERE 1 = 1";

if ($cari !== '') {
    $c = mysqli_real_escape_string($koneksi, $cari);

    $sql .= " AND (
        p.kode_peminjaman LIKE '%$c%'
        OR u.nama LIKE '%$c%'
        OR a.nama_alat LIKE '%$c%'
    )";
}

if ($status !== '') {
    $s = mysqli_real_escape_string($koneksi, $status);
    $sql .= " AND p.status = '$s'";
}

$sql .= " GROUP BY p.id_peminjaman
          ORDER BY p.id_peminjaman DESC";

$data_peminjaman = mysqli_query($koneksi, $sql);

/* =========================
   PESAN NOTIFIKASI
========================= */

$pesan = $_GET['pesan'] ?? '';

$daftar_pesan = [
    'berhasil_tambah' => [
        'success',
        'Data peminjaman berhasil disimpan.'
    ],
    'berhasil_edit' => [
        'success',
        'Data peminjaman berhasil diperbarui.'
    ],
    'berhasil_hapus' => [
        'success',
        'Data peminjaman berhasil dihapus.'
    ],
    'stok_tidak_cukup' => [
        'danger',
        'Stok alat baik tidak mencukupi.'
    ],
    'peminjam_tidak_valid' => [
        'danger',
        'Peminjam tidak ditemukan atau tidak aktif.'
    ],
    'data_tidak_valid' => [
        'warning',
        'Lengkapi data dan pastikan tanggal kembali setelah tanggal pinjam.'
    ],
    'gagal' => [
        'danger',
        'Proses gagal. Periksa kembali data dan struktur tabel.'
    ],
    'gagal_hapus' => [
        'danger',
        'Data tidak dapat dihapus karena sudah diproses.'
    ]
];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Peminjaman - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f7f1e8;
            color: #493629;
        }

        .judul {
            color: #60452f;
            font-weight: bold;
        }

        .panel {
            background: white;
            border: 1px solid #e7d9c8;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .btn-cokelat {
            background: #79583d;
            color: white;
        }

        .btn-cokelat:hover {
            background: #60432e;
            color: white;
        }

        .table thead th {
            background: #eee1d1;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <div class="container-fluid p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h3 class="judul mb-1">Data Peminjaman</h3>
                <span class="text-secondary">TECHLOAN - Admin</span>
            </div>

            <a href="index.php" class="btn btn-outline-secondary">
                ← Kembali ke Dashboard
            </a>
        </div>

        <!-- NOTIFIKASI -->
        <?php if (isset($daftar_pesan[$pesan])): ?>
            <div class="alert alert-<?= e($daftar_pesan[$pesan][0]) ?>">
                <?= e($daftar_pesan[$pesan][1]) ?>
            </div>
        <?php endif; ?>

        <!-- FORM TAMBAH DAN EDIT -->
        <div class="panel">

            <h5 class="judul mb-3">
                <?= $edit ? 'Edit Peminjaman' : 'Tambah Peminjaman' ?>
            </h5>

            <form
                method="POST"
                onsubmit="return confirm('Yakin ingin <?= $edit ? 'menyimpan perubahan' : 'menyimpan data peminjaman' ?>?')"
            >
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['csrf']) ?>"
                >

                <input
                    type="hidden"
                    name="aksi"
                    value="<?= $edit ? 'edit' : 'tambah' ?>"
                >

                <?php if ($edit): ?>
                    <input
                        type="hidden"
                        name="id_peminjaman"
                        value="<?= (int) $edit['id_peminjaman'] ?>"
                    >
                <?php endif; ?>

                <div class="row g-3">

                    <!-- NAMA PEMINJAM -->
                    <div class="col-md-4">
                        <label class="form-label">Nama Peminjam</label>

                        <select name="id_user" class="form-select" required>
                            <option value="">Pilih peminjam</option>

                            <?php foreach ($daftar_pengguna as $u): ?>
                                <option
                                    value="<?= (int) $u['id_user'] ?>"
                                    <?= $edit && (int) $edit['id_user'] === (int) $u['id_user'] ? 'selected' : '' ?>
                                >
                                    <?= e($u['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- TANGGAL PINJAM -->
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Pinjam</label>

                        <input
                            type="date"
                            name="tanggal_pinjam"
                            class="form-control"
                            value="<?= e($edit['tanggal_pinjam'] ?? '') ?>"
                            required
                        >
                    </div>

                    <!-- TANGGAL KEMBALI -->
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Kembali</label>

                        <input
                            type="date"
                            name="tanggal_kembali"
                            class="form-control"
                            value="<?= e($edit['tanggal_kembali'] ?? '') ?>"
                            required
                        >
                    </div>

                    <!-- PILIH ALAT -->
                    <div class="col-12">
                        <label class="form-label">Pilih Alat dan Jumlah</label>

                        <div class="table-responsive">
                            <table class="table table-bordered">

                                <thead>
                                    <tr>
                                        <th>Pilih</th>
                                        <th>Kode</th>
                                        <th>Nama Alat</th>
                                        <th>Stok Baik Tersedia</th>
                                        <th>Jumlah Pinjam</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($daftar_alat as $a): ?>
                                        <?php $aid = (int) $a['id_alat']; ?>

                                        <tr>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    name="pilih[<?= $aid ?>]"
                                                    value="1"
                                                    <?= isset($detail_edit[$aid]) ? 'checked' : '' ?>
                                                >
                                            </td>

                                            <td>
                                                <?= e($a['kode_alat']) ?>
                                            </td>

                                            <td>
                                                <?= e($a['nama_alat']) ?>
                                            </td>

                                            <td>
                                                <?= (int) $a['stok'] ?>
                                            </td>

                                            <td>
                                                <input
                                                    type="number"
                                                    name="jumlah[<?= $aid ?>]"
                                                    class="form-control"
                                                    min="1"
                                                    max="<?= (int) $a['stok'] ?>"
                                                    value="<?= $detail_edit[$aid] ?? 1 ?>"
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$daftar_alat): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                Belum ada alat baik yang tersedia.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>

                        <small class="text-secondary">
                            Centang alat yang dipinjam. Bisa memilih lebih dari satu jenis alat.
                        </small>
                    </div>

                    <!-- CATATAN -->
                    <div class="col-12">
                        <label class="form-label">Catatan</label>

                        <textarea
                            name="catatan"
                            class="form-control"
                            rows="2"
                        ><?= e($edit['catatan'] ?? '') ?></textarea>
                    </div>

                    <!-- TOMBOL -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-cokelat">
                            <?= $edit ? 'Simpan Perubahan' : 'Simpan Peminjaman' ?>
                        </button>

                        <?php if ($edit): ?>
                            <a
                                href="peminjaman.php"
                                class="btn btn-outline-secondary"
                            >
                                Batal
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </form>
        </div>

        <!-- PENCARIAN DAN FILTER -->
        <div class="panel">

            <form method="GET" class="row g-2 mb-3">

                <div class="col-md-6">
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Cari kode, nama peminjam, atau alat..."
                        value="<?= e($cari) ?>"
                    >
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>

                        <?php foreach (
                            ['Menunggu', 'Disetujui', 'Ditolak', 'Dipinjam', 'Selesai']
                            as $s
                        ): ?>
                            <option
                                value="<?= e($s) ?>"
                                <?= $status === $s ? 'selected' : '' ?>
                            >
                                <?= e($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-cokelat">
                        Cari
                    </button>

                    <a
                        href="peminjaman.php"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>

            </form>

            <!-- TABEL PEMINJAMAN -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode</th>
                            <th>Peminjam</th>
                            <th>Alat dan Jumlah</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (
                            $data_peminjaman &&
                            mysqli_num_rows($data_peminjaman) > 0
                        ): ?>

                            <?php
                            $no = 1;

                            while ($p = mysqli_fetch_assoc($data_peminjaman)):
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td>
                                        <?= e($p['kode_peminjaman']) ?>
                                    </td>

                                    <td>
                                        <?= e($p['nama']) ?>
                                    </td>

                                    <td>
                                        <?= e($p['daftar_alat'] ?: '-') ?>
                                    </td>

                                    <td>
                                        <?= e($p['tanggal_pinjam']) ?>
                                    </td>

                                    <td>
                                        <?= e($p['tanggal_kembali']) ?>
                                    </td>

                                    <td>
                                        <?= e($p['status']) ?>
                                    </td>

                                    <td class="text-nowrap">

                                        <?php if ($p['status'] === 'Menunggu'): ?>
                                            <a
                                                class="btn btn-sm btn-warning"
                                                href="peminjaman.php?edit=<?= (int) $p['id_peminjaman'] ?>"
                                            >
                                                Edit
                                            </a>
                                        <?php endif; ?>

                                        <?php if (
                                            in_array(
                                                $p['status'],
                                                ['Menunggu', 'Ditolak'],
                                                true
                                            )
                                        ): ?>

                                            <form
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Yakin ingin menghapus data peminjaman ini?')"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= e($_SESSION['csrf']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id_peminjaman"
                                                    value="<?= (int) $p['id_peminjaman'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-danger"
                                                >
                                                    Hapus
                                                </button>
                                            </form>

                                        <?php else: ?>

                                            <span class="text-secondary small">
                                                Transaksi diproses
                                            </span>

                                        <?php endif; ?>

                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="text-center">
                                    Data peminjaman belum tersedia.
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>

    </div>

</body>

</html>
