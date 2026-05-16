<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Ambil tanggal hari ini dari MySQL
$today_mysql = $conn->query("SELECT CURDATE() as today")->fetch_assoc()['today'];
$today_formatted = date('d/m/Y', strtotime($today_mysql));

// Statistik
$total_pasien = $conn->query("SELECT COUNT(*) as total FROM pasien")->fetch_assoc()['total'];
$total_dokter = $conn->query("SELECT COUNT(*) as total FROM dokter")->fetch_assoc()['total'];
$total_poli = $conn->query("SELECT COUNT(*) as total FROM poli")->fetch_assoc()['total'];
$total_reservasi = $conn->query("SELECT COUNT(*) as total FROM reservasi")->fetch_assoc()['total'];

// Ambil filter tanggal dari URL (opsional)
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';

if ($filter_tanggal) {
    $reservasi_list = $conn->query("
        SELECT r.*, p.nama_poli, d.nama_dokter, ps.nama_lengkap
        FROM reservasi r
        JOIN poli p ON r.id_poli = p.id_poli
        JOIN dokter d ON r.id_dokter = d.id_dokter
        JOIN pasien ps ON r.id_pasien = ps.id_pasien
        WHERE r.tanggal_periksa = '$filter_tanggal'
        ORDER BY r.tanggal_periksa DESC, r.created_at DESC
    ");
    $judul_tabel = "Pendaftaran Tanggal " . date('d/m/Y', strtotime($filter_tanggal));
} else {
    $reservasi_list = $conn->query("
        SELECT r.*, p.nama_poli, d.nama_dokter, ps.nama_lengkap
        FROM reservasi r
        JOIN poli p ON r.id_poli = p.id_poli
        JOIN dokter d ON r.id_dokter = d.id_dokter
        JOIN pasien ps ON r.id_pasien = ps.id_pasien
        ORDER BY r.tanggal_periksa DESC, r.created_at DESC
        LIMIT 20
    ");
    $judul_tabel = "Pendaftaran Terbaru (20 data terakhir)";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MEDKLIK</title>
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

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px 24px;
            overflow-y: auto;
            height: 100vh;
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
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

        .filter-box {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: #333;
        }

        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-filter {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-reset {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
        }

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

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .main-content::-webkit-scrollbar {
            width: 4px;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>MEDKLIK</h2><p>Admin Panel</p></div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">Dashboard</a>
            <a href="antrian.php" class="nav-item">Kelola Antrian</a>
            <a href="kelola_poli.php" class="nav-item">Kelola Poli</a>
            <a href="kelola_dokter.php" class="nav-item">Kelola Dokter</a>
            <a href="kelola_pasien.php" class="nav-item">Kelola Pasien</a>
            <a href="laporan.php" class="nav-item">Laporan</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar">A</div><div><div class="user-name">Admin</div><div class="user-role">Administrator</div></div></div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>Dashboard Admin</h1>
            <p>Selamat datang di panel administrator MEDKLIK</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><h3>Total Pasien</h3><div class="value"><?= $total_pasien ?></div></div>
            <div class="stat-card"><h3>Total Dokter</h3><div class="value"><?= $total_dokter ?></div></div>
            <div class="stat-card"><h3>Total Poli</h3><div class="value"><?= $total_poli ?></div></div>
            <div class="stat-card"><h3>Total Pendaftaran</h3><div class="value"><?= $total_reservasi ?></div></div>
        </div>

        <div class="filter-box">
            <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
                <div class="filter-group"><label>Tanggal Periksa</label><input type="date" name="tanggal" value="<?= $filter_tanggal ?>"></div>
                <button type="submit" class="btn-filter">Filter</button>
                <?php if ($filter_tanggal): ?><a href="dashboard.php" class="btn-reset">Reset</a><?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <h3><?= $judul_tabel ?></h3>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead><tr><th>Tanggal Periksa</th><th>Pasien</th><th>Poli</th><th>Dokter</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($reservasi_list->num_rows > 0): ?>
                            <?php while($row = $reservasi_list->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                <td><span class="badge badge-<?= $row['status_reservasi'] ?>"><?= ucfirst($row['status_reservasi']) ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">Belum ada pendaftaran</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>