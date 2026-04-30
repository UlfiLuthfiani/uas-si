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

// Ambil riwayat pemeriksaan
$riwayat = $conn->query("
    SELECT rm.*, r.tanggal_periksa, p.nama_lengkap, p.jenis_pasien, p.no_bpjs
    FROM rekam_medis rm
    JOIN reservasi r ON rm.id_reservasi = r.id_reservasi
    JOIN pasien p ON rm.id_pasien = p.id_pasien
    WHERE rm.id_dokter = '$id_dokter'
    ORDER BY r.tanggal_periksa DESC, rm.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pemeriksaan - MEDKLIK</title>
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

        /* Table Card */
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
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-bpjs {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-umum {
            background: #d1fae5;
            color: #059669;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
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
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="periksa.php" class="nav-item">Periksa Pasien</a>
            <a href="riwayat.php" class="nav-item active">Riwayat</a>
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
            <h1>Riwayat Pemeriksaan</h1>
            <p>Daftar riwayat pemeriksaan pasien yang telah ditangani</p>
        </div>

        <div class="table-card">
            <h3>Data Riwayat Pemeriksaan</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama Pasien</th>
                            <th>Jenis Pasien</th>
                            <th>No BPJS</th>
                            <th>Diagnosa</th>
                            <th>Resep Obat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat->num_rows > 0): ?>
                            <?php while($row = $riwayat->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td>
                                    <span class="badge <?= $row['jenis_pasien'] == 'BPJS' ? 'badge-bpjs' : 'badge-umum' ?>">
                                        <?= $row['jenis_pasien'] ?>
                                    </span>
                                </td>
                                <td><?= $row['no_bpjs'] ?? '-' ?></td>
                                <td><?= htmlspecialchars(substr($row['diagnosa'], 0, 50)) ?><?= strlen($row['diagnosa']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars(substr($row['resep_obat'], 0, 50)) ?><?= strlen($row['resep_obat']) > 50 ? '...' : '' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">Belum ada riwayat pemeriksaan</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>