<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function tanggal($tgl) {
    return date('d-m-Y', strtotime($tgl));
}

if (empty($_SESSION['token_pengembalian'])) {
    $_SESSION['token_pengembalian'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_pengembalian'];
$pesan = $_SESSION['pesan_pengembalian'] ?? '';
unset($_SESSION['pesan_pengembalian']);

function kembali($pesan) {
    $_SESSION['pesan_pengembalian'] = $pesan;
    header("Location: pengembalian.php");
    exit;
}

function validasiKondisi($kondisi) {
    return in_array($kondisi, [
        'Baik',
        'Rusak Ringan',
        'Rusak Sedang',
        'Rusak Berat',
        'Hilang'
    ], true);
}

// DENDA TERLAMBAT + DENDA KONDISI
function hitungDenda(
    $tanggal_kembali,
    $tanggal_dikembalikan,
    $jumlah,
    $kondisi,
    $harga_penggantian
) {
    $batas = new DateTime($tanggal_kembali);
    $aktual = new DateTime($tanggal_dikembalikan);

    $denda_terlambat = 0;

    if ($aktual > $batas) {
        $hari = (int)$batas->diff($aktual)->days;
        $denda_terlambat = $hari * $jumlah * 2000;
    }

    $tarif = [
        'Baik' => 0,
        'Rusak Ringan' => 10000,
        'Rusak Sedang' => 25000,
        'Rusak Berat' => 50000,
        'Hilang' => (float)$harga_penggantian
    ];

    return $denda_terlambat + ($tarif[$kondisi] * $jumlah);
}

// MEMBALIKKAN PERUBAHAN STOK SAAT EDIT / HAPUS
function batalkanDampakPengembalian($koneksi, $id_pengembalian) {
    $q = $koneksi->prepare("
        SELECT dp.id_alat, dp.jumlah_pinjam, dk.kondisi_kembali
        FROM detail_pengembalian dk
        JOIN detail_peminjaman dp ON dk.id_detail = dp.id_detail
        WHERE dk.id_pengembalian = ?
    ");
    $q->bind_param("i", $id_pengembalian);
    $q->execute();
    $hasil = $q->get_result();

    while ($d = $hasil->fetch_assoc()) {
        $id_alat = (int)$d['id_alat'];
        $jumlah = (int)$d['jumlah_pinjam'];
        $kondisi = $d['kondisi_kembali'];

        if ($kondisi === 'Baik') {
            $sql = "UPDATE alat
                    SET stok = stok - ?, jumlah_baik = jumlah_baik - ?
                    WHERE id_alat = ?";
            $u = $koneksi->prepare($sql);
            $u->bind_param("iii", $jumlah, $jumlah, $id_alat);
        } elseif ($kondisi === 'Rusak Ringan') {
            $u = $koneksi->prepare(
                "UPDATE alat SET rusak_ringan = rusak_ringan - ? WHERE id_alat = ?"
            );
            $u->bind_param("ii", $jumlah, $id_alat);
        } elseif ($kondisi === 'Rusak Sedang') {
            $u = $koneksi->prepare(
                "UPDATE alat SET rusak_sedang = rusak_sedang - ? WHERE id_alat = ?"
            );
            $u->bind_param("ii", $jumlah, $id_alat);
        } elseif ($kondisi === 'Rusak Berat') {
            $u = $koneksi->prepare(
                "UPDATE alat SET rusak_berat = rusak_berat - ? WHERE id_alat = ?"
            );
            $u->bind_param("ii", $jumlah, $id_alat);
        } else {
            // Alat hilang tidak pernah ditambahkan kembali ke stok.
            continue;
        }

        $u->execute();
        $u->close();
    }

    $q->close();
}

// MENAMBAHKAN DAMPAK STOK DAN MENYIMPAN DETAIL
function terapkanDampakPengembalian(
    $koneksi,
    $id_peminjaman,
    $id_pengembalian,
    $tanggal_dikembalikan,
    $catatan,
    $kondisi,
    $keterangan
) {
    $q = $koneksi->prepare("
        SELECT dp.id_detail, dp.id_alat, dp.jumlah_pinjam,
               p.tanggal_kembali, a.harga_penggantian
        FROM detail_peminjaman dp
        JOIN peminjaman p ON dp.id_peminjaman = p.id_peminjaman
        JOIN alat a ON dp.id_alat = a.id_alat
        WHERE dp.id_peminjaman = ?
    ");
    $q->bind_param("i", $id_peminjaman);
    $q->execute();
    $hasil = $q->get_result();

    $total_denda = 0;
    $jumlah_detail = 0;

    while ($d = $hasil->fetch_assoc()) {
        $id_detail = (int)$d['id_detail'];
        $id_alat = (int)$d['id_alat'];
        $jumlah = (int)$d['jumlah_pinjam'];

        if (!isset($kondisi[$id_detail]) ||
            !validasiKondisi($kondisi[$id_detail])) {
            throw new Exception("Kondisi semua alat wajib dipilih dengan benar.");
        }

        $keadaan = $kondisi[$id_detail];
        $ket = trim($keterangan[$id_detail] ?? '');
        $harga = (float)$d['harga_penggantian'];

        if ($keadaan === 'Hilang' && $harga <= 0) {
            throw new Exception(
                "Harga penggantian alat harus diisi sebelum alat dinyatakan hilang."
            );
        }

        if ($keadaan === 'Baik') {
            $u = $koneksi->prepare("
                UPDATE alat
                SET stok = stok + ?, jumlah_baik = jumlah_baik + ?
                WHERE id_alat = ?
            ");
            $u->bind_param("iii", $jumlah, $jumlah, $id_alat);
        } elseif ($keadaan === 'Rusak Ringan') {
            $u = $koneksi->prepare("
                UPDATE alat SET rusak_ringan = rusak_ringan + ?
                WHERE id_alat = ?
            ");
            $u->bind_param("ii", $jumlah, $id_alat);
        } elseif ($keadaan === 'Rusak Sedang') {
            $u = $koneksi->prepare("
                UPDATE alat SET rusak_sedang = rusak_sedang + ?
                WHERE id_alat = ?
            ");
            $u->bind_param("ii", $jumlah, $id_alat);
        } elseif ($keadaan === 'Rusak Berat') {
            $u = $koneksi->prepare("
                UPDATE alat SET rusak_berat = rusak_berat + ?
                WHERE id_alat = ?
            ");
            $u->bind_param("ii", $jumlah, $id_alat);
        } else {
            // Hilang: stok tidak ditambah.
            $u = null;
        }

        if ($u !== null) {
            $u->execute();
            $u->close();
        }

        $denda = hitungDenda(
            $d['tanggal_kembali'],
            $tanggal_dikembalikan,
            $jumlah,
            $keadaan,
            $harga
        );

        $total_denda += $denda;

        $ins = $koneksi->prepare("
            INSERT INTO detail_pengembalian
                (id_pengembalian, id_detail, kondisi_kembali, denda, keterangan)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->bind_param(
            "iisis",
            $id_pengembalian,
            $id_detail,
            $keadaan,
            $denda,
            $ket
        );
        $ins->execute();
        $ins->close();

        $jumlah_detail++;
    }

    $q->close();

    if ($jumlah_detail === 0) {
        throw new Exception("Detail peminjaman tidak ditemukan.");
    }

    $u = $koneksi->prepare("
        UPDATE pengembalian
        SET tanggal_dikembalikan = ?, total_denda = ?, catatan = ?
        WHERE id_pengembalian = ?
    ");
    $u->bind_param(
        "sisi",
        $tanggal_dikembalikan,
        $total_denda,
        $catatan,
        $id_pengembalian
    );
    $u->execute();
    $u->close();
}

// PROSES PERSETUJUAN PENGEMBALIAN DARI PEMINJAM
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['aksi'] ?? '', ['terima_pengajuan', 'tolak_pengajuan'], true)
) {
    if (!hash_equals($token, $_POST['token'] ?? '')) {
        kembali("Permintaan tidak valid.");
    }

    $id_pengajuan = (int)($_POST['id_pengajuan'] ?? 0);
    $aksi = $_POST['aksi'];

    try {
        $koneksi->begin_transaction();

        $q = $koneksi->prepare("
            SELECT id_pengajuan, id_peminjaman, tanggal_pengajuan,
                   catatan, status
            FROM pengajuan_pengembalian
            WHERE id_pengajuan = ?
            FOR UPDATE
        ");
        $q->bind_param("i", $id_pengajuan);
        $q->execute();
        $pengajuan = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$pengajuan || $pengajuan['status'] !== 'Menunggu') {
            throw new Exception("Pengajuan tidak ditemukan atau sudah diproses.");
        }

        $id_peminjaman = (int)$pengajuan['id_peminjaman'];
        $id_admin = (int)$_SESSION['id_user'];

        if ($aksi === 'tolak_pengajuan') {
            $u = $koneksi->prepare("
                UPDATE pengajuan_pengembalian
                SET status = 'Ditolak'
                WHERE id_pengajuan = ? AND status = 'Menunggu'
            ");
            $u->bind_param("i", $id_pengajuan);
            $u->execute();

            if ($u->affected_rows !== 1) {
                $u->close();
                throw new Exception("Pengajuan gagal ditolak.");
            }
            $u->close();

            $aktivitas = "Menolak pengajuan pengembalian ID $id_pengajuan";
            $log = $koneksi->prepare("
                INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
                VALUES (?, ?, NOW())
            ");
            $log->bind_param("is", $id_admin, $aktivitas);
            $log->execute();
            $log->close();

            $koneksi->commit();
            kembali("Pengajuan pengembalian berhasil ditolak.");
        }

        $q = $koneksi->prepare("
            SELECT tanggal_kembali, status
            FROM peminjaman
            WHERE id_peminjaman = ?
            FOR UPDATE
        ");
        $q->bind_param("i", $id_peminjaman);
        $q->execute();
        $pinjam = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$pinjam || $pinjam['status'] !== 'Dipinjam') {
            throw new Exception("Peminjaman tidak berstatus Dipinjam.");
        }

        $q = $koneksi->prepare("
            SELECT COUNT(*) AS total
            FROM detail_peminjaman WHERE id_peminjaman = ?
        ");
        $q->bind_param("i", $id_peminjaman);
        $q->execute();
        $total_detail = (int)$q->get_result()->fetch_assoc()['total'];
        $q->close();

        $q = $koneksi->prepare("
            SELECT COUNT(*) AS total
            FROM detail_pengajuan_pengembalian
            WHERE id_pengajuan = ?
        ");
        $q->bind_param("i", $id_pengajuan);
        $q->execute();
        $total_diajukan = (int)$q->get_result()->fetch_assoc()['total'];
        $q->close();

        if ($total_detail === 0 || $total_detail !== $total_diajukan) {
            throw new Exception("Data kondisi semua alat belum lengkap.");
        }

        $q = $koneksi->prepare("
            SELECT dp.id_detail, dp.id_alat, dp.jumlah_pinjam,
                   dpk.kondisi_kembali, a.harga_penggantian
            FROM detail_peminjaman dp
            JOIN detail_pengajuan_pengembalian dpk
                ON dpk.id_detail = dp.id_detail
            JOIN alat a ON a.id_alat = dp.id_alat
            WHERE dp.id_peminjaman = ? AND dpk.id_pengajuan = ?
            FOR UPDATE
        ");
        $q->bind_param("ii", $id_peminjaman, $id_pengajuan);
        $q->execute();
        $hasil = $q->get_result();

        $detail = [];
        while ($baris = $hasil->fetch_assoc()) {
            $detail[] = $baris;
        }
        $q->close();

        if (count($detail) !== $total_detail) {
            throw new Exception("Detail kondisi pengembalian tidak cocok.");
        }

        foreach ($detail as $d) {
            if (!validasiKondisi($d['kondisi_kembali'])) {
                throw new Exception("Kondisi alat tidak valid.");
            }

            if (
                $d['kondisi_kembali'] === 'Hilang' &&
                (float)$d['harga_penggantian'] <= 0
            ) {
                throw new Exception(
                    "Harga penggantian alat hilang harus diisi terlebih dahulu."
                );
            }
        }

        $q = $koneksi->prepare("
            SELECT id_pengembalian FROM pengembalian
            WHERE id_peminjaman = ? FOR UPDATE
        ");
        $q->bind_param("i", $id_peminjaman);
        $q->execute();
        $sudah_ada = $q->get_result()->num_rows > 0;
        $q->close();

        if ($sudah_ada) {
            throw new Exception("Data pengembalian sudah tercatat.");
        }

        $tgl_kembali = date(
            'Y-m-d',
            strtotime($pengajuan['tanggal_pengajuan'])
        );
        $catatan = $pengajuan['catatan'] ?? '';

        $ins = $koneksi->prepare("
            INSERT INTO pengembalian
                (id_peminjaman, tanggal_dikembalikan,
                 id_petugas, total_denda, catatan)
            VALUES (?, ?, ?, 0, ?)
        ");
        $ins->bind_param("isis", $id_peminjaman, $tgl_kembali, $id_admin, $catatan);
        $ins->execute();
        $id_pengembalian = $koneksi->insert_id;
        $ins->close();

        $total_denda = 0;

        foreach ($detail as $d) {
            $id_detail = (int)$d['id_detail'];
            $id_alat = (int)$d['id_alat'];
            $jumlah = (int)$d['jumlah_pinjam'];
            $keadaan = $d['kondisi_kembali'];
            $harga = (float)$d['harga_penggantian'];

            if ($keadaan === 'Baik') {
                $u = $koneksi->prepare("
                    UPDATE alat SET stok = stok + ?, jumlah_baik = jumlah_baik + ?
                    WHERE id_alat = ?
                ");
                $u->bind_param("iii", $jumlah, $jumlah, $id_alat);
            } elseif ($keadaan === 'Rusak Ringan') {
                $u = $koneksi->prepare("
                    UPDATE alat SET rusak_ringan = rusak_ringan + ?
                    WHERE id_alat = ?
                ");
                $u->bind_param("ii", $jumlah, $id_alat);
            } elseif ($keadaan === 'Rusak Sedang') {
                $u = $koneksi->prepare("
                    UPDATE alat SET rusak_sedang = rusak_sedang + ?
                    WHERE id_alat = ?
                ");
                $u->bind_param("ii", $jumlah, $id_alat);
            } elseif ($keadaan === 'Rusak Berat') {
                $u = $koneksi->prepare("
                    UPDATE alat SET rusak_berat = rusak_berat + ?
                    WHERE id_alat = ?
                ");
                $u->bind_param("ii", $jumlah, $id_alat);
            } else {
                $u = null;
            }

            if ($u !== null) {
                $u->execute();
                $u->close();
            }

            $denda = hitungDenda(
                $pinjam['tanggal_kembali'],
                $tgl_kembali,
                $jumlah,
                $keadaan,
                $harga
            );
            $total_denda += $denda;

            $keterangan = 'Kondisi dilaporkan oleh peminjam dan diperiksa Admin';

            $ins = $koneksi->prepare("
                INSERT INTO detail_pengembalian
                    (id_pengembalian, id_detail, kondisi_kembali, denda, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->bind_param(
                "iisis",
                $id_pengembalian,
                $id_detail,
                $keadaan,
                $denda,
                $keterangan
            );
            $ins->execute();
            $ins->close();
        }

        $u = $koneksi->prepare("
            UPDATE pengembalian SET total_denda = ?
            WHERE id_pengembalian = ?
        ");
        $u->bind_param("ii", $total_denda, $id_pengembalian);
        $u->execute();
        $u->close();

        $u = $koneksi->prepare("
            UPDATE peminjaman SET status = 'Selesai'
            WHERE id_peminjaman = ? AND status = 'Dipinjam'
        ");
        $u->bind_param("i", $id_peminjaman);
        $u->execute();

        if ($u->affected_rows !== 1) {
            $u->close();
            throw new Exception("Status peminjaman gagal diperbarui.");
        }
        $u->close();

        $u = $koneksi->prepare("
            UPDATE pengajuan_pengembalian SET status = 'Disetujui'
            WHERE id_pengajuan = ? AND status = 'Menunggu'
        ");
        $u->bind_param("i", $id_pengajuan);
        $u->execute();

        if ($u->affected_rows !== 1) {
            $u->close();
            throw new Exception("Status pengajuan gagal diperbarui.");
        }
        $u->close();

        $aktivitas = "Menerima pengembalian peminjaman ID $id_peminjaman";
        $log = $koneksi->prepare("
            INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
            VALUES (?, ?, NOW())
        ");
        $log->bind_param("is", $id_admin, $aktivitas);
        $log->execute();
        $log->close();

        $koneksi->commit();
        kembali("Pengembalian berhasil diterima. Total denda: " . rupiah($total_denda));

    } catch (Throwable $err) {
        $koneksi->rollback();
        kembali("Gagal memproses pengajuan: " . $err->getMessage());
    }
}

// PROSES TAMBAH, EDIT, HAPUS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, $_POST['token'] ?? '')) {
        kembali("Permintaan tidak valid.");
    }

    $aksi = $_POST['aksi'] ?? '';

    try {
        $koneksi->begin_transaction();

        if ($aksi === 'simpan' || $aksi === 'edit') {
            $id_peminjaman = (int)($_POST['id_peminjaman'] ?? 0);
            $tgl = $_POST['tanggal_dikembalikan'] ?? '';
            $catatan = trim($_POST['catatan'] ?? '');
            $kondisi = $_POST['kondisi'] ?? [];
            $keterangan = $_POST['keterangan'] ?? [];

            $tanggal_valid = DateTime::createFromFormat('Y-m-d', $tgl);

            if (!$tanggal_valid || $tanggal_valid->format('Y-m-d') !== $tgl) {
                throw new Exception("Tanggal pengembalian tidak valid.");
            }

            if ($tgl > date('Y-m-d')) {
                throw new Exception("Tanggal pengembalian tidak boleh melebihi hari ini.");
            }

            $q = $koneksi->prepare("
                SELECT id_peminjaman, status FROM peminjaman
                WHERE id_peminjaman = ? FOR UPDATE
            ");
            $q->bind_param("i", $id_peminjaman);
            $q->execute();
            $pinjam = $q->get_result()->fetch_assoc();
            $q->close();

            if (!$pinjam) {
                throw new Exception("Data peminjaman tidak ditemukan.");
            }

            if ($aksi === 'simpan') {
                if ($pinjam['status'] !== 'Dipinjam') {
                    throw new Exception("Hanya peminjaman berstatus Dipinjam yang bisa dikembalikan.");
                }

                $cek = $koneksi->prepare("
                    SELECT id_pengembalian FROM pengembalian
                    WHERE id_peminjaman = ?
                ");
                $cek->bind_param("i", $id_peminjaman);
                $cek->execute();
                $sudah_ada = $cek->get_result()->num_rows > 0;
                $cek->close();

                if ($sudah_ada) {
                    throw new Exception("Pengembalian sudah tercatat.");
                }

                $id_petugas = (int)$_SESSION['id_user'];
                $ins = $koneksi->prepare("
                    INSERT INTO pengembalian
                        (id_peminjaman, tanggal_dikembalikan, id_petugas, total_denda, catatan)
                    VALUES (?, ?, ?, 0, ?)
                ");
                $ins->bind_param("isis", $id_peminjaman, $tgl, $id_petugas, $catatan);
                $ins->execute();
                $id_pengembalian = $koneksi->insert_id;
                $ins->close();
            } else {
                $id_pengembalian = (int)($_POST['id_pengembalian'] ?? 0);

                $cek = $koneksi->prepare("
                    SELECT id_pengembalian, id_peminjaman
                    FROM pengembalian WHERE id_pengembalian = ? FOR UPDATE
                ");
                $cek->bind_param("i", $id_pengembalian);
                $cek->execute();
                $data_lama = $cek->get_result()->fetch_assoc();
                $cek->close();

                if (!$data_lama ||
                    (int)$data_lama['id_peminjaman'] !== $id_peminjaman) {
                    throw new Exception("Data pengembalian tidak cocok.");
                }

                batalkanDampakPengembalian($koneksi, $id_pengembalian);

                $del = $koneksi->prepare("
                    DELETE FROM detail_pengembalian WHERE id_pengembalian = ?
                ");
                $del->bind_param("i", $id_pengembalian);
                $del->execute();
                $del->close();
            }

            terapkanDampakPengembalian(
                $koneksi,
                $id_peminjaman,
                $id_pengembalian,
                $tgl,
                $catatan,
                $kondisi,
                $keterangan
            );

            $u = $koneksi->prepare("
                UPDATE peminjaman SET status = 'Selesai'
                WHERE id_peminjaman = ?
            ");
            $u->bind_param("i", $id_peminjaman);
            $u->execute();
            $u->close();

            $aktivitas = ($aksi === 'simpan')
                ? "Mencatat pengembalian peminjaman ID $id_peminjaman"
                : "Mengedit pengembalian peminjaman ID $id_peminjaman";

            $id_user = (int)$_SESSION['id_user'];
            $log = $koneksi->prepare("
                INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
                VALUES (?, ?, NOW())
            ");
            $log->bind_param("is", $id_user, $aktivitas);
            $log->execute();
            $log->close();

            $koneksi->commit();
            kembali($aksi === 'simpan'
                ? "Pengembalian berhasil disimpan."
                : "Pengembalian berhasil diperbarui.");
        }

        if ($aksi === 'hapus') {
            $id_pengembalian = (int)($_POST['id_pengembalian'] ?? 0);

            $q = $koneksi->prepare("
                SELECT id_peminjaman FROM pengembalian
                WHERE id_pengembalian = ? FOR UPDATE
            ");
            $q->bind_param("i", $id_pengembalian);
            $q->execute();
            $data = $q->get_result()->fetch_assoc();
            $q->close();

            if (!$data) {
                throw new Exception("Data pengembalian tidak ditemukan.");
            }

            $id_peminjaman = (int)$data['id_peminjaman'];

            batalkanDampakPengembalian($koneksi, $id_pengembalian);

            $del = $koneksi->prepare("
                DELETE FROM detail_pengembalian WHERE id_pengembalian = ?
            ");
            $del->bind_param("i", $id_pengembalian);
            $del->execute();
            $del->close();

            $del = $koneksi->prepare("
                DELETE FROM pengembalian WHERE id_pengembalian = ?
            ");
            $del->bind_param("i", $id_pengembalian);
            $del->execute();
            $del->close();

            $u = $koneksi->prepare("
                UPDATE peminjaman SET status = 'Dipinjam'
                WHERE id_peminjaman = ?
            ");
            $u->bind_param("i", $id_peminjaman);
            $u->execute();
            $u->close();

            $aktivitas = "Menghapus pengembalian peminjaman ID $id_peminjaman";
            $id_user = (int)$_SESSION['id_user'];

            $log = $koneksi->prepare("
                INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
                VALUES (?, ?, NOW())
            ");
            $log->bind_param("is", $id_user, $aktivitas);
            $log->execute();
            $log->close();

            $koneksi->commit();
            kembali("Data pengembalian berhasil dihapus.");
        }

        throw new Exception("Aksi tidak dikenal.");

    } catch (Throwable $err) {
        $koneksi->rollback();
        kembali("Gagal memproses pengembalian: " . $err->getMessage());
    }
}

// FORM EDIT
$mode = $_GET['mode'] ?? '';
$id_edit = (int)($_GET['id'] ?? 0);
$id_pilih = (int)($_GET['peminjaman'] ?? 0);
$form = null;
$detail_form = [];

if ($mode === 'edit' && $id_edit > 0) {
    $q = $koneksi->prepare("
        SELECT pg.*, p.kode_peminjaman, p.id_peminjaman
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        WHERE pg.id_pengembalian = ?
    ");
    $q->bind_param("i", $id_edit);
    $q->execute();
    $form = $q->get_result()->fetch_assoc();
    $q->close();

    if ($form) {
        $id_pilih = (int)$form['id_peminjaman'];

        $q = $koneksi->prepare("
            SELECT id_detail, kondisi_kembali, keterangan
            FROM detail_pengembalian
            WHERE id_pengembalian = ?
        ");
        $q->bind_param("i", $id_edit);
        $q->execute();
        $hasil = $q->get_result();

        while ($d = $hasil->fetch_assoc()) {
            $detail_form[(int)$d['id_detail']] = $d;
        }
        $q->close();
    }
}

$peminjaman_tersedia = $koneksi->query("
    SELECT p.id_peminjaman, p.kode_peminjaman,
           p.tanggal_kembali, u.nama
    FROM peminjaman p
    JOIN pengguna u ON p.id_user = u.id_user
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    WHERE p.status = 'Dipinjam' OR pg.id_pengembalian IS NOT NULL
    ORDER BY p.id_peminjaman DESC
");

$detail_alat = [];

if ($id_pilih > 0) {
    $q = $koneksi->prepare("
        SELECT dp.id_detail, dp.jumlah_pinjam, a.nama_alat,
               a.kode_alat, a.harga_penggantian
        FROM detail_peminjaman dp
        JOIN alat a ON dp.id_alat = a.id_alat
        WHERE dp.id_peminjaman = ?
        ORDER BY dp.id_detail
    ");
    $q->bind_param("i", $id_pilih);
    $q->execute();
    $hasil = $q->get_result();

    while ($d = $hasil->fetch_assoc()) {
        $detail_alat[] = $d;
    }
    $q->close();
}

$pengajuan_kembali = $koneksi->query("
    SELECT pp.id_pengajuan, pp.id_peminjaman,
           pp.tanggal_pengajuan, pp.catatan, pp.status,
           p.kode_peminjaman, u.nama, u.kelas
    FROM pengajuan_pengembalian pp
    JOIN peminjaman p ON pp.id_peminjaman = p.id_peminjaman
    JOIN pengguna u ON p.id_user = u.id_user
    ORDER BY
        CASE pp.status
            WHEN 'Menunggu' THEN 1
            WHEN 'Disetujui' THEN 2
            WHEN 'Ditolak' THEN 3
            ELSE 4
        END,
        pp.tanggal_pengajuan DESC
");

$cari = trim($_GET['cari'] ?? '');
$keyword = '%' . $cari . '%';

$q = $koneksi->prepare("
    SELECT pg.*, p.kode_peminjaman, u.nama,
           (SELECT COUNT(*) FROM detail_pengembalian dk
            WHERE dk.id_pengembalian = pg.id_pengembalian) AS jumlah_detail
    FROM pengembalian pg
    JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
    JOIN pengguna u ON p.id_user = u.id_user
    WHERE p.kode_peminjaman LIKE ? OR u.nama LIKE ?
    ORDER BY pg.id_pengembalian DESC
");
$q->bind_param("ss", $keyword, $keyword);
$q->execute();
$riwayat = $q->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengembalian Alat - TECHLOAN</title>
    <link rel="stylesheet" href="../asset/bootstrap.min.css">
    <style>
        body { background: #f7f1e8; color: #49372b; }
        .navbar { background: #654832; }
        .navbar a { color: white; text-decoration: none; }
        .card { border: 1px solid #e4d5c4; border-radius: 12px; }
        .judul { color: #654832; font-weight: 700; }
        .btn-cokelat { background: #654832; color: white; }
        .btn-cokelat:hover { background: #4c3425; color: white; }
        .table thead th { background: #eee1d2; color: #49372b; white-space: nowrap; }
        .table td { vertical-align: middle; }
        .form-control, .form-select { border-color: #d8c4ae; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark px-4 py-3">
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
        <h3 class="judul">Pengembalian Alat</h3>
        <p class="text-secondary mb-0">
            Kelola pengajuan, kondisi alat, dan denda pengembalian.
        </p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-info"><?= e($pesan) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="judul mb-3">Pengajuan Pengembalian dari Peminjam</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>No.</th><th>Kode Peminjaman</th><th>Peminjam</th>
                            <th>Kelas</th><th>Tanggal Pengajuan</th>
                            <th>Catatan</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($pengajuan_kembali && $pengajuan_kembali->num_rows > 0): ?>
                        <?php $no_pengajuan = 1; ?>
                        <?php while ($pg = $pengajuan_kembali->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no_pengajuan++ ?></td>
                                <td><?= e($pg['kode_peminjaman']) ?></td>
                                <td><?= e($pg['nama']) ?></td>
                                <td><?= e($pg['kelas'] ?? '-') ?></td>
                                <td><?= e(tanggal($pg['tanggal_pengajuan'])) ?></td>
                                <td><?= e($pg['catatan'] ?: '-') ?></td>
                                <td>
                                    <?php
                                    $warna_status = [
                                        'Menunggu' => 'warning',
                                        'Disetujui' => 'success',
                                        'Ditolak' => 'danger'
                                    ];
                                    $warna = $warna_status[$pg['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge text-bg-<?= e($warna) ?>">
                                        <?= e($pg['status']) ?>
                                    </span>
                                </td>
                                <td>
                                <?php if ($pg['status'] === 'Menunggu'): ?>
                                    <div class="d-flex gap-1">
                                        <form method="post" onsubmit="return confirm('Terima pengembalian ini?')">
                                            <input type="hidden" name="token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id_pengajuan" value="<?= (int)$pg['id_pengajuan'] ?>">
                                            <button class="btn btn-success btn-sm" name="aksi" value="terima_pengajuan">Terima</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Tolak pengajuan ini?')">
                                            <input type="hidden" name="token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id_pengajuan" value="<?= (int)$pg['id_pengajuan'] ?>">
                                            <button class="btn btn-danger btn-sm" name="aksi" value="tolak_pengajuan">Tolak</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sudah diproses</span>
                                <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada pengajuan pengembalian.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($mode === 'tambah' || $mode === 'edit'): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="judul mb-3"><?= $mode === 'edit' ? 'Edit Pengembalian' : 'Tambah Pengembalian' ?></h5>

            <form method="get" class="mb-4">
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <?php if ($mode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $id_edit ?>">
                <?php endif; ?>
                <label class="form-label">Peminjaman</label>
                <select name="peminjaman" class="form-select" required onchange="this.form.submit()">
                    <option value="">-- Pilih Peminjaman --</option>
                    <?php
                    $peminjaman_tersedia->data_seek(0);
                    while ($p = $peminjaman_tersedia->fetch_assoc()):
                    ?>
                        <option value="<?= (int)$p['id_peminjaman'] ?>"
                            <?= $id_pilih === (int)$p['id_peminjaman'] ? 'selected' : '' ?>>
                            <?= e($p['kode_peminjaman']) ?> - <?= e($p['nama']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <?php if ($id_pilih > 0 && $detail_alat): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="aksi" value="<?= $mode === 'edit' ? 'edit' : 'simpan' ?>">
                <input type="hidden" name="id_peminjaman" value="<?= $id_pilih ?>">
                <?php if ($mode === 'edit'): ?>
                    <input type="hidden" name="id_pengembalian" value="<?= $id_edit ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Tanggal Dikembalikan</label>
                    <input type="date" name="tanggal_dikembalikan" class="form-control" required
                           max="<?= date('Y-m-d') ?>"
                           value="<?= e($form['tanggal_dikembalikan'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr><th>No.</th><th>Alat</th><th>Jumlah</th><th>Kondisi</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($detail_alat as $i => $d):
                            $id_detail = (int)$d['id_detail'];
                            $lama = $detail_form[$id_detail] ?? [];
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?= e($d['nama_alat']) ?><br>
                                    <small><?= e($d['kode_alat']) ?></small><br>
                                    <small>Harga penggantian: <?= e(rupiah($d['harga_penggantian'])) ?></small>
                                </td>
                                <td><?= (int)$d['jumlah_pinjam'] ?></td>
                                <td>
                                    <select name="kondisi[<?= $id_detail ?>]" class="form-select" required>
                                        <?php foreach (['Baik','Rusak Ringan','Rusak Sedang','Rusak Berat','Hilang'] as $k): ?>
                                            <option value="<?= e($k) ?>"
                                                <?= ($lama['kondisi_kembali'] ?? 'Baik') === $k ? 'selected' : '' ?>>
                                                <?= e($k) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="keterangan[<?= $id_detail ?>]"
                                           class="form-control"
                                           value="<?= e($lama['keterangan'] ?? '') ?>"
                                           placeholder="Catatan kondisi alat">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning">
                    Denda terlambat Rp2.000 per alat per hari.
                    Rusak ringan Rp10.000, rusak sedang Rp25.000, rusak berat Rp50.000 per alat.
                    Alat hilang dikenai harga penggantian.
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan Pengembalian</label>
                    <textarea name="catatan" class="form-control" rows="3"><?= e($form['catatan'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-cokelat"
                        onclick="return confirm('Simpan pengembalian ini?')">Simpan Pengembalian</button>
                <a href="pengembalian.php" class="btn btn-secondary">Batal</a>
            </form>
            <?php elseif ($id_pilih > 0): ?>
                <div class="alert alert-warning">Detail alat tidak ditemukan.</div>
            <?php else: ?>
                <p class="text-secondary mb-0">Pilih peminjaman untuk mengisi pengembalian.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="mb-3">
            <a href="?mode=tambah" class="btn btn-cokelat">+ Tambah Pengembalian Manual</a>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <h5 class="judul mb-0">Riwayat Pengembalian</h5>
                <form method="get" class="d-flex gap-2">
                    <input type="text" name="cari" class="form-control"
                           placeholder="Cari kode / nama" value="<?= e($cari) ?>">
                    <button class="btn btn-cokelat">Cari</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>No.</th><th>Kode Peminjaman</th><th>Nama Peminjam</th>
                            <th>Tanggal Kembali</th><th>Jumlah Detail</th>
                            <th>Total Denda</th><th>Catatan</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($riwayat->num_rows > 0): ?>
                        <?php $no = 1; while ($r = $riwayat->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($r['kode_peminjaman']) ?></td>
                                <td><?= e($r['nama']) ?></td>
                                <td><?= e(tanggal($r['tanggal_dikembalikan'])) ?></td>
                                <td><?= (int)$r['jumlah_detail'] ?></td>
                                <td><?= e(rupiah($r['total_denda'])) ?></td>
                                <td><?= e($r['catatan'] ?: '-') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="?mode=edit&id=<?= (int)$r['id_pengembalian'] ?>"
                                           class="btn btn-sm btn-warning">Edit</a>
                                        <form method="post"
                                              onsubmit="return confirm('Hapus pengembalian ini?')">
                                            <input type="hidden" name="token" value="<?= e($token) ?>">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_pengembalian"
                                                   value="<?= (int)$r['id_pengembalian'] ?>">
                                            <button class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-secondary">Belum ada data pengembalian.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>