
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
    return 'Rp' . number_format($angka, 0, ',', '.');
}

$pesan = "";

// SIMPAN PEMBAYARAN
if (isset($_POST['bayar'])) {
    $id = (int) $_POST['id_pembayaran'];
    $jumlah = (float) $_POST['jumlah_dibayar'];
    $metode = $_POST['metode_pembayaran'];

    if ($jumlah <= 0 || !in_array($metode, ['Tunai', 'Transfer'])) {
        $pesan = "Jumlah atau metode pembayaran tidak valid.";
    } else {
        $stmt = $koneksi->prepare("
            SELECT total_denda, jumlah_dibayar, status
            FROM pembayaran_denda
            WHERE id_pembayaran = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            $pesan = "Data pembayaran tidak ditemukan.";
        } elseif ($data['status'] === 'Lunas') {
            $pesan = "Denda ini sudah lunas.";
        } else {
            $total = (float) $data['total_denda'];
            $sudah = (float) $data['jumlah_dibayar'];
            $sisa = $total - $sudah;

            if ($jumlah > $sisa) {
                $pesan = "Pembayaran melebihi sisa denda "
                    . rupiah($sisa) . ".";
            } else {
                $baru = $sudah + $jumlah;
                $status = ($baru >= $total) ? 'Lunas' : 'Belum Dibayar';
                $tanggal = date('Y-m-d');

                $stmt = $koneksi->prepare("
                    UPDATE pembayaran_denda
                    SET jumlah_dibayar = ?,
                        metode_pembayaran = ?,
                        tanggal_bayar = ?,
                        status = ?
                    WHERE id_pembayaran = ?
                ");
                $stmt->bind_param(
                    "dsssi",
                    $baru,
                    $metode,
                    $tanggal,
                    $status,
                    $id
                );

                if ($stmt->execute()) {
                    $pesan = "Pembayaran berhasil dicatat. Status: " . $status . ".";
                } else {
                    $pesan = "Pembayaran gagal disimpan.";
                }
                $stmt->close();
            }
        }
    }
}

// SINKRONKAN DATA DENDA DARI PENGEMBALIAN
$koneksi->query("
    INSERT INTO pembayaran_denda
        (id_pengembalian, total_denda)
    SELECT p.id_pengembalian, p.total_denda
    FROM pengembalian p
    WHERE p.total_denda > 0
    ON DUPLICATE KEY UPDATE
        total_denda = IF(
            pembayaran_denda.status = 'Lunas',
            pembayaran_denda.total_denda,
            p.total_denda
        )
");

// AMBIL DATA PEMBAYARAN
$data = $koneksi->query("
    SELECT
        pd.*,
        u.nama AS nama_peminjam,
        p.id_peminjaman
    FROM pembayaran_denda pd
    JOIN pengembalian p
        ON pd.id_pengembalian = p.id_pengembalian
    JOIN peminjaman pm
        ON p.id_peminjaman = pm.id_peminjaman
    JOIN pengguna u
        ON pm.id_user = u.id_user
    ORDER BY pd.id_pembayaran DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran Denda</title>
    <link rel="stylesheet" href="../asset/bootstrap.min.css">
</head>

<body style="background:#f7f1e8;">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 style="color:#6b4226;">Pembayaran Denda</h3>
            <p class="text-muted mb-0">
                Kelola pembayaran denda pengembalian alat.
            </p>
        </div>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if ($pesan != ""): ?>
        <div class="alert alert-info">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead style="background:#ead8c0;">
                        <tr>
                            <th>No.</th>
                            <th>Peminjam</th>
                            <th>Total Denda</th>
                            <th>Dibayar</th>
                            <th>Sisa Denda</th>
                            <th>Metode</th>
                            <th>Tanggal Bayar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($data && $data->num_rows > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = $data->fetch_assoc()): ?>
                            <?php
                            $sisa = max(
                                0,
                                (float) $row['total_denda']
                                - (float) $row['jumlah_dibayar']
                            );
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($row['nama_peminjam']) ?></td>
                                <td><?= rupiah($row['total_denda']) ?></td>
                                <td><?= rupiah($row['jumlah_dibayar']) ?></td>
                                <td><?= rupiah($sisa) ?></td>
                                <td><?= e($row['metode_pembayaran'] ?? '-') ?></td>
                                <td><?= e($row['tanggal_bayar'] ?? '-') ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Lunas'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            Belum Dibayar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] !== 'Lunas'): ?>
                                        <button
                                            class="btn btn-sm btn-primary"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#bayar<?= $row['id_pembayaran'] ?>">
                                            Bayar
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success">Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if ($row['status'] !== 'Lunas'): ?>
                            <tr class="collapse" id="bayar<?= $row['id_pembayaran'] ?>">
                                <td colspan="9">
                                    <form method="POST" class="row g-2 align-items-end">
                                        <input type="hidden"
                                               name="id_pembayaran"
                                               value="<?= (int) $row['id_pembayaran'] ?>">

                                        <div class="col-md-4">
                                            <label class="form-label">
                                                Jumlah yang Diterima
                                            </label>
                                            <input type="number"
                                                   name="jumlah_dibayar"
                                                   class="form-control"
                                                   min="1"
                                                   max="<?= $sisa ?>"
                                                   step="1"
                                                   required>
                                            <small class="text-muted">
                                                Sisa: <?= rupiah($sisa) ?>
                                            </small>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">
                                                Metode Pembayaran
                                            </label>
                                            <select name="metode_pembayaran"
                                                    class="form-select" required>
                                                <option value="">Pilih Metode</option>
                                                <option value="Tunai">Tunai</option>
                                                <option value="Transfer">Transfer</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <button type="submit"
                                                    name="bayar"
                                                    class="btn btn-success">
                                                Simpan Pembayaran
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                Belum ada data denda yang harus dibayar.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../asset/bootstrap.bundle.min.js"></script>
</body>
</html>
