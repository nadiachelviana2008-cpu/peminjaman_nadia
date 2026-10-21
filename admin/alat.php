<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// TOKEN KEAMANAN
if (!isset($_SESSION['token_alat'])) {
    $_SESSION['token_alat'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_alat'];
$pesan = "";
$error = "";
$id_edit = "";
$id_kategori = "";
$kode_alat = "";
$nama_alat = "";
$merk = "";
$tahun = "";
$jumlah = "";
$kondisi = "Baik";
$lokasi = "";
$keterangan = "";

// FUNGSI AMAN
function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// CEK TOKEN
function cekToken($token) {
    if (
        !isset($_POST['token']) ||
        !hash_equals($token, $_POST['token'])
    ) {
        die("Permintaan tidak valid. Silakan muat ulang halaman.");
    }
}

// CEK KODE ALAT DUPLIKAT
function kodeAda($koneksi, $kode, $id = 0) {
    $query = mysqli_prepare(
        $koneksi,
        "SELECT id_alat FROM alat
         WHERE LOWER(TRIM(kode_alat)) = LOWER(TRIM(?))
         AND id_alat != ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($query, "si", $kode, $id);
    mysqli_stmt_execute($query);
    mysqli_stmt_store_result($query);

    $ada = mysqli_stmt_num_rows($query) > 0;
    mysqli_stmt_close($query);

    return $ada;
}

// TAMBAH ALAT
if (isset($_POST['tambah'])) {
    cekToken($token);

    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $kode_alat = trim($_POST['kode_alat'] ?? "");
    $nama_alat = trim($_POST['nama_alat'] ?? "");
    $merk = trim($_POST['merk'] ?? "");
    $tahun = (int) ($_POST['tahun'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $kondisi = $_POST['kondisi'] ?? "Baik";
    $lokasi = trim($_POST['lokasi'] ?? "");
    $keterangan = trim($_POST['keterangan'] ?? "");

    if (
        $id_kategori <= 0 ||
        $kode_alat === "" ||
        $nama_alat === "" ||
        $tahun < 1900 ||
        $tahun > (int) date('Y') ||
        $jumlah < 1 ||
        !in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)
    ) {
        $error = "Periksa kembali data alat. Pastikan semua data wajib benar.";
    } elseif (kodeAda($koneksi, $kode_alat)) {
        $error = "Kode alat sudah digunakan.";
    } else {
        // CEK KATEGORI
        $cek = mysqli_prepare(
            $koneksi,
            "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
        );

        mysqli_stmt_bind_param($cek, "i", $id_kategori);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        $kategori_valid = mysqli_stmt_num_rows($cek) > 0;
        mysqli_stmt_close($cek);

        if (!$kategori_valid) {
            $error = "Kategori tidak ditemukan.";
        } else {
            // STOK AWAL SAMA DENGAN JUMLAH
            $query = mysqli_prepare(
                $koneksi,
                "INSERT INTO alat
                (id_kategori, kode_alat, nama_alat, merk, tahun,
                 jumlah, stok, kondisi, lokasi, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $query,
                "isssiiisss",
                $id_kategori,
                $kode_alat,
                $nama_alat,
                $merk,
                $tahun,
                $jumlah,
                $jumlah,
                $kondisi,
                $lokasi,
                $keterangan
            );

            if (mysqli_stmt_execute($query)) {
                mysqli_stmt_close($query);
                header("Location: alat.php?pesan=tambah");
                exit;
            }

            $error = "Alat gagal ditambahkan. Periksa struktur tabel database.";
            mysqli_stmt_close($query);
        }
    }
}

// PILIH DATA UNTUK DIEDIT
if (isset($_POST['mulai_edit'])) {
    cekToken($token);

    $id = (int) ($_POST['id_alat'] ?? 0);

    $query = mysqli_prepare(
        $koneksi,
        "SELECT id_kategori, kode_alat, nama_alat, merk, tahun,
                jumlah, stok, kondisi, lokasi, keterangan
         FROM alat WHERE id_alat = ?"
    );

    mysqli_stmt_bind_param($query, "i", $id);
    mysqli_stmt_execute($query);
    mysqli_stmt_bind_result(
        $query,
        $kat_db,
        $kode_db,
        $nama_db,
        $merk_db,
        $tahun_db,
        $jumlah_db,
        $stok_db,
        $kondisi_db,
        $lokasi_db,
        $ket_db
    );

    if (mysqli_stmt_fetch($query)) {
        $id_edit = $id;
        $id_kategori = $kat_db;
        $kode_alat = $kode_db;
        $nama_alat = $nama_db;
        $merk = $merk_db;
        $tahun = $tahun_db;
        $jumlah = $jumlah_db;
        $kondisi = $kondisi_db;
        $lokasi = $lokasi_db;
        $keterangan = $ket_db;
    } else {
        $error = "Data alat tidak ditemukan.";
    }

    mysqli_stmt_close($query);
}

// SIMPAN PERUBAHAN
if (isset($_POST['edit'])) {
    cekToken($token);

    $id = (int) ($_POST['id_alat'] ?? 0);
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $kode_alat = trim($_POST['kode_alat'] ?? "");
    $nama_alat = trim($_POST['nama_alat'] ?? "");
    $merk = trim($_POST['merk'] ?? "");
    $tahun = (int) ($_POST['tahun'] ?? 0);
    $jumlah_baru = (int) ($_POST['jumlah'] ?? 0);
    $kondisi = $_POST['kondisi'] ?? "Baik";
    $lokasi = trim($_POST['lokasi'] ?? "");
    $keterangan = trim($_POST['keterangan'] ?? "");

    if (
        $id <= 0 ||
        $id_kategori <= 0 ||
        $kode_alat === "" ||
        $nama_alat === "" ||
        $tahun < 1900 ||
        $tahun > (int) date('Y') ||
        $jumlah_baru < 1 ||
        !in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)
    ) {
        $error = "Periksa kembali data alat. Pastikan semua data wajib benar.";
    } elseif (kodeAda($koneksi, $kode_alat, $id)) {
        $error = "Kode alat sudah digunakan.";
    } else {
        // PERTAHANKAN JUMLAH ALAT YANG SEDANG DIPINJAM
        $cek = mysqli_prepare(
            $koneksi,
            "SELECT jumlah, stok FROM alat WHERE id_alat = ?"
        );

        mysqli_stmt_bind_param($cek, "i", $id);
        mysqli_stmt_execute($cek);
        mysqli_stmt_bind_result($cek, $jumlah_lama, $stok_lama);

        if (mysqli_stmt_fetch($cek)) {
            $stok_baru = $jumlah_baru - ($jumlah_lama - $stok_lama);
        } else {
            $stok_baru = -1;
        }

        mysqli_stmt_close($cek);

        if ($stok_baru < 0 || $stok_baru > $jumlah_baru) {
            $error = "Jumlah alat tidak boleh kurang dari jumlah yang sedang dipinjam.";
        } else {
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
            );

            mysqli_stmt_bind_param($cek, "i", $id_kategori);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            $kategori_valid = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if (!$kategori_valid) {
                $error = "Kategori tidak ditemukan.";
            } else {
                $query = mysqli_prepare(
                    $koneksi,
                    "UPDATE alat SET
                    id_kategori = ?, kode_alat = ?, nama_alat = ?,
                    merk = ?, tahun = ?, jumlah = ?, stok = ?,
                    kondisi = ?, lokasi = ?, keterangan = ?
                    WHERE id_alat = ?"
                );

                mysqli_stmt_bind_param(
                    $query,
                    "isssiiisssi",
                    $id_kategori,
                    $kode_alat,
                    $nama_alat,
                    $merk,
                    $tahun,
                    $jumlah_baru,
                    $stok_baru,
                    $kondisi,
                    $lokasi,
                    $keterangan,
                    $id
                );

                if (mysqli_stmt_execute($query)) {
                    mysqli_stmt_close($query);
                    header("Location: alat.php?pesan=edit");
                    exit;
                }

                $error = "Data alat gagal diperbarui.";
                mysqli_stmt_close($query);
            }
        }
    }

    $id_edit = $id;
    $jumlah = $jumlah_baru;
}

// HAPUS ALAT
if (isset($_POST['hapus'])) {
    cekToken($token);

    $id = (int) ($_POST['id_alat'] ?? 0);

    // CEK RIWAYAT PEMINJAMAN
    $cek = mysqli_prepare(
        $koneksi,
        "SELECT id_detail FROM detail_peminjaman
         WHERE id_alat = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($cek, "i", $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        mysqli_stmt_close($cek);
        header("Location: alat.php?pesan=riwayat");
        exit;
    }

    mysqli_stmt_close($cek);

    $query = mysqli_prepare(
        $koneksi,
        "DELETE FROM alat WHERE id_alat = ?"
    );

    mysqli_stmt_bind_param($query, "i", $id);

    if (mysqli_stmt_execute($query)) {
        mysqli_stmt_close($query);
        header("Location: alat.php?pesan=hapus");
        exit;
    }

    mysqli_stmt_close($query);
    header("Location: alat.php?pesan=gagal");
    exit;
}

// NOTIFIKASI
if (isset($_GET['pesan'])) {
    switch ($_GET['pesan']) {
        case 'tambah':
            $pesan = "Data alat berhasil ditambahkan.";
            break;
        case 'edit':
            $pesan = "Data alat berhasil diperbarui.";
            break;
        case 'hapus':
            $pesan = "Data alat berhasil dihapus.";
            break;
        case 'riwayat':
            $error = "Alat tidak dapat dihapus karena memiliki riwayat peminjaman.";
            break;
        case 'gagal':
            $error = "Data alat gagal dihapus.";
            break;
    }
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? "");

if ($cari !== "") {
    $kata = "%" . $cari . "%";

    $query = mysqli_prepare(
        $koneksi,
        "SELECT a.*, k.nama_kategori
         FROM alat a
         LEFT JOIN kategori k ON a.id_kategori = k.id_kategori
         WHERE a.kode_alat LIKE ?
            OR a.nama_alat LIKE ?
            OR a.merk LIKE ?
            OR k.nama_kategori LIKE ?
         ORDER BY a.id_alat DESC"
    );

    mysqli_stmt_bind_param($query, "ssss", $kata, $kata, $kata, $kata);
    mysqli_stmt_execute($query);
    $hasil = mysqli_stmt_get_result($query);
} else {
    $hasil = mysqli_query(
        $koneksi,
        "SELECT a.*, k.nama_kategori
         FROM alat a
         LEFT JOIN kategori k ON a.id_kategori = k.id_kategori
         ORDER BY a.id_alat DESC"
    );
}

// DAFTAR KATEGORI
$kategori = mysqli_query(
    $koneksi,
    "SELECT id_kategori, nama_kategori
     FROM kategori ORDER BY nama_kategori ASC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Alat - TECHLOAN</title>

    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f6f0e7;
            color: #49372c;
        }

        .judul {
            color: #594333;
            font-weight: bold;
        }

        .panel {
            background: white;
            border: 1px solid #eadfd2;
            border-radius: 13px;
            padding: 22px;
            box-shadow: 0 4px 14px rgba(75, 53, 35, 0.06);
        }

        .btn-cokelat {
            background: #76563d;
            color: white;
            border: none;
        }

        .btn-cokelat:hover {
            background: #594333;
            color: white;
        }

        .form-control, .form-select {
            border-color: #dfd1c1;
        }

        .table thead th {
            background: #eee2d3;
            color: #49372c;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <h3 class="judul mb-1">Data Alat</h3>
            <p class="text-secondary mb-0">
                Kelola inventaris alat TECHLOAN.
            </p>
        </div>

        <a href="index.php" class="btn btn-outline-secondary">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan !== ""): ?>
        <div class="alert alert-success"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- FORM -->
        <div class="col-xl-4 col-lg-5">
            <div class="panel">
                <h5 class="judul mb-3">
                    <?= $id_edit !== "" ? "Edit Alat" : "Tambah Alat" ?>
                </h5>

                <form method="POST">
                    <input type="hidden" name="token"
                           value="<?= e($token) ?>">

                    <?php if ($id_edit !== ""): ?>
                        <input type="hidden" name="id_alat"
                               value="<?= e($id_edit) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="id_kategori" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>

                            <?php while ($kat = mysqli_fetch_assoc($kategori)): ?>
                                <option value="<?= e($kat['id_kategori']) ?>"
                                    <?= (string) $id_kategori === (string) $kat['id_kategori']
                                        ? 'selected' : '' ?>>
                                    <?= e($kat['nama_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kode Alat</label>
                        <input type="text" name="kode_alat"
                               class="form-control" maxlength="50"
                               value="<?= e($kode_alat) ?>"
                               placeholder="Contoh: ALT001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Alat</label>
                        <input type="text" name="nama_alat"
                               class="form-control" maxlength="100"
                               value="<?= e($nama_alat) ?>"
                               placeholder="Nama alat" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Merek</label>
                        <input type="text" name="merk"
                               class="form-control" maxlength="100"
                               value="<?= e($merk) ?>"
                               placeholder="Contoh: Logitech">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tahun</label>
                        <input type="number" name="tahun"
                               class="form-control"
                               min="1900" max="<?= date('Y') ?>"
                               value="<?= e($tahun) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Alat</label>
                        <input type="number" name="jumlah"
                               class="form-control" min="1"
                               value="<?= e($jumlah) ?>" required>

                        <?php if ($id_edit !== ""): ?>
                            <small class="text-secondary">
                                Stok tersedia disesuaikan dengan jumlah yang sedang dipinjam.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kondisi</label>
                        <select name="kondisi" class="form-select" required>
                            <option value="Baik"
                                <?= $kondisi === 'Baik' ? 'selected' : '' ?>>
                                Baik
                            </option>
                            <option value="Rusak Ringan"
                                <?= $kondisi === 'Rusak Ringan' ? 'selected' : '' ?>>
                                Rusak Ringan
                            </option>
                            <option value="Rusak Berat"
                                <?= $kondisi === 'Rusak Berat' ? 'selected' : '' ?>>
                                Rusak Berat
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="lokasi"
                               class="form-control" maxlength="100"
                               value="<?= e($lokasi) ?>"
                               placeholder="Contoh: Lab RPL">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan"
                                  class="form-control" rows="3"
                                  placeholder="Keterangan tambahan"><?= e($keterangan) ?></textarea>
                    </div>

                    <?php if ($id_edit !== ""): ?>
                        <button type="submit" name="edit"
                                class="btn btn-cokelat w-100"
                                onclick="return confirm('Apakah Anda yakin ingin menyimpan perubahan alat?')">
                            Simpan Perubahan
                        </button>

                        <a href="alat.php"
                           class="btn btn-outline-secondary w-100 mt-2">
                            Batal
                        </a>
                    <?php else: ?>
                        <button type="submit" name="tambah"
                                class="btn btn-cokelat w-100"
                                onclick="return confirm('Apakah Anda yakin ingin menyimpan data alat ini?')">
                            + Simpan Alat
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABEL -->
        <div class="col-xl-8 col-lg-7">
            <div class="panel">

                <div class="d-flex flex-wrap justify-content-between
                            align-items-center gap-3 mb-3">
                    <h5 class="judul mb-0">Daftar Alat</h5>

                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="cari"
                               class="form-control"
                               placeholder="Cari alat..."
                               value="<?= e($cari) ?>">

                        <button type="submit" class="btn btn-cokelat">
                            Cari
                        </button>

                        <?php if ($cari !== ""): ?>
                            <a href="alat.php"
                               class="btn btn-outline-secondary">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Stok</th>
                                <th>Kondisi</th>
                                <th>Lokasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php $no = 1; ?>
                        <?php while ($data = mysqli_fetch_assoc($hasil)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($data['kode_alat']) ?></td>
                                <td>
                                    <strong><?= e($data['nama_alat']) ?></strong>
                                    <br>
                                    <small class="text-secondary">
                                        <?= e($data['merk'] ?: '-') ?>
                                    </small>
                                </td>
                                <td><?= e($data['nama_kategori'] ?? '-') ?></td>
                                <td><?= e($data['jumlah']) ?></td>
                                <td><?= e($data['stok']) ?></td>
                                <td><?= e($data['kondisi']) ?></td>
                                <td><?= e($data['lokasi'] ?: '-') ?></td>
                                <td>
                                    <div class="d-flex gap-1">

                                        <form method="POST">
                                            <input type="hidden" name="token"
                                                   value="<?= e($token) ?>">
                                            <input type="hidden" name="id_alat"
                                                   value="<?= e($data['id_alat']) ?>">

                                            <button type="submit" name="mulai_edit"
                                                    class="btn btn-sm btn-warning">
                                                Edit
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="token"
                                                   value="<?= e($token) ?>">
                                            <input type="hidden" name="id_alat"
                                                   value="<?= e($data['id_alat']) ?>">

                                            <button type="submit" name="hapus"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus alat ini?')">
                                                Hapus
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($hasil) === 0): ?>
                            <tr>
                                <td colspan="9"
                                    class="text-center text-secondary py-4">
                                    Data alat tidak ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>

    <div class="text-center text-secondary mt-4">
        <small>TECHLOAN &copy; <?= date('Y') ?></small>
    </div>

</div>

</body>
</html>
