<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Proses Tambah Poli
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $nama_poli = $conn->real_escape_string($_POST['nama_poli']);
        $kuota_harian = intval($_POST['kuota_harian']);
        $durasi_pemeriksaan = intval($_POST['durasi_pemeriksaan']);
        
        $check = $conn->query("SELECT * FROM poli WHERE nama_poli = '$nama_poli'");
        if ($check->num_rows > 0) {
            $error = "Nama poli sudah ada!";
        } else {
            $conn->query("INSERT INTO poli (nama_poli, kuota_harian, durasi_pemeriksaan) 
                          VALUES ('$nama_poli', '$kuota_harian', '$durasi_pemeriksaan')");
            $success = "Poli berhasil ditambahkan!";
        }
    }
    
    // Proses Edit Poli
    if ($_POST['action'] == 'edit') {
        $id_poli = intval($_POST['id_poli']);
        $nama_poli = $conn->real_escape_string($_POST['nama_poli']);
        $kuota_harian = intval($_POST['kuota_harian']);
        $durasi_pemeriksaan = intval($_POST['durasi_pemeriksaan']);
        
        $conn->query("UPDATE poli SET nama_poli = '$nama_poli', kuota_harian = '$kuota_harian', durasi_pemeriksaan = '$durasi_pemeriksaan' 
                      WHERE id_poli = '$id_poli'");
        $success = "Poli berhasil diupdate!";
    }
    
    // Proses Hapus Poli
    if ($_POST['action'] == 'hapus') {
        $id_poli = intval($_POST['id_poli']);
        
        $cek_dokter = $conn->query("SELECT * FROM dokter WHERE id_poli = '$id_poli'");
        if ($cek_dokter->num_rows > 0) {
            $error = "Tidak bisa menghapus poli karena masih memiliki dokter!";
        } else {
            $conn->query("DELETE FROM poli WHERE id_poli = '$id_poli'");
            $success = "Poli berhasil dihapus!";
        }
    }
}

// Ambil semua data poli
$poli_list = $conn->query("SELECT * FROM poli ORDER BY id_poli");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Poli - MEDKLIK</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f9;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* SIDEBAR - Sama dengan dashboard */
        .sidebar {
            width: 260px;
            background: white;
            height: 100vh;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px 20px;
            border-bottom: 1px solid #eef2f6;
        }

        .sidebar-header h2 {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-header p {
            font-size: 11px;
            color: #6c757d;
            margin-top: 4px;
        }

        .nav-menu {
            flex: 1;
            padding: 16px 0;
        }

        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-item:hover {
            background: #f0f4f9;
            color: #2a5298;
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(30,60,114,0.1) 0%, rgba(42,82,152,0.1) 100%);
            color: #2a5298;
            border-right: 3px solid #2a5298;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #eef2f6;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .user-role {
            font-size: 11px;
            color: #6c757d;
        }

        .logout-btn {
            display: block;
            text-align: center;
            padding: 8px;
            background: #fee2e2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px 24px;
            overflow-y: auto;
            height: 100vh;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 12px;
            padding: 16px 24px;
            color: white;
            margin-bottom: 20px;
        }

        .welcome-card h1 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .welcome-card p {
            font-size: 12px;
            opacity: 0.85;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
        }

        .form-card h2 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 16px;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eef2f6;
        }

        .table-card h2 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2a5298;
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #e0e7ff;
            color: #2a5298;
        }

        .btn-hapus {
            background: #fee2e2;
            color: #dc2626;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 13px;
        }

        th {
            color: #6c757d;
            font-weight: 500;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .main-content::-webkit-scrollbar {
            width: 4px;
        }
        .main-content::-webkit-scrollbar-track {
            background: #e0e0e0;
            border-radius: 4px;
        }
        .main-content::-webkit-scrollbar-thumb {
            background: #2a5298;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Admin Panel</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="antrian.php" class="nav-item">Kelola Antrian</a>
            <a href="kelola_poli.php" class="nav-item active">Kelola Poli</a>
            <a href="kelola_dokter.php" class="nav-item">Kelola Dokter</a>
            <a href="kelola_pasien.php" class="nav-item">Kelola Pasien</a>
            <a href="laporan.php" class="nav-item">Laporan</a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">A</div>
                <div>
                    <div class="user-name">Admin</div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="welcome-card">
            <h1>Kelola Data Poli</h1>
            <p>Tambah, edit, atau hapus data poli</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form Tambah Poli -->
        <div class="form-card">
            <h2>Tambah Poli Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Poli</label>
                        <input type="text" name="nama_poli" placeholder="Contoh: Poli Mata" required>
                    </div>
                    <div class="form-group">
                        <label>Kuota Harian</label>
                        <input type="number" name="kuota_harian" value="20" required>
                    </div>
                    <div class="form-group">
                        <label>Durasi Pemeriksaan (menit)</label>
                        <input type="number" name="durasi_pemeriksaan" value="15" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Tambah Poli</button>
            </form>
        </div>

        <!-- Daftar Poli -->
        <div class="table-card">
            <h2>Daftar Poli</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Poli</th>
                            <th>Kuota Harian</th>
                            <th>Durasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $poli_list->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id_poli'] ?></td>
                            <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                            <td><?= $row['kuota_harian'] ?> pasien</td>
                            <td><?= $row['durasi_pemeriksaan'] ?> menit</td>
                            <td>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id_poli" value="<?= $row['id_poli'] ?>">
                                    <input type="text" name="nama_poli" value="<?= htmlspecialchars($row['nama_poli']) ?>" style="width:100px; padding:4px; margin-right:4px; border:1px solid #ddd; border-radius:4px;" required>
                                    <input type="number" name="kuota_harian" value="<?= $row['kuota_harian'] ?>" style="width:60px; padding:4px; margin-right:4px; border:1px solid #ddd; border-radius:4px;" required>
                                    <input type="number" name="durasi_pemeriksaan" value="<?= $row['durasi_pemeriksaan'] ?>" style="width:60px; padding:4px; margin-right:4px; border:1px solid #ddd; border-radius:4px;" required>
                                    <button type="submit" class="btn btn-edit" style="padding:4px 8px;">Edit</button>
                                </form>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin ingin menghapus poli <?= htmlspecialchars($row['nama_poli']) ?>?')">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id_poli" value="<?= $row['id_poli'] ?>">
                                    <button type="submit" class="btn btn-hapus" style="padding:4px 8px;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>