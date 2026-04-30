<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dokter') {
    header('Location: ../login.php');
    exit();
}

$id_user = $_SESSION['user_id'];

// Ambil data dokter
$dokter = $conn->query("
    SELECT d.*, p.nama_poli 
    FROM dokter d
    JOIN poli p ON d.id_poli = p.id_poli
    WHERE d.id_user = '$id_user'
")->fetch_assoc();

$id_dokter = $dokter['id_dokter'];

// Proses panggil pasien (tanpa form)
if (isset($_GET['panggil'])) {
    $id_reservasi = intval($_GET['panggil']);
    $conn->query("UPDATE antrian SET status_antrian = 'dipanggil' WHERE id_reservasi = '$id_reservasi'");
    $conn->query("UPDATE reservasi SET status_reservasi = 'dipanggil' WHERE id_reservasi = '$id_reservasi'");
    header("Location: dashboard.php");
    exit();
}

// Ambil daftar pasien hari ini
$pasien_list = $conn->query("
    SELECT r.id_reservasi, r.tanggal_periksa, r.keluhan, r.status_reservasi,
           a.nomor_antrian, a.status_antrian,
           p.id_pasien, p.nama_lengkap, p.jenis_pasien, p.no_bpjs
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN pasien p ON r.id_pasien = p.id_pasien
    WHERE r.id_dokter = '$id_dokter' 
      AND r.tanggal_periksa = CURDATE()
    ORDER BY a.nomor_antrian ASC
");

// Statistik
$total_menunggu = $conn->query("
    SELECT COUNT(*) as total FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    WHERE r.id_dokter = '$id_dokter' AND r.tanggal_periksa = CURDATE() AND a.status_antrian = 'menunggu'
")->fetch_assoc()['total'];

$total_dipanggil = $conn->query("
    SELECT COUNT(*) as total FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    WHERE r.id_dokter = '$id_dokter' AND r.tanggal_periksa = CURDATE() AND a.status_antrian = 'dipanggil'
")->fetch_assoc()['total'];

$total_selesai = $conn->query("
    SELECT COUNT(*) as total FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    WHERE r.id_dokter = '$id_dokter' AND r.tanggal_periksa = CURDATE() AND a.status_antrian = 'selesai'
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dokter - MEDKLIK</title>
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

        /* SIDEBAR - Sama dengan riwayat */
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

        /* Welcome Card - Sama dengan riwayat */
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            border: 1px solid #eef2f6;
        }

        .stat-card h3 {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1e3c72;
        }

        /* Table Card - Sama dengan riwayat */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eef2f6;
        }

        .table-card h3 {
            font-size: 14px;
            color: #1e3c72;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 12px;
        }

        th {
            color: #6c757d;
            font-weight: 500;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-menunggu {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-dipanggil {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-selesai {
            background: #d1fae5;
            color: #059669;
        }

        .btn-panggil {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-periksa {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-detail {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Dokter Panel</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">Dashboard</a>
            <a href="periksa.php" class="nav-item">Periksa Pasien</a>
            <a href="riwayat.php" class="nav-item">Riwayat</a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($dokter['nama_dokter'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($dokter['nama_dokter']) ?></div>
                    <div class="user-role"><?= htmlspecialchars($dokter['nama_poli']) ?></div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="welcome-card">
            <h1>Selamat Datang, <?= htmlspecialchars($dokter['nama_dokter']) ?></h1>
            <p><?= htmlspecialchars($dokter['nama_poli']) ?></p>
        </div>

        <!-- Statistik -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Menunggu</h3>
                <div class="value"><?= $total_menunggu ?></div>
            </div>
            <div class="stat-card">
                <h3>Dipanggil</h3>
                <div class="value"><?= $total_dipanggil ?></div>
            </div>
            <div class="stat-card">
                <h3>Selesai</h3>
                <div class="value"><?= $total_selesai ?></div>
            </div>
        </div>

        <!-- Daftar Pasien -->
        <div class="table-card">
            <h3>Daftar Pasien Hari Ini</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>No. Antrian</th>
                            <th>Nama Pasien</th>
                            <th>Jenis Pasien</th>
                            <th>No BPJS</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pasien_list->num_rows > 0): ?>
                            <?php while($row = $pasien_list->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $row['nomor_antrian'] ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td>
                                    <span class="badge <?= $row['jenis_pasien'] == 'BPJS' ? 'badge-dipanggil' : 'badge-selesai' ?>">
                                        <?= $row['jenis_pasien'] ?>
                                    </span>
                                </td>
                                <td><?= $row['no_bpjs'] ?? '-' ?></td>
                                <td><?= htmlspecialchars(substr($row['keluhan'], 0, 35)) ?>...</td>
                                <td>
                                    <?php if ($row['status_antrian'] == 'menunggu'): ?>
                                        <span class="badge badge-menunggu">Menunggu</span>
                                    <?php elseif ($row['status_antrian'] == 'dipanggil'): ?>
                                        <span class="badge badge-dipanggil">Dipanggil</span>
                                    <?php else: ?>
                                        <span class="badge badge-selesai">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status_antrian'] == 'menunggu'): ?>
                                        <a href="?panggil=<?= $row['id_reservasi'] ?>" class="btn-panggil" onclick="return confirm('Panggil pasien <?= $row['nama_lengkap'] ?>?')">Panggil</a>
                                    <?php elseif ($row['status_antrian'] == 'dipanggil'): ?>
                                        <a href="periksa.php?id=<?= $row['id_reservasi'] ?>" class="btn-periksa">Periksa</a>
                                    <?php else: ?>
                                        <a href="periksa.php?lihat=<?= $row['id_reservasi'] ?>" class="btn-detail">Lihat</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">Belum ada pasien yang terdaftar hari ini</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>