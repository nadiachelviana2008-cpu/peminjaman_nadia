
<?php
session_start();
include "../koneksi.php";

// HANYA ADMIN
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// TOKEN FORM
if (empty($_SESSION['token_pengguna'])) {
    $_SESSION['token_pengguna'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_pengguna'];
$pesan = "";
$jenis_pesan = "info";
$edit_data = null;

// FUNGSI AMAN
function e($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// DATA FORM SEMENTARA
$form = [
    'id_user' => '',
    'nama' => '',
    'username' => '',
    'level' => '',
    'kelas' => '',
    'no_hp' => '',
    'status' => 'Aktif'
];

// PROSES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CEK TOKEN
    if (
        !isset($_POST['token']) ||
        !hash_equals($_SESSION['token_pengguna'], $_POST['token'])
    ) {
        $pesan = "Permintaan tidak valid. Muat ulang halaman!";
        $jenis_pesan = "danger";
    } else {

        // =========================
        // TAMBAH PENGGUNA
        // =========================
        if (isset($_POST['tambah'])) {
            $nama = trim($_POST['nama'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $level = $_POST['level'] ?? '';
            $kelas = trim($_POST['kelas'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $status = $_POST['status'] ?? 'Aktif';

            $form = [
                'id_user' => '',
                'nama' => $nama,
                'username' => $username,
                'level' => $level,
                'kelas' => $kelas,
                'no_hp' => $no_hp,
                'status' => $status
            ];

            if ($nama === '' || $username === '' || $password === '') {
                $pesan = "Nama, username, dan password wajib diisi!";
                $jenis_pesan = "danger";
            } elseif (
                $no_hp !== '' &&
                !preg_match('/^[0-9]{1,13}$/', $no_hp)
            ) {
                $pesan = "Nomor HP hanya boleh angka dan maksimal 13 digit!";
                $jenis_pesan = "danger";
            } elseif (!in_array($level, ['Admin', 'Petugas', 'Peminjam'], true)) {
                $pesan = "Level pengguna tidak valid!";
                $jenis_pesan = "danger";
            } elseif (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
                $pesan = "Status pengguna tidak valid!";
                $jenis_pesan = "danger";
            } else {
                $cek = mysqli_prepare(
                    $koneksi,
                    "SELECT id_user FROM pengguna WHERE username = ?"
                );
                mysqli_stmt_bind_param($cek, "s", $username);
                mysqli_stmt_execute($cek);
                $hasil = mysqli_stmt_get_result($cek);

                if (mysqli_num_rows($hasil) > 0) {
                    $pesan = "Username sudah digunakan!";
                    $jenis_pesan = "danger";
                } else {
                    $password_hash = md5($password);

                    $query = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO pengguna
                        (nama, username, password, level, kelas, no_hp, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $query,
                        "sssssss",
                        $nama,
                        $username,
                        $password_hash,
                        $level,
                        $kelas,
                        $no_hp,
                        $status
                    );

                    if (mysqli_stmt_execute($query)) {
                        header("Location: pengguna.php?pesan=tambah");
                        exit;
                    } else {
                        $pesan = "Gagal menambahkan pengguna!";
                        $jenis_pesan = "danger";
                    }
                }
            }
        }

        // =========================
        // MULAI EDIT
        // ID DIKIRIM DENGAN POST
        // =========================
        elseif (isset($_POST['mulai_edit'])) {
            $id = (int) ($_POST['id_user'] ?? 0);

            $query = mysqli_prepare(
                $koneksi,
                "SELECT id_user, nama, username, level, kelas, no_hp, status
                 FROM pengguna WHERE id_user = ?"
            );
            mysqli_stmt_bind_param($query, "i", $id);
            mysqli_stmt_execute($query);
            $edit_data = mysqli_fetch_assoc(
                mysqli_stmt_get_result($query)
            );

            if (!$edit_data) {
                $pesan = "Pengguna tidak ditemukan!";
                $jenis_pesan = "danger";
            }
        }

        // =========================
        // SIMPAN PERUBAHAN
        // =========================
        elseif (isset($_POST['edit'])) {
            $id = (int) ($_POST['id_user'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $level = $_POST['level'] ?? '';
            $kelas = trim($_POST['kelas'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $status = $_POST['status'] ?? '';

            $form = [
                'id_user' => $id,
                'nama' => $nama,
                'username' => $username,
                'level' => $level,
                'kelas' => $kelas,
                'no_hp' => $no_hp,
                'status' => $status
            ];

            if ($nama === '' || $username === '') {
                $pesan = "Nama dan username wajib diisi!";
                $jenis_pesan = "danger";
            } elseif (
                $no_hp !== '' &&
                !preg_match('/^[0-9]{1,13}$/', $no_hp)
            ) {
                $pesan = "Nomor HP hanya boleh angka dan maksimal 13 digit!";
                $jenis_pesan = "danger";
            } elseif (!in_array($level, ['Admin', 'Petugas', 'Peminjam'], true)) {
                $pesan = "Level pengguna tidak valid!";
                $jenis_pesan = "danger";
            } elseif (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
                $pesan = "Status pengguna tidak valid!";
                $jenis_pesan = "danger";
            } else {
                // CEK PENGGUNA LAMA
                $cek_user = mysqli_prepare(
                    $koneksi,
                    "SELECT id_user FROM pengguna WHERE id_user = ?"
                );
                mysqli_stmt_bind_param($cek_user, "i", $id);
                mysqli_stmt_execute($cek_user);
                $user_lama = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($cek_user)
                );

                if (!$user_lama) {
                    $pesan = "Pengguna tidak ditemukan!";
                    $jenis_pesan = "danger";
                } elseif (
                    $id === (int) $_SESSION['id_user'] &&
                    ($level !== 'Admin' || $status !== 'Aktif')
                ) {
                    $pesan = "Level dan status akun Admin yang sedang digunakan tidak boleh diubah!";
                    $jenis_pesan = "danger";
                } else {
                    // CEK USERNAME DUPLIKAT
                    $cek = mysqli_prepare(
                        $koneksi,
                        "SELECT id_user FROM pengguna
                         WHERE username = ? AND id_user != ?"
                    );
                    mysqli_stmt_bind_param($cek, "si", $username, $id);
                    mysqli_stmt_execute($cek);
                    $hasil = mysqli_stmt_get_result($cek);

                    if (mysqli_num_rows($hasil) > 0) {
                        $pesan = "Username sudah digunakan pengguna lain!";
                        $jenis_pesan = "danger";
                    } else {
                        if ($password !== '') {
                            $password_hash = md5($password);

                            $query = mysqli_prepare(
                                $koneksi,
                                "UPDATE pengguna SET
                                 nama = ?, username = ?, password = ?,
                                 level = ?, kelas = ?, no_hp = ?, status = ?
                                 WHERE id_user = ?"
                            );

                            mysqli_stmt_bind_param(
                                $query,
                                "sssssssi",
                                $nama,
                                $username,
                                $password_hash,
                                $level,
                                $kelas,
                                $no_hp,
                                $status,
                                $id
                            );
                        } else {
                            $query = mysqli_prepare(
                                $koneksi,
                                "UPDATE pengguna SET
                                 nama = ?, username = ?, level = ?,
                                 kelas = ?, no_hp = ?, status = ?
                                 WHERE id_user = ?"
                            );

                            mysqli_stmt_bind_param(
                                $query,
                                "ssssssi",
                                $nama,
                                $username,
                                $level,
                                $kelas,
                                $no_hp,
                                $status,
                                $id
                            );
                        }

                        if (mysqli_stmt_execute($query)) {
                            if ($id === (int) $_SESSION['id_user']) {
                                $_SESSION['nama'] = $nama;
                                $_SESSION['username'] = $username;
                            }

                            header("Location: pengguna.php?pesan=edit");
                            exit;
                        } else {
                            $pesan = "Gagal menyimpan perubahan pengguna!";
                            $jenis_pesan = "danger";
                        }
                    }
                }
            }

            // TAMPILKAN KEMBALI FORM EDIT JIKA GAGAL
            if ($pesan !== '') {
                $edit_data = $form;
            }
        }

        // =========================
        // HAPUS PENGGUNA
        // =========================
        elseif (isset($_POST['hapus'])) {
            $id = (int) ($_POST['id_user'] ?? 0);

            if ($id === (int) $_SESSION['id_user']) {
                $pesan = "Akun yang sedang digunakan tidak boleh dihapus!";
                $jenis_pesan = "danger";
            } else {
                $query = mysqli_prepare(
                    $koneksi,
                    "DELETE FROM pengguna WHERE id_user = ?"
                );
                mysqli_stmt_bind_param($query, "i", $id);

                if (mysqli_stmt_execute($query)) {
                    if (mysqli_stmt_affected_rows($query) > 0) {
                        header("Location: pengguna.php?pesan=hapus");
                        exit;
                    } else {
                        $pesan = "Pengguna tidak ditemukan!";
                        $jenis_pesan = "danger";
                    }
                } else {
                    $pesan = "Pengguna tidak dapat dihapus karena masih digunakan pada data peminjaman atau data terkait lainnya!";
                    $jenis_pesan = "danger";
                }
            }
        }
    }
}

// =========================
// PENCARIAN DATA
// GET HANYA UNTUK PENCARIAN
// =========================
$cari = trim($_GET['cari'] ?? '');
$kata = "%" . $cari . "%";

$query = mysqli_prepare(
    $koneksi,
    "SELECT id_user, nama, username, level, kelas, no_hp, status
     FROM pengguna
     WHERE nama LIKE ? OR username LIKE ? OR level LIKE ?
     ORDER BY id_user DESC"
);
mysqli_stmt_bind_param($query, "sss", $kata, $kata, $kata);
mysqli_stmt_execute($query);
$data = mysqli_stmt_get_result($query);

// PESAN SETELAH BERHASIL
if (isset($_GET['pesan'])) {
    $daftar_pesan = [
        'tambah' => 'Pengguna berhasil ditambahkan!',
        'edit' => 'Perubahan data pengguna berhasil disimpan!',
        'hapus' => 'Pengguna berhasil dihapus!'
    ];

    if (isset($daftar_pesan[$_GET['pesan']])) {
        $pesan = $daftar_pesan[$_GET['pesan']];
        $jenis_pesan = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna - TECHLOAN</title>
    <link rel="stylesheet" href="../asset/bootstrap.min.css">

    <style>
        body {
            background: #f7f2eb;
            color: #403027;
        }

        .header {
            background: linear-gradient(120deg, #50392d, #89674f);
            color: white;
            padding: 24px;
            border-radius: 14px;
        }

        .panel {
            background: white;
            padding: 22px;
            border-radius: 14px;
            border: 1px solid #eee3d7;
        }

        .btn-cokelat {
            background: #50392d;
            color: white;
        }

        .btn-cokelat:hover {
            background: #705342;
            color: white;
        }

        .table thead th {
            background: #eee2d4;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
<div class="container py-4">

    <!-- HEADER -->
    <div class="header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <small>TECHLOAN / PENGELOLAAN</small>
                <h3 class="fw-bold mt-2 mb-1">Data Pengguna</h3>
                <p class="mb-0">Kelola akun Admin, Petugas, dan Peminjam.</p>
            </div>
            <a href="index.php" class="btn btn-light">← Dashboard</a>
        </div>
    </div>

    <!-- PESAN SUKSES ATAU ERROR -->
    <?php if ($pesan !== '') { ?>
        <div class="alert alert-<?= e($jenis_pesan); ?> alert-dismissible fade show"
             role="alert">
            <?= e($pesan); ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php } ?>

    <!-- FORM TAMBAH / EDIT -->
    <div class="panel mb-4">
        <h5 class="fw-bold mb-3">
            <?= $edit_data ? 'Edit Pengguna' : 'Tambah Pengguna'; ?>
        </h5>

        <form method="POST" action="pengguna.php">
            <input type="hidden" name="token" value="<?= e($token); ?>">

            <?php if ($edit_data) { ?>
                <input type="hidden" name="id_user"
                       value="<?= e($edit_data['id_user']); ?>">
            <?php } ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama" class="form-control"
                           maxlength="100" required
                           value="<?= e($edit_data['nama'] ?? $form['nama']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control"
                           maxlength="50" required
                           value="<?= e($edit_data['username'] ?? $form['username']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Password <?= $edit_data ? '(kosongkan jika tidak diganti)' : '*'; ?>
                    </label>
                    <input type="password" name="password" class="form-control"
                           <?= $edit_data ? '' : 'required'; ?>
                           autocomplete="new-password">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Level *</label>
                    <select name="level" class="form-select" required>
                        <option value="">-- Pilih Level --</option>
                        <?php foreach (['Admin', 'Petugas', 'Peminjam'] as $level) { ?>
                            <option value="<?= e($level); ?>"
                                <?= (($edit_data['level'] ?? $form['level']) === $level) ? 'selected' : ''; ?>>
                                <?= e($level); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kelas</label>
                    <input type="text" name="kelas" class="form-control"
                           maxlength="50"
                           value="<?= e($edit_data['kelas'] ?? $form['kelas']); ?>"
                           placeholder="Contoh: XI RPL 1">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nomor HP</label>
                    <input type="text" name="no_hp" class="form-control"
                           maxlength="13"
                           inputmode="numeric"
                           pattern="[0-9]{1,13}"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13)"
                           value="<?= e($edit_data['no_hp'] ?? $form['no_hp']); ?>"
                           placeholder="Masukkan nomor HP">
                    <small class="text-muted">Hanya angka, maksimal 13 digit.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="Aktif"
                            <?= (($edit_data['status'] ?? $form['status']) === 'Aktif') ? 'selected' : ''; ?>>
                            Aktif
                        </option>
                        <option value="Nonaktif"
                            <?= (($edit_data['status'] ?? $form['status']) === 'Nonaktif') ? 'selected' : ''; ?>>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <div class="col-12">
                    <?php if ($edit_data) { ?>
                        <button type="submit" name="edit" class="btn btn-cokelat"
                            onclick="return confirm('Apakah Anda yakin ingin menyimpan perubahan?')">
                            Simpan Perubahan
                        </button>
                        <a href="pengguna.php" class="btn btn-secondary">Batal</a>
                    <?php } else { ?>
                        <button type="submit" name="tambah" class="btn btn-cokelat"
                            onclick="return confirm('Apakah Anda yakin ingin menyimpan data pengguna?')">
                            + Simpan Pengguna
                        </button>
                    <?php } ?>
                </div>
            </div>
        </form>
    </div>

    <!-- TABEL PENGGUNA -->
    <div class="panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <h5 class="fw-bold mb-0">Daftar Pengguna</h5>

            <form method="GET" action="pengguna.php"
                  class="d-flex gap-2 flex-wrap">
                <input type="text" name="cari" class="form-control"
                       placeholder="Cari nama, username, level..."
                       value="<?= e($cari); ?>">
                <button type="submit" class="btn btn-cokelat">Cari</button>
                <a href="pengguna.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Level</th>
                        <th>Kelas</th>
                        <th>No. HP</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;

                if (mysqli_num_rows($data) > 0) {
                    while ($row = mysqli_fetch_assoc($data)) {
                ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= e($row['nama']); ?></td>
                        <td><?= e($row['username']); ?></td>
                        <td><?= e($row['level']); ?></td>
                        <td><?= e($row['kelas'] ?: '-'); ?></td>
                        <td><?= e($row['no_hp'] ?: '-'); ?></td>
                        <td>
                            <?php if ($row['status'] === 'Aktif') { ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php } else { ?>
                                <span class="badge bg-secondary">Nonaktif</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">

                            <!-- EDIT VIA POST -->
                            <form method="POST" action="pengguna.php" class="d-inline">
                                <input type="hidden" name="token" value="<?= e($token); ?>">
                                <input type="hidden" name="id_user"
                                       value="<?= e($row['id_user']); ?>">
                                <button type="submit" name="mulai_edit"
                                        class="btn btn-sm btn-warning">
                                    Edit
                                </button>
                            </form>

                            <!-- HAPUS VIA POST -->
                            <?php if ((int) $row['id_user'] !== (int) $_SESSION['id_user']) { ?>
                                <form method="POST" action="pengguna.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Yakin ingin menghapus pengguna ini?');">
                                    <input type="hidden" name="token" value="<?= e($token); ?>">
                                    <input type="hidden" name="id_user"
                                           value="<?= e($row['id_user']); ?>">
                                    <button type="submit" name="hapus"
                                            class="btn btn-sm btn-danger">
                                        Hapus
                                    </button>
                                </form>
                            <?php } ?>

                        </td>
                    </tr>
                <?php
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            Data pengguna tidak ditemukan.
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center mt-4">
        <small class="text-muted">© <?= date('Y'); ?> TECHLOAN</small>
    </div>

</div>
</body>
</html>
