<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Proses Export Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=laporan_medklik_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Ambil semua data
    $total_pendaftaran = $conn->query("SELECT COUNT(*) as total FROM reservasi")->fetch_assoc()['total'];
    $total_pasien = $conn->query("SELECT COUNT(*) as total FROM pasien")->fetch_assoc()['total'];
    $pasien_umum = $conn->query("SELECT COUNT(*) as total FROM pasien WHERE jenis_pasien = 'Umum'")->fetch_assoc()['total'];
    $pasien_bpjs = $conn->query("SELECT COUNT(*) as total FROM pasien WHERE jenis_pasien = 'BPJS'")->fetch_assoc()['total'];
    
    $status_menunggu = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'menunggu'")->fetch_assoc()['total'];
    $status_dipanggil = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'dipanggil'")->fetch_assoc()['total'];
    $status_selesai = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'selesai'")->fetch_assoc()['total'];
    
    $poli_stats = $conn->query("
        SELECT p.nama_poli, COUNT(r.id_reservasi) as jumlah
        FROM poli p
        LEFT JOIN reservasi r ON p.id_poli = r.id_poli
        GROUP BY p.id_poli
    ");
    
    $dokter_stats = $conn->query("
        SELECT d.nama_dokter, p.nama_poli, COUNT(r.id_reservasi) as jumlah
        FROM dokter d
        LEFT JOIN reservasi r ON d.id_dokter = r.id_dokter
        JOIN poli p ON d.id_poli = p.id_poli
        GROUP BY d.id_dokter
    ");
    
    $harian_stats = $conn->query("
        SELECT tanggal_periksa, COUNT(*) as jumlah
        FROM reservasi
        WHERE tanggal_periksa >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY tanggal_periksa
        ORDER BY tanggal_periksa DESC
    ");
    
    $bulanan_stats = $conn->query("
        SELECT DATE_FORMAT(tanggal_periksa, '%M %Y') as bulan, 
               DATE_FORMAT(tanggal_periksa, '%Y-%m') as bulan_key,
               COUNT(*) as jumlah
        FROM reservasi
        WHERE tanggal_periksa >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY bulan_key, bulan
        ORDER BY bulan_key DESC
    ");
    
    // Output Excel dengan format tabel rapi
    echo "<html>";
    echo "<head><title>Laporan MEDKLIK</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #1e3c72; font-size: 18px; margin-bottom: 10px; }
        h2 { color: #2a5298; font-size: 14px; margin-top: 20px; margin-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        th { background: #1e3c72; color: white; font-weight: bold; }
        .stat-table { width: auto; }
        .stat-table th, .stat-table td { padding: 4px 12px; }
    </style>";
    echo "</head><body>";
    
    echo "<h1>LAPORAN MEDKLIK</h1>";
    echo "<p>Tanggal: " . date('d/m/Y H:i:s') . "</p>";
    
    // Statistik Utama dalam bentuk tabel
    echo "<h2>STATISTIK UTAMA</h2>";
    echo "<table class='stat-table'>";
    echo "<tr><th>Keterangan</th><th>Jumlah</th></tr>";
    echo "<tr><td>Total Pendaftaran</td><td>$total_pendaftaran</td></tr>";
    echo "<tr><td>Total Pasien</td><td>$total_pasien</td></tr>";
    echo "<tr><td>Pasien Umum</td><td>$pasien_umum</td></tr>";
    echo "<tr><td>Pasien BPJS</td><td>$pasien_bpjs</td></tr>";
    echo "<tr><td>Status Menunggu</td><td>$status_menunggu</td></tr>";
    echo "<tr><td>Status Dipanggil</td><td>$status_dipanggil</td></tr>";
    echo "<tr><td>Status Selesai</td><td>$status_selesai</td></tr>";
    echo "</table>";
    
    // Pendaftaran per Poli
    echo "<h2>PENDAFTARAN PER POLI</h2>";
    echo "<table>";
    echo "<tr><th>Poli</th><th>Jumlah Pendaftaran</th></tr>";
    while($row = $poli_stats->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['nama_poli']) . "</td><td>" . $row['jumlah'] . "</td></tr>";
    }
    echo "</table>";
    
    // Pendaftaran per Dokter
    echo "<h2>PENDAFTARAN PER DOKTER</h2>";
    echo "<table>";
    echo "<tr><th>Dokter</th><th>Poli</th><th>Jumlah Pasien</th></tr>";
    while($row = $dokter_stats->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['nama_dokter']) . "</td><td>" . htmlspecialchars($row['nama_poli']) . "</td><td>" . $row['jumlah'] . "</td></tr>";
    }
    echo "</table>";
    
    // Pendaftaran 7 Hari Terakhir
    echo "<h2>PENDAFTARAN 7 HARI TERAKHIR</h2>";
    echo "<table>";
    echo "<tr><th>Tanggal</th><th>Jumlah Pendaftaran</th></tr>";
    while($row = $harian_stats->fetch_assoc()) {
        echo "<tr><td>" . date('d/m/Y', strtotime($row['tanggal_periksa'])) . "</td><td>" . $row['jumlah'] . "</td></tr>";
    }
    echo "</table>";
    
    // Pendaftaran per Bulan
    echo "<h2>PENDAFTARAN PER BULAN (6 BULAN TERAKHIR)</h2>";
    echo "<table>";
    echo "<tr><th>Bulan</th><th>Jumlah Pendaftaran</th></tr>";
    while($row = $bulanan_stats->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['bulan']) . "</td><td>" . $row['jumlah'] . "</td></tr>";
    }
    echo "</table>";
    
    echo "</body></html>";
    exit();
}

// ========== TAMPILAN WEB (SAMA DENGAN DASHBOARD) ==========
$total_pendaftaran = $conn->query("SELECT COUNT(*) as total FROM reservasi")->fetch_assoc()['total'];
$total_pasien = $conn->query("SELECT COUNT(*) as total FROM pasien")->fetch_assoc()['total'];
$pasien_umum = $conn->query("SELECT COUNT(*) as total FROM pasien WHERE jenis_pasien = 'Umum'")->fetch_assoc()['total'];
$pasien_bpjs = $conn->query("SELECT COUNT(*) as total FROM pasien WHERE jenis_pasien = 'BPJS'")->fetch_assoc()['total'];

$status_menunggu = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'menunggu'")->fetch_assoc()['total'];
$status_dipanggil = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'dipanggil'")->fetch_assoc()['total'];
$status_selesai = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE status_reservasi = 'selesai'")->fetch_assoc()['total'];

$poli_stats = $conn->query("
    SELECT p.nama_poli, COUNT(r.id_reservasi) as jumlah
    FROM poli p
    LEFT JOIN reservasi r ON p.id_poli = r.id_poli
    GROUP BY p.id_poli
    ORDER BY jumlah DESC
");

$dokter_stats = $conn->query("
    SELECT d.nama_dokter, p.nama_poli, COUNT(r.id_reservasi) as jumlah
    FROM dokter d
    LEFT JOIN reservasi r ON d.id_dokter = r.id_dokter
    JOIN poli p ON d.id_poli = p.id_poli
    GROUP BY d.id_dokter
    ORDER BY jumlah DESC
");

$harian_stats = $conn->query("
    SELECT tanggal_periksa, COUNT(*) as jumlah
    FROM reservasi
    WHERE tanggal_periksa >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY tanggal_periksa
    ORDER BY tanggal_periksa DESC
");

$bulanan_stats = $conn->query("
    SELECT DATE_FORMAT(tanggal_periksa, '%M %Y') as bulan, 
           DATE_FORMAT(tanggal_periksa, '%Y-%m') as bulan_key,
           COUNT(*) as jumlah
    FROM reservasi
    WHERE tanggal_periksa >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY bulan_key, bulan
    ORDER BY bulan_key DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - MEDKLIK</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .welcome-card h1 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .welcome-card p {
            font-size: 12px;
            opacity: 0.85;
        }

        /* Export Button */
        .btn-excel {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-excel:hover {
            background: #059669;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            font-size: 28px;
            font-weight: 700;
            color: #1e3c72;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
        }

        .section-card h2 {
            font-size: 16px;
            color: #1e3c72;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eef2f6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 12px;
        }

        th {
            color: #6c757d;
            font-weight: 500;
            background: #f8fafc;
        }

        .main-content::-webkit-scrollbar {
            width: 4px;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="kelola_poli.php" class="nav-item">Kelola Poli</a>
            <a href="kelola_dokter.php" class="nav-item">Kelola Dokter</a>
            <a href="kelola_pasien.php" class="nav-item">Kelola Pasien</a>
            <a href="laporan.php" class="nav-item active">Laporan</a>
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
            <div>
                <h1>Laporan Statistik</h1>
                <p>Data statistik dan laporan sistem MEDKLIK</p>
            </div>
            <a href="?export=excel" class="btn-excel">Export Excel</a>
        </div>

        <!-- Kartu Statistik -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Pendaftaran</h3>
                <div class="value"><?= $total_pendaftaran ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Pasien</h3>
                <div class="value"><?= $total_pasien ?></div>
            </div>
            <div class="stat-card">
                <h3>Pasien Umum</h3>
                <div class="value"><?= $pasien_umum ?></div>
            </div>
            <div class="stat-card">
                <h3>Pasien BPJS</h3>
                <div class="value"><?= $pasien_bpjs ?></div>
            </div>
        </div>

        <!-- Status Pendaftaran -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Menunggu</h3>
                <div class="value"><?= $status_menunggu ?></div>
            </div>
            <div class="stat-card">
                <h3>Dipanggil</h3>
                <div class="value"><?= $status_dipanggil ?></div>
            </div>
            <div class="stat-card">
                <h3>Selesai</h3>
                <div class="value"><?= $status_selesai ?></div>
            </div>
        </div>

        <!-- Pendaftaran per Poli -->
        <div class="section-card">
            <h2>Pendaftaran per Poli</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Poli</th>
                            <th>Jumlah Pendaftaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $poli_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><strong><?= $row['jumlah'] ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pendaftaran per Dokter -->
        <div class="section-card">
            <h2>Pendaftaran per Dokter</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Dokter</th>
                            <th>Poli</th>
                            <th>Jumlah Pasien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $dokter_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><strong><?= $row['jumlah'] ?></strong></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pendaftaran 7 Hari Terakhir -->
        <div class="section-card">
            <h2>Pendaftaran 7 Hari Terakhir</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jumlah Pendaftaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($harian_stats->num_rows > 0): ?>
                            <?php while($row = $harian_stats->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                    <td><strong><?= $row['jumlah'] ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2">Belum ada数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pendaftaran per Bulan -->
        <div class="section-card">
            <h2>Pendaftaran per Bulan (6 Bulan Terakhir)</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Jumlah Pendaftaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bulanan_stats->num_rows > 0): ?>
                            <?php while($row = $bulanan_stats->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['bulan']) ?></td>
                                    <td><strong><?= $row['jumlah'] ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2">Belum ada数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>