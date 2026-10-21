
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Peminjam') {
    header("Location: ../login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Peminjam';
$pesan = "";
$jenis_pesan = "success";

function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// TABEL KHUSUS PENGAJUAN PENGEMBALIAN
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS pengajuan_pengembalian (
    id_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
    id_peminjaman INT NOT NULL UNIQUE,
    tanggal_pengajuan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    catatan TEXT NULL,
    status ENUM('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu'
)");

mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS detail_pengajuan_pengembalian (
    id_detail_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_detail INT NOT NULL,
    kondisi_kembali ENUM('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik'
)");

// PROSES PENGAJUAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan'])) {
    $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $kondisi = $_POST['kondisi'] ?? [];

    try {
        mysqli_begin_transaction($koneksi);

        // Pastikan peminjaman milik akun yang sedang login
        $stmt = mysqli_prepare($koneksi, "
            SELECT id_peminjaman
            FROM peminjaman
            WHERE id_peminjaman = ?
              AND id_user = ?
              AND status = 'Dipinjam'
            FOR UPDATE
        ");
        mysqli_stmt_bind_param($stmt, "ii", $id_peminjaman, $id_user);
        mysqli_stmt_execute($stmt);
        $pinjam = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($pinjam) === 0) {
            throw new Exception(
                "Peminjaman tidak ditemukan atau statusnya bukan Dipinjam."
            );
        }

        // Cegah pengajuan ganda
        $stmt = mysqli_prepare($koneksi, "
            SELECT id_pengajuan
            FROM pengajuan_pengembalian
            WHERE id_peminjaman = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_peminjaman);
        mysqli_stmt_execute($stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            throw new Exception(
                "Pengajuan pengembalian untuk peminjaman ini sudah pernah dibuat."
            );
        }

        // Ambil semua detail alat yang dipinjam
        $stmt = mysqli_prepare($koneksi, "
            SELECT id_detail
            FROM detail_peminjaman
            WHERE id_peminjaman = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_peminjaman);
        mysqli_stmt_execute($stmt);
        $detail = mysqli_stmt_get_result($stmt);

        $daftar_detail = [];
        while ($d = mysqli_fetch_assoc($detail)) {
            $daftar_detail[] = (int)$d['id_detail'];
        }

        if (count($daftar_detail) === 0) {
            throw new Exception("Detail alat yang dipinjam tidak ditemukan.");
        }

        // Simpan pengajuan, bukan menyelesaikan pengembalian
        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO pengajuan_pengembalian
                (id_peminjaman, catatan)
            VALUES (?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "is", $id_peminjaman, $catatan);
        mysqli_stmt_execute($stmt);

        $id_pengajuan = mysqli_insert_id($koneksi);

        $stmt_detail = mysqli_prepare($koneksi, "
            INSERT INTO detail_pengajuan_pengembalian
                (id_pengajuan, id_detail, kondisi_kembali)
            VALUES (?, ?, ?)
        ");

        foreach ($daftar_detail as $id_detail) {
            $pilihan = $kondisi[$id_detail] ?? 'Baik';

            if (!in_array($pilihan, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
                throw new Exception("Pilihan kondisi alat tidak valid.");
            }

            mysqli_stmt_bind_param(
                $stmt_detail,
                "iis",
                $id_pengajuan,
                $id_detail,
                $pilihan
            );
            mysqli_stmt_execute($stmt_detail);
        }

        mysqli_commit($koneksi);

        header("Location: pengembalian_saya.php?berhasil=1");
        exit;

    } catch (Throwable $err) {
        mysqli_rollback($koneksi);
        $pesan = $err->getMessage();
        $jenis_pesan = "danger";
    }
}

// DAFTAR PEMINJAMAN YANG MASIH DIPINJAM
$stmt = mysqli_prepare($koneksi, "
    SELECT p.id_peminjaman, p.kode_peminjaman,
           p.tanggal_pinjam, p.tanggal_kembali
    FROM peminjaman p
    WHERE p.id_user = ?
      AND p.status = 'Dipinjam'
      AND NOT EXISTS (
          SELECT 1
          FROM pengajuan_pengembalian pg
          WHERE pg.id_peminjaman = p.id_peminjaman
            AND pg.status IN ('Menunggu', 'Disetujui')
      )
    ORDER BY p.id_peminjaman DESC
");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data_peminjaman = mysqli_stmt_get_result($stmt);

// RIWAYAT PENGAJUAN
$stmt = mysqli_prepare($koneksi, "
    SELECT pg.*, p.kode_peminjaman
    FROM pengajuan_pengembalian pg
    JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
    WHERE p.id_user = ?
    ORDER BY pg.id_pengajuan DESC
");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$riwayat = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengembalian Alat - TECHLOAN</title>
<link rel="stylesheet" href="../asset/bootstrap.min.css">

<style>
*{box-sizing:border-box}
body{margin:0;background:#f7f2ea;color:#3f2b20;font-family:Arial,sans-serif}
.sidebar{width:235px;height:100vh;position:fixed;background:#3f2b20;padding:25px 16px;color:white}
.brand{font-size:22px;font-weight:bold;margin-bottom:5px}
.brand-info{font-size:12px;color:#d7c5b5;margin-bottom:30px}
.menu-link{display:flex;align-items:center;gap:12px;color:#e8ddd3;text-decoration:none;padding:12px;border-radius:9px;margin-bottom:7px;font-size:14px}
.menu-link:hover,.menu-link.active{background:#79583f;color:white}
.menu-link svg{width:18px;height:18px}
.keluar{position:absolute;bottom:22px;left:16px;right:16px}
.main{margin-left:235px;padding:28px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:25px}
.panel{background:white;padding:22px;border-radius:13px;box-shadow:0 3px 12px #3f2b2010;margin-bottom:22px}
.banner{background:linear-gradient(120deg,#50392c,#8b674e);color:white;padding:25px;border-radius:15px;margin-bottom:22px}
.banner h3{font-weight:bold}
.banner p{margin:0;color:#f0e4d9}
.btn-cokelat{background:#604532;color:white;border:0}
.btn-cokelat:hover{background:#493324;color:white}
.form-control,.form-select{border-color:#e3d6c9;padding:10px}
.alat{background:#faf7f3;border:1px solid #eee2d6;padding:14px;border-radius:10px;margin-bottom:12px}
.status{display:inline-block;padding:5px 10px;border-radius:20px;font-size:12px;background:#fff0c2;color:#896000}
@media(max-width:768px){
.sidebar{width:65px;padding:20px 8px}
.brand{font-size:12px;overflow-wrap:anywhere}
.brand-info,.menu-link span{display:none}
.menu-link{justify-content:center;padding:13px 5px}
.keluar{left:8px;right:8px}
.main{margin-left:65px;padding:15px}
.topbar{align-items:flex-start;flex-direction:column}
.panel{padding:14px}
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">TECHLOAN</div>
    <div class="brand-info">Sistem Peminjaman Alat</div>

    <a href="index.php" class="menu-link">
        <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
    </a>
    <a href="daftar_alat.php" class="menu-link">
        <i data-lucide="package"></i><span>Daftar Alat</span>
    </a>
    <a href="peminjaman_saya.php" class="menu-link">
        <i data-lucide="clipboard-list"></i><span>Peminjaman Saya</span>
    </a>
    <a href="pengembalian_saya.php" class="menu-link active">
        <i data-lucide="package-check"></i><span>Pengembalian Alat</span>
    </a>
    <a href="../logout.php" class="menu-link keluar">
        <i data-lucide="log-out"></i><span>Keluar</span>
    </a>
</aside>

<main class="main">
    <div class="topbar">
        <h4>Pengembalian Alat</h4>
        <div><?= e($nama) ?> · Peminjam</div>
    </div>

    <div class="banner">
        <h3>Pengajuan Pengembalian</h3>
        <p>Pilih peminjaman yang akan dikembalikan dan isi kondisi alat dengan benar.</p>
    </div>

    <?php if (isset($_GET['berhasil'])): ?>
        <div class="alert alert-success">
            Pengajuan pengembalian berhasil dikirim. Silakan tunggu proses Admin.
        </div>
    <?php endif; ?>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= e($jenis_pesan) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h5 class="mb-3">Ajukan Pengembalian</h5>

        <?php if (mysqli_num_rows($data_peminjaman) === 0): ?>
            <div class="text-muted">
                Tidak ada peminjaman berstatus Dipinjam yang bisa diajukan untuk pengembalian.
            </div>
        <?php else: ?>
            <form method="POST">
                <label class="form-label">Pilih Peminjaman</label>
                <select name="id_peminjaman" class="form-select mb-3" required>
                    <option value="">-- Pilih kode peminjaman --</option>
                    <?php while ($p = mysqli_fetch_assoc($data_peminjaman)): ?>
                        <option value="<?= (int)$p['id_peminjaman'] ?>">
                            <?= e($p['kode_peminjaman']) ?>
                            (<?= e($p['tanggal_pinjam']) ?> s.d. <?= e($p['tanggal_kembali']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <div class="alert alert-warning">
                    Pilih kondisi setiap alat sesuai kondisi saat dikembalikan.
                    Kondisi yang dipilih berlaku untuk seluruh jumlah alat pada baris tersebut.
                </div>

                <?php
                $stmt_alat = mysqli_prepare($koneksi, "
                    SELECT dp.id_peminjaman, dp.id_detail,
                           dp.jumlah_pinjam, a.nama_alat, a.kode_alat
                    FROM detail_peminjaman dp
                    JOIN alat a ON dp.id_alat = a.id_alat
                    JOIN peminjaman p ON dp.id_peminjaman = p.id_peminjaman
                    WHERE p.id_user = ? AND p.status = 'Dipinjam'
                    ORDER BY dp.id_peminjaman DESC
                ");
                mysqli_stmt_bind_param($stmt_alat, "i", $id_user);
                mysqli_stmt_execute($stmt_alat);
                $alat_dipinjam = mysqli_stmt_get_result($stmt_alat);
                ?>

                <label class="form-label">Kondisi Alat</label>
                <div class="mb-3">
                    <?php while ($a = mysqli_fetch_assoc($alat_dipinjam)): ?>
                        <div class="alat">
                            <strong><?= e($a['nama_alat']) ?></strong>
                            <div class="text-muted small mb-2">
                                <?= e($a['kode_alat']) ?> ·
                                <?= (int)$a['jumlah_pinjam'] ?> unit ·
                                <?= e($a['id_peminjaman']) ?>
                            </div>
                            <label class="form-label small">Kondisi saat dikembalikan</label>
                            <select
                                name="kondisi[<?= (int)$a['id_detail'] ?>]"
                                class="form-select"
                            >
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                                <option value="Rusak Berat">Hilang</option>

                            </select>
                        </div>
                    <?php endwhile; ?>
                </div>

                <label class="form-label">Catatan (opsional)</label>
                <textarea name="catatan" class="form-control mb-3" rows="3"
                    placeholder="Tambahkan catatan jika diperlukan"></textarea>

                <button type="submit" name="ajukan" class="btn btn-cokelat">
                    <i data-lucide="send" style="width:16px;height:16px;vertical-align:middle"></i>
                    Kirim Pengajuan
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h5 class="mb-3">Riwayat Pengajuan Pengembalian</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Kode Peminjaman</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Catatan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($riwayat) > 0): ?>
                    <?php while ($r = mysqli_fetch_assoc($riwayat)): ?>
                        <tr>
                            <td><?= e($r['kode_peminjaman']) ?></td>
                            <td><?= e($r['tanggal_pengajuan']) ?></td>
                            <td><?= e($r['catatan'] ?: '-') ?></td>
                            <td><span class="status"><?= e($r['status']) ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            Belum ada pengajuan pengembalian.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>
</body>
</html>
