
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'Petugas') {
    header("Location: ../login.php");
    exit;
}

$pesan = "";

// PROSES AKSI
if (isset($_POST['aksi'])) {
    $id = (int) $_POST['id_peminjaman'];
    $aksi = $_POST['aksi'];

    if (in_array($aksi, ['Setujui', 'Tolak', 'Serahkan Alat'])) {
        mysqli_begin_transaction($koneksi);

        try {
            $stmt = mysqli_prepare($koneksi,
                "SELECT status FROM peminjaman
                 WHERE id_peminjaman = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $hasil = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($hasil);
            mysqli_stmt_close($stmt);

            if (!$data) {
                throw new Exception("Peminjaman tidak ditemukan.");
            }

            $id_petugas = (int) $_SESSION['id_user'];

            // SETUJUI ATAU TOLAK
            if ($aksi == 'Setujui' || $aksi == 'Tolak') {
                if ($data['status'] != 'Menunggu') {
                    throw new Exception(
                        "Pengajuan sudah diproses sebelumnya."
                    );
                }

                if ($aksi == 'Setujui') {
                    $stmt = mysqli_prepare($koneksi,
                        "SELECT d.id_alat, d.jumlah_pinjam,
                                a.stok, a.nama_alat
                         FROM detail_peminjaman d
                         JOIN alat a ON d.id_alat = a.id_alat
                         WHERE d.id_peminjaman = ?
                         FOR UPDATE");

                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $hasil = mysqli_stmt_get_result($stmt);

                    $detail = [];

                    while ($baris = mysqli_fetch_assoc($hasil)) {
                        $detail[] = $baris;
                    }

                    mysqli_stmt_close($stmt);

                    if (count($detail) == 0) {
                        throw new Exception(
                            "Detail alat peminjaman tidak ditemukan."
                        );
                    }

                    // CEK STOK SEMUA ALAT
                    foreach ($detail as $alat) {
                        if ($alat['stok'] < $alat['jumlah_pinjam']) {
                            throw new Exception(
                                "Stok alat " . $alat['nama_alat'] .
                                " tidak mencukupi."
                            );
                        }
                    }

                    // KURANGI STOK SAAT DISETUJUI
                    foreach ($detail as $alat) {
                        $stmt = mysqli_prepare($koneksi,
                            "UPDATE alat
                             SET stok = stok - ?
                             WHERE id_alat = ? AND stok >= ?");

                        mysqli_stmt_bind_param(
                            $stmt,
                            "iii",
                            $alat['jumlah_pinjam'],
                            $alat['id_alat'],
                            $alat['jumlah_pinjam']
                        );

                        mysqli_stmt_execute($stmt);

                        if (mysqli_stmt_affected_rows($stmt) != 1) {
                            mysqli_stmt_close($stmt);
                            throw new Exception(
                                "Stok alat gagal diperbarui."
                            );
                        }

                        mysqli_stmt_close($stmt);
                    }

                    $status = 'Disetujui';
                } else {
                    $status = 'Ditolak';
                }

                $stmt = mysqli_prepare($koneksi,
                    "UPDATE peminjaman
                     SET status = ?, id_petugas = ?,
                         tanggal_persetujuan = NOW()
                     WHERE id_peminjaman = ?
                     AND status = 'Menunggu'");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sii",
                    $status,
                    $id_petugas,
                    $id
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) != 1) {
                    mysqli_stmt_close($stmt);
                    throw new Exception(
                        "Status peminjaman gagal diperbarui."
                    );
                }

                mysqli_stmt_close($stmt);

                $pesan = "Pengajuan berhasil " .
                         strtolower($aksi) . ".";

            // KONFIRMASI PENYERAHAN ALAT
            } elseif ($aksi == 'Serahkan Alat') {
                if ($data['status'] != 'Disetujui') {
                    throw new Exception(
                        "Hanya peminjaman berstatus Disetujui " .
                        "yang bisa diserahkan."
                    );
                }

                $status = 'Dipinjam';

                $stmt = mysqli_prepare($koneksi,
                    "UPDATE peminjaman
                     SET status = ?, id_petugas = ?
                     WHERE id_peminjaman = ?
                     AND status = 'Disetujui'");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sii",
                    $status,
                    $id_petugas,
                    $id
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) != 1) {
                    mysqli_stmt_close($stmt);
                    throw new Exception(
                        "Status gagal diubah menjadi Dipinjam."
                    );
                }

                mysqli_stmt_close($stmt);

                $pesan = "Alat berhasil diserahkan. " .
                         "Status sekarang Dipinjam.";
            }

            mysqli_commit($koneksi);

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = "Gagal memproses pengajuan: " .
                     $e->getMessage();
        }
    }
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? '');
$cari_sql = mysqli_real_escape_string($koneksi, $cari);

$query = mysqli_query($koneksi,
    "SELECT p.id_peminjaman, p.kode_peminjaman,
            p.tanggal_pengajuan, p.tanggal_pinjam,
            p.tanggal_kembali, p.status,
            u.nama, u.kelas
     FROM peminjaman p
     JOIN pengguna u ON p.id_user = u.id_user
     WHERE (
         p.kode_peminjaman LIKE '%$cari_sql%'
         OR u.nama LIKE '%$cari_sql%'
         OR u.kelas LIKE '%$cari_sql%'
     )
     ORDER BY
         CASE p.status
             WHEN 'Menunggu' THEN 1
             WHEN 'Disetujui' THEN 2
             WHEN 'Dipinjam' THEN 3
             WHEN 'Selesai' THEN 4
             WHEN 'Ditolak' THEN 5
             ELSE 6
         END,
         p.tanggal_pengajuan DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Persetujuan Peminjaman - TECHLOAN</title>
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

        .konten {
            padding: 30px;
            min-height: 100vh;
        }

        .subjudul {
            color: #526783;
            font-size: 14px;
        }

        .judul {
            color: #3f2b20;
            font-weight: bold;
        }

        .header-card {
            background: #ead5bd;
            border: 1px solid #d8b998;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 3px 8px rgba(63, 43, 32, 0.08);
        }

        .kartu {
            background: white;
            border: 1px solid #eadfd2;
            border-radius: 14px;
        }

        .table th {
            background: #f1e3d2;
            color: #3f2b20;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-cokelat {
            background: #6f4e37;
            color: white;
        }

        .btn-cokelat:hover {
            background: #50392c;
            color: white;
        }

        .btn-kembali {
            display: inline-block;
            padding: 9px 14px;
            background: white;
            border: 1px solid #eadfd2;
            color: #3f2b20;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 22px;
            transition: background 0.2s, color 0.2s;
        }

        .btn-kembali:hover {
            background: #6f4e37;
            color: white;
        }

        .footer {
            text-align: center;
            color: #526783;
            font-size: 13px;
            margin-top: 50px;
        }

        @media (max-width: 768px) {
            .konten {
                padding: 18px 12px;
            }

            .header-card {
                padding: 16px;
                gap: 15px;
                align-items: flex-start !important;
            }

            .judul {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
<main class="konten">

    <a href="/UKK/peminjaman_nadia/petugas/index.php"
       class="btn-kembali">
        &larr; Kembali ke Dashboard
    </a>

    <div class="header-card d-flex justify-content-between align-items-center">
        <div>
            <h3 class="judul mb-1">Persetujuan Peminjaman</h3>
            <div class="subjudul">
                Periksa pengajuan dan konfirmasi penyerahan alat
            </div>
        </div>

        <div class="text-end">
            <strong>Petugas</strong>
            <div class="subjudul">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </div>
        </div>
    </div>

    <?php if ($pesan != ''): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="card kartu shadow-sm">
        <div class="card-body p-4">

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-5">
                    <input type="text" name="cari"
                           class="form-control"
                           placeholder="Cari kode, nama, atau kelas..."
                           value="<?= htmlspecialchars($cari) ?>">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-cokelat">
                        Cari
                    </button>

                    <a href="persetujuan.php"
                       class="btn btn-outline-secondary">
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
                            <th>Peminjam</th>
                            <th>Kelas</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= htmlspecialchars($row['kode_peminjaman']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['nama']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['kelas'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['tanggal_pengajuan']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['tanggal_pinjam']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['tanggal_kembali']) ?>
                                </td>

                                <td>
                                    <?php
                                    $warna = [
                                        'Menunggu' => 'warning',
                                        'Disetujui' => 'primary',
                                        'Ditolak' => 'danger',
                                        'Dipinjam' => 'info',
                                        'Selesai' => 'success'
                                    ];
                                    $badge = $warna[$row['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge text-bg-<?= $badge ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($row['status'] == 'Menunggu'): ?>
                                        <form method="POST"
                                              class="d-flex gap-1"
                                              onsubmit="return confirm('Yakin memproses pengajuan ini?')">

                                            <input type="hidden"
                                                   name="id_peminjaman"
                                                   value="<?= (int)$row['id_peminjaman'] ?>">

                                            <button type="submit"
                                                    name="aksi"
                                                    value="Setujui"
                                                    class="btn btn-success btn-sm">
                                                Setujui
                                            </button>

                                            <button type="submit"
                                                    name="aksi"
                                                    value="Tolak"
                                                    class="btn btn-danger btn-sm">
                                                Tolak
                                            </button>
                                        </form>

                                    <?php elseif ($row['status'] == 'Disetujui'): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Pastikan alat sudah benar-benar diserahkan kepada peminjam. Lanjutkan?')">

                                            <input type="hidden"
                                                   name="id_peminjaman"
                                                   value="<?= (int)$row['id_peminjaman'] ?>">

                                            <button type="submit"
                                                    name="aksi"
                                                    value="Serahkan Alat"
                                                    class="btn btn-cokelat btn-sm">
                                                Serahkan Alat
                                            </button>
                                        </form>

                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                Tidak ada data peminjaman.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="footer">
        © <?= date('Y') ?> TECHLOAN — Technology Lending System
    </div>

</main>
</body>
</html>
