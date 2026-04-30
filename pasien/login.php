<?php
require_once '../config/database.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'pasien') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $result = $conn->query("SELECT * FROM users WHERE username = '$username' AND role = 'pasien'");
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($password == $user['password']) {
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Ambil id_pasien
            $pasien = $conn->query("SELECT id_pasien FROM pasien WHERE id_user = '{$user['id_user']}'");
            if ($pasien->num_rows > 0) {
                $_SESSION['id_pasien'] = $pasien->fetch_assoc()['id_pasien'];
            }
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pasien - MEDKLIK</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo h1 { font-size: 26px; color: #1e3c72; }
        .title { text-align: center; margin-bottom: 8px; }
        .title h2 { font-size: 22px; color: #1e3c72; }
        .subtitle { text-align: center; color: #6c757d; font-size: 13px; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 500; margin-bottom: 5px; }
        .form-group input {
            width: 100%; padding: 10px 12px; border: 1.5px solid #e0e0e0;
            border-radius: 10px; font-size: 13px;
        }
        .form-group input:focus { outline: none; border-color: #2a5298; }
        .btn-login {
            width: 100%; padding: 10px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white; border: none; border-radius: 10px; font-size: 14px;
            font-weight: 600; cursor: pointer; margin-top: 8px;
        }
        .register-link { text-align: center; margin-top: 16px; }
        .register-link a { color: #2a5298; text-decoration: none; font-size: 13px; }
        .alert { padding: 10px; border-radius: 8px; margin-bottom: 16px; font-size: 12px; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .back-link { text-align: center; margin-top: 16px; }
        .back-link a { color: #6c757d; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo"><h1>MEDKLIK</h1></div>
        <div class="title"><h2>Login Pasien</h2></div>
        <div class="subtitle">Masuk ke akun pasien Anda</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="register-link">
            <a href="register.php">Belum punya akun? Daftar di sini</a>
        </div>
        <div class="back-link">
            <a href="../index.php"> Kembali ke Beranda</a>
        </div>
    </div>
</body>
</html>