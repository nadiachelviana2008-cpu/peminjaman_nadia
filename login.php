<?php
    session_start();

    if (isset($_SESSION['level'])) {
        header("Location: index.php");
        exit;
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Login - Peminjaman Alat</title>
        <link rel="stylesheet" href="asset/bootstrap.min.css">
    </head>
    <body style="background:#f4eee5; min-height:100vh;">

    <div class="container d-flex justify-content-center align-items-center"
        style="min-height:100vh;">

        <div class="card shadow border-0 rounded-4"
            style="max-width:420px; width:100%;">

            <div class="card-body p-4 p-md-5">

                <div class="text-center mb-4">
                    <div class="mb-3" style="font-size:45px;">💻</div>
                    <h4 class="fw-bold" style="color:#684b35;">
                        TECHLOAN
                    </h4>
                    <p class="text-secondary mb-0">
                        Smart Computer Equipment Management
                    </p>
                    <p class="small text-secondary">
                        Solusi Digital Peminjaman dan Pengelolaan Perangkat Komputer
                    </p>
                </div>

                <?php if (isset($_GET['pesan'])) { ?>
                    <div class="alert alert-danger">
                        <?php
                        if ($_GET['pesan'] == 'gagal') {
                            echo "Username atau password salah!";
                        } elseif ($_GET['pesan'] == 'nonaktif') {
                            echo "Akun kamu sedang nonaktif!";
                        } elseif ($_GET['pesan'] == 'akses') {
                            echo "Silakan login terlebih dahulu!";
                        }
                        ?>
                    </div>
                <?php } ?>

                <form action="cek_login.php" method="POST">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username"
                            class="form-control"
                            placeholder="Masukkan username"
                            required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password"
                            class="form-control"
                            placeholder="Masukkan password"
                            required>
                    </div>

                    <button type="submit"
                            class="btn w-100 text-white py-2"
                            style="background:#79583d;">
                        Login
                    </button>

                </form>
            </div>
        </div>
    </div>

    </body>
</html>
