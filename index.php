<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEDKLIK - Sistem Reservasi Kesehatan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .logo h1 { font-size: 32px; color: #1e3c72; margin-bottom: 8px; }
        .logo p { color: #6c757d; margin-bottom: 32px; }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: block;
            padding: 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-pasien { background: #2a5298; color: white; }
        .btn-dokter { background: #1e3c72; color: white; }
        .btn-admin { background: #0f2b4f; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .footer { margin-top: 32px; font-size: 11px; color: #adb5bd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>MEDKLIK</h1>
            <p>Sistem Reservasi Pelayanan Kesehatan</p>
        </div>
        <div class="btn-group">
            <a href="pasien/login.php" class="btn btn-pasien">Login sebagai Pasien</a>
            <a href="dokter/login.php" class="btn btn-dokter">Login sebagai Dokter</a>
            <a href="admin/login.php" class="btn btn-admin">Login sebagai Admin</a>
        </div>
        <div class="footer">
            <p>Belum punya akun? <a href="pasien/register.php">Daftar sebagai Pasien</a></p>
            <p>&copy; <?= date('Y') ?> MEDKLIK</p>
        </div>
    </div>
</body>
</html>