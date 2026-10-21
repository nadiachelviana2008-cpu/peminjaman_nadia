<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// TOKEN KEAMANAN
if (!isset($_SESSION['token_kategori'])) {
    $_SESSION['token_kategori'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_kategori'];
$pesan = "";
$error = "";
$id_edit = "";
$nama_kategori = "";
$keterangan = "";

// CEK TOKEN
function cekToken($token) {
    if (
        !isset($_POST['token']) ||
        !hash_equals($token, $_POST['token'])
    ) {
        die("Permintaan tidak valid. Silakan muat ulang halaman.");
    }
}

// CEK KATEGORI DUPLIKAT
function kategoriAda($koneksi, $nama, $id = 0) {
    $query = mysqli_prepare(
        $koneksi,
        "SELECT id_kategori FROM kategori
         WHERE LOWER(TRIM(nama_kategori)) = LOWER(TRIM(?))
         AND id_kategori != ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($query, "si", $nama, $id);
    mysqli_stmt_execute($query);
    mysqli_stmt_store_result($query);

    $ada = mysqli_stmt_num_rows($query) > 0;
    mysqli_stmt_close($query);

    return $ada;
}

// TAMBAH KATEGORI
if (isset($_POST['tambah'])) {
    cekToken($token);

    $nama = trim($_POST['nama_kategori'] ?? "");
    $ket = trim($_POST['keterangan'] ?? "");

    if ($nama === "") {
        $error = "Nama kategori wajib diisi.";
    } elseif (kategoriAda($koneksi, $nama)) {
        $error = "Nama kategori sudah tersedia.";
    } else {
        $query = mysqli_prepare(
            $koneksi,
            "INSERT INTO kategori (nama_kategori, keterangan)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($query, "ss", $nama, $ket);

        if (mysqli_stmt_execute($query)) {
            mysqli_stmt_close($query);
            header("Location: kategori.php?pesan=tambah");
            exit;
        }

        $error = "Kategori gagal ditambahkan.";
        mysqli_stmt_close($query);
    }

    $nama_kategori = $nama;
    $keterangan = $ket;
}

// PILIH KATEGORI UNTUK DIEDIT
if (isset($_POST['mulai_edit'])) {
    cekToken($token);

    $id = (int) ($_POST['id_kategori'] ?? 0);

    $query = mysqli_prepare(
        $koneksi,
        "SELECT nama_kategori, keterangan
         FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($query, "i", $id);
    mysqli_stmt_execute($query);
    mysqli_stmt_bind_result($query, $nama_db, $ket_db);

    if (mysqli_stmt_fetch($query)) {
        $id_edit = $id;
        $nama_kategori = $nama_db;
        $keterangan = $ket_db;
    } else {
        $error = "Kategori tidak ditemukan.";
    }

    mysqli_stmt_close($query);
}

// SIMPAN PERUBAHAN
if (isset($_POST['edit'])) {
    cekToken($token);

    $id = (int) ($_POST['id_kategori'] ?? 0);
    $nama = trim($_POST['nama_kategori'] ?? "");
    $ket = trim($_POST['keterangan'] ?? "");

    if ($nama === "") {
        $error = "Nama kategori wajib diisi.";
    } elseif (kategoriAda($koneksi, $nama, $id)) {
        $error = "Nama kategori sudah digunakan.";
    } else {
        $query = mysqli_prepare(
            $koneksi,
            "UPDATE kategori
             SET nama_kategori = ?, keterangan = ?
             WHERE id_kategori = ?"
        );

        mysqli_stmt_bind_param($query, "ssi", $nama, $ket, $id);

        if (mysqli_stmt_execute($query)) {
            mysqli_stmt_close($query);
            header("Location: kategori.php?pesan=edit");
            exit;
        }

        $error = "Kategori gagal diperbarui.";
        mysqli_stmt_close($query);
    }

    $id_edit = $id;
    $nama_kategori = $nama;
    $keterangan = $ket;
}

// HAPUS KATEGORI
if (isset($_POST['hapus'])) {
    cekToken($token);

    $id = (int) ($_POST['id_kategori'] ?? 0);

    // CEK APAKAH KATEGORI MASIH DIPAKAI ALAT
    $cek = mysqli_prepare(
        $koneksi,
        "SELECT id_alat FROM alat
         WHERE id_kategori = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($cek, "i", $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        mysqli_stmt_close($cek);
        header("Location: kategori.php?pesan=dipakai");
        exit;
    }

    mysqli_stmt_close($cek);

    $query = mysqli_prepare(
        $koneksi,
        "DELETE FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($query, "i", $id);

    if (mysqli_stmt_execute($query)) {
        mysqli_stmt_close($query);
        header("Location: kategori.php?pesan=hapus");
        exit;
    }

    mysqli_stmt_close($query);
    header("Location: kategori.php?pesan=gagal");
    exit;
}

// NOTIFIKASI
if (isset($_GET['pesan'])) {
    switch ($_GET['pesan']) {
        case 'tambah':
            $pesan = "Kategori berhasil ditambahkan.";
            break;
        case 'edit':
            $pesan = "Kategori berhasil diperbarui.";
            break;
        case 'hapus':
            $pesan = "Kategori berhasil dihapus.";
            break;
        case 'dipakai':
            $error = "Kategori tidak bisa dihapus karena masih digunakan oleh alat.";
            break;
        case 'gagal':
            $error = "Kategori gagal dihapus.";
            break;
    }
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? "");

if ($cari !== "") {
    $kata = "%" . $cari . "%";

    $query = mysqli_prepare(
        $koneksi,
        "SELECT id_kategori, nama_kategori, keterangan
         FROM kategori
         WHERE nama_kategori LIKE ? OR keterangan LIKE ?
         ORDER BY id_kategori DESC"
    );

    mysqli_stmt_bind_param($query, "ss", $kata, $kata);
    mysqli_stmt_execute($query);
    $hasil = mysqli_stmt_get_result($query);
} else {
    $hasil = mysqli_query(
        $koneksi,
        "SELECT id_kategori, nama_kategori, keterangan
         FROM kategori ORDER BY id_kategori DESC"
    );
}

// AMANKAN TEKS
function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Kategori - TECHLOAN</title>

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

        .form-control {
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

<div class="container py-4 py-md-5">

    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <h3 class="judul mb-1">Data Kategori Alat</h3>
            <p class="text-secondary mb-0">
                Kelola kategori alat yang tersedia di TECHLOAN.
            </p>
        </div>

        <a href="index.php" class="btn btn-outline-secondary">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($pesan !== ""): ?>
        <div class="alert alert-success">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- FORM TAMBAH DAN EDIT -->
        <div class="col-lg-4">
            <div class="panel">
                <h5 class="judul mb-3">
                    <?= $id_edit !== "" ? "Edit Kategori" : "Tambah Kategori" ?>
                </h5>

                <form method="POST">
                    <input type="hidden" name="token"
                           value="<?= e($token) ?>">

                    <?php if ($id_edit !== ""): ?>
                        <input type="hidden" name="id_kategori"
                               value="<?= e($id_edit) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text"
                               name="nama_kategori"
                               class="form-control"
                               maxlength="100"
                               placeholder="Contoh: Elektronik"
                               value="<?= e($nama_kategori) ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Keterangan kategori"><?= e($keterangan) ?></textarea>
                    </div>

                    <?php if ($id_edit !== ""): ?>

                        <button type="submit"
                                name="edit"
                                class="btn btn-cokelat w-100"
                                onclick="return confirm('Apakah Anda yakin ingin menyimpan perubahan kategori?')">
                            Simpan Perubahan
                        </button>

                        <a href="kategori.php"
                           class="btn btn-outline-secondary w-100 mt-2">
                            Batal
                        </a>

                    <?php else: ?>

                        <button type="submit"
                                name="tambah"
                                class="btn btn-cokelat w-100"
                                onclick="return confirm('Apakah Anda yakin ingin menyimpan kategori ini?')">
                            + Simpan Kategori
                        </button>

                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABEL DATA -->
        <div class="col-lg-8">
            <div class="panel">

                <div class="d-flex flex-wrap justify-content-between
                            align-items-center gap-3 mb-3">
                    <h5 class="judul mb-0">Daftar Kategori</h5>

                    <form method="GET" class="d-flex gap-2">
                        <input type="text"
                               name="cari"
                               class="form-control"
                               placeholder="Cari kategori..."
                               value="<?= e($cari) ?>">

                        <button type="submit" class="btn btn-cokelat">
                            Cari
                        </button>

                        <?php if ($cari !== ""): ?>
                            <a href="kategori.php"
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
                                <th width="50">No</th>
                                <th>Nama Kategori</th>
                                <th>Keterangan</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php
                        $no = 1;
                        while ($data = mysqli_fetch_assoc($hasil)):
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= e($data['nama_kategori']) ?>
                                </td>

                                <td>
                                    <?= trim((string) $data['keterangan']) !== ""
                                        ? e($data['keterangan'])
                                        : "-" ?>
                                </td>

                                <td>
                                    <div class="d-flex gap-1">

                                        <!-- EDIT -->
                                        <form method="POST">
                                            <input type="hidden"
                                                   name="token"
                                                   value="<?= e($token) ?>">

                                            <input type="hidden"
                                                   name="id_kategori"
                                                   value="<?= e($data['id_kategori']) ?>">

                                            <button type="submit"
                                                    name="mulai_edit"
                                                    class="btn btn-sm btn-warning">
                                                Edit
                                            </button>
                                        </form>

                                        <!-- HAPUS -->
                                        <form method="POST">
                                            <input type="hidden"
                                                   name="token"
                                                   value="<?= e($token) ?>">

                                            <input type="hidden"
                                                   name="id_kategori"
                                                   value="<?= e($data['id_kategori']) ?>">

                                            <button type="submit"
                                                    name="hapus"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                                Hapus
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($hasil) === 0): ?>
                            <tr>
                                <td colspan="4"
                                    class="text-center text-secondary py-4">
                                    Data kategori tidak ditemukan.
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
