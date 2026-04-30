<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header('Location: login.php');
    exit();
}

$id_pasien = $_SESSION['id_pasien'];

// Ambil riwayat medis
$riwayat = $conn->query("
    SELECT r.tanggal_periksa, p.nama_poli, d.nama_dokter, 
           a.nomor_antrian, a.status_antrian,
           rm.diagnosa, rm.resep_obat, rm.created_at as tgl_rekam
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN poli p ON r.id_poli = p.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
    LEFT JOIN rekam_medis rm ON r.id_reservasi = rm.id_reservasi
    WHERE r.id_pasien = '$id_pasien'
    ORDER BY r.created_at DESC
");

$pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_pasien'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Medis - MEDKLIK</title>
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

        /* SIDEBAR - Sama seperti dashboard */
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

        /* Welcome Card - Sama seperti dashboard */
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
            opacity: 0.85;
            font-size: 12px;
        }

        /* Riwayat Card */
        .riwayat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }

        .table-wrapper {
            overflow-x: auto;
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
            padding: 40px;
            color: #6c757d;
        }

        /* Scrollbar */
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
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Reservasi Klinik</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="pendaftaran.php" class="nav-item">Pendaftaran</a>
            <a href="riwayat.php" class="nav-item active">Riwayat Medis</a>
            <a href="antrian_saya.php" class="nav-item">Nomor Antrian</a>
            <a href="poli.php" class="nav-item">Lihat Poli</a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($pasien['nama_lengkap'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($pasien['nama_lengkap']) ?></div>
                    <div class="user-role">Pasien</div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>Riwayat Medis</h1>
            <p>Riwayat kunjungan dan rekam medis Anda.</p>
        </div>

        <div class="riwayat-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Poli</th>
                            <th>Dokter</th>
                            <th>No. Antrian</th>
                            <th>Diagnosa</th>
                            <th>Resep Obat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat->num_rows > 0): ?>
                            <?php while ($row = $riwayat->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                    <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                    <td><?= $row['nomor_antrian'] ?></td>
                                    <td><?= htmlspecialchars($row['diagnosa'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['resep_obat'] ?? '-') ?></td>
                                    <td>
                                        <?php 
                                        $status = $row['status_antrian'];
                                        if ($status == 'menunggu') {
                                            echo '<span class="badge badge-menunggu">Menunggu</span>';
                                        } elseif ($status == 'dipanggil') {
                                            echo '<span class="badge badge-dipanggil">Dipanggil</span>';
                                        } else {
                                            echo '<span class="badge badge-selesai">Selesai</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">Belum ada riwayat medis. Silakan daftar berobat terlebih dahulu.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>