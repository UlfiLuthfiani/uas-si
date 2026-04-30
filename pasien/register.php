<?php
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Data diri
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $nik = $conn->real_escape_string($_POST['nik']);
    $tanggal_lahir = $conn->real_escape_string($_POST['tanggal_lahir']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $no_hp = $conn->real_escape_string($_POST['no_hp']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);
    $jenis_pasien = $conn->real_escape_string($_POST['jenis_pasien']);
    $no_bpjs = isset($_POST['no_bpjs']) ? $conn->real_escape_string($_POST['no_bpjs']) : null;
    
    if ($password != $confirm_password) {
        $error = 'Password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        $check = $conn->query("SELECT * FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = 'Username sudah digunakan!';
        } else {
            $check_email = $conn->query("SELECT * FROM pasien WHERE email = '$email'");
            if ($check_email->num_rows > 0) {
                $error = 'Email sudah terdaftar!';
            } else {
                // Insert ke users
                $conn->query("INSERT INTO users (username, password, role) VALUES ('$username', '$password', 'pasien')");
                $id_user = $conn->insert_id;
                
                // Insert ke pasien (termasuk no_bpjs jika BPJS)
                $sql = "INSERT INTO pasien (id_user, nama_lengkap, nik, email, tanggal_lahir, alamat, no_hp, jenis_kelamin, jenis_pasien, no_bpjs) 
                        VALUES ('$id_user', '$nama_lengkap', '$nik', '$email', '$tanggal_lahir', '$alamat', '$no_hp', '$jenis_kelamin', '$jenis_pasien', " . ($no_bpjs ? "'$no_bpjs'" : "NULL") . ")";
                
                if ($conn->query($sql)) {
                    $success = 'Registrasi berhasil! Silakan login.';
                } else {
                    $error = 'Gagal mendaftar: ' . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - MEDKLIK</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .title {
            text-align: center;
            margin-bottom: 4px;
        }

        .title h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1e3c72;
        }

        .subtitle {
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 12px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2a5298;
        }

        .btn-register {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .divider {
            text-align: center;
            margin: 14px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            background: white;
            padding: 0 10px;
            position: relative;
            color: #adb5bd;
            font-size: 11px;
        }

        .login-link {
            text-align: center;
        }

        .login-link p {
            color: #6c757d;
            font-size: 12px;
        }

        .login-link a {
            color: #2a5298;
            text-decoration: none;
            font-weight: 600;
        }

        .alert {
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 12px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
        }

        .footer-date {
            text-align: center;
            margin-top: 16px;
            font-size: 10px;
            color: #adb5bd;
        }

        .no-bpjs-field {
            transition: all 0.3s ease;
        }

        @media (max-width: 500px) {
            .register-card {
                padding: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
    <script>
        function toggleNoBPJS() {
            var jenisPasien = document.getElementById('jenis_pasien').value;
            var noBpjsGroup = document.getElementById('no_bpjs_group');
            
            if (jenisPasien === 'BPJS') {
                noBpjsGroup.style.display = 'block';
                document.getElementById('no_bpjs').required = true;
            } else {
                noBpjsGroup.style.display = 'none';
                document.getElementById('no_bpjs').required = false;
                document.getElementById('no_bpjs').value = '';
            }
        }
    </script>
</head>
<body>
    <div class="register-card">
        <div class="logo">
            <h1>MEDKLIK</h1>
        </div>

        <div class="title">
            <h2>Daftar Akun</h2>
        </div>
        <div class="subtitle">Lengkapi data diri untuk mendaftar</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?> <a href="../pasien/login.php" style="color:#059669;">Login</a></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Buat username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Email aktif" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="confirm_password" placeholder="Ulangi password" required>
                </div>
            </div>

            <hr style="margin: 16px 0; border: none; border-top: 1px solid #eef2f6;">

            <div class="form-row">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" placeholder="Nama lengkap" required>
                </div>
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" placeholder="16 digit NIK" maxlength="16" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" required>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select name="jenis_kelamin" required>
                        <option value="">Pilih</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <input type="text" name="alamat" placeholder="Alamat lengkap" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>No HP</label>
                    <input type="tel" name="no_hp" placeholder="Nomor telepon" required>
                </div>
                <div class="form-group">
                    <label>Jenis Pasien</label>
                    <select name="jenis_pasien" id="jenis_pasien" onchange="toggleNoBPJS()" required>
                        <option value="Umum">Umum</option>
                        <option value="BPJS">BPJS</option>
                    </select>
                </div>
            </div>

            <!-- Field No BPJS (hidden by default) -->
            <div class="form-group no-bpjs-field" id="no_bpjs_group" style="display: none;">
                <label>No BPJS</label>
                <input type="text" name="no_bpjs" id="no_bpjs" placeholder="Masukkan nomor BPJS" maxlength="20">
            </div>

            <button type="submit" class="btn-register">Daftar</button>
        </form>

        <div class="divider">
            <span>atau</span>
        </div>

        <div class="login-link">
            <p>Sudah punya akun? <a href="../pasien/login.php">Login</a></p>
        </div>

        <div class="footer-date">
            <?= date('H:i') ?> | <?= date('d/m/Y') ?>
        </div>
    </div>

    <script>
        // Inisialisasi saat halaman load
        document.addEventListener('DOMContentLoaded', function() {
            toggleNoBPJS();
        });
    </script>
</body>
</html>