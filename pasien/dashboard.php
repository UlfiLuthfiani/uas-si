<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header('Location: login.php');
    exit();
}

$id_pasien = $_SESSION['id_pasien'];

// Ambil data pasien
$pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_pasien'")->fetch_assoc();

// Ambil antrian aktif
$antrian_aktif = $conn->query("
    SELECT r.*, a.nomor_antrian, a.status_antrian, a.estimasi_waktu_jam,
           p.nama_poli, d.nama_dokter
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN poli p ON r.id_poli = p.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
    WHERE r.id_pasien = '$id_pasien' AND a.status_antrian IN ('menunggu', 'dipanggil')
    ORDER BY r.created_at DESC
    LIMIT 1
")->fetch_assoc();

// Ambil riwayat reservasi terbaru (3 data)
$riwayat = $conn->query("
    SELECT r.*, a.nomor_antrian, a.status_antrian,
           p.nama_poli, d.nama_dokter
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN poli p ON r.id_poli = p.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
    WHERE r.id_pasien = '$id_pasien'
    ORDER BY r.created_at DESC
    LIMIT 3
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MEDKLIK</title>
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

        /* SIDEBAR */
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
            padding: 14px 20px;
            color: white;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .welcome-text h1 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .welcome-text p {
            opacity: 0.85;
            font-size: 11px;
        }

        .btn-pendaftaran {
            background: white;
            color: #1e3c72;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        /* Kartu Antrian */
        .antrian-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 16px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }

        .antrian-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .antrian-header h3 {
            font-size: 13px;
            color: #1e3c72;
            font-weight: 600;
        }

        .badge-terdaftar {
            background: #d1fae5;
            color: #059669;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
        }

        .nomor-antrian-box {
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .label-antrian {
            font-size: 9px;
            color: #6c757d;
            letter-spacing: 1px;
        }

        .nomor-antrian {
            font-size: 32px;
            font-weight: 800;
            color: #1e3c72;
            margin: 4px 0;
        }

        .estimasi {
            font-size: 10px;
            color: #2a5298;
            font-weight: 500;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            text-align: center;
            padding-top: 8px;
            border-top: 1px solid #eef2f6;
        }

        .info-label {
            font-size: 9px;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
        }

        .no-antrian {
            background: #f1f5f9;
            text-align: center;
            padding: 16px;
            border-radius: 12px;
            color: #6c757d;
            font-size: 12px;
            margin-bottom: 16px;
        }

        /* Riwayat */
        .riwayat-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }

        .riwayat-card h3 {
            font-size: 13px;
            color: #1e3c72;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 6px 4px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 10px;
        }

        th {
            color: #6c757d;
            font-weight: 500;
        }

        .badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
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

        .lihat-semua {
            text-align: right;
            margin-top: 8px;
        }

        .lihat-semua a {
            color: #2a5298;
            text-decoration: none;
            font-size: 10px;
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
                padding: 12px;
            }
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Reservasi Klinik</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <span> Dashboard</span>
            </a>
            <a href="pendaftaran.php" class="nav-item">
                <span> Pendaftaran</span>
            </a>
            <a href="riwayat.php" class="nav-item">
                <span> Riwayat Medis</span>
            </a>
            <a href="antrian_saya.php" class="nav-item">
                <span> Nomor Antrian</span>
            </a>
            <a href="poli.php" class="nav-item">
                <span> Lihat Poli</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($pasien['nama_lengkap'], 0, 1)) ?>
                </div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($pasien['nama_lengkap']) ?></div>
                    <div class="user-role">Pasien</div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="welcome-text">
                <h1>Selamat Datang, <?= htmlspecialchars($pasien['nama_lengkap']) ?></h1>
                <p>Silakan lakukan pendaftaran </p>
            </div>
            <a href="pendaftaran.php" class="btn-pendaftaran"> Pendaftaran</a>
        </div>

        <!-- Kartu Antrian Aktif -->
        <?php if ($antrian_aktif): ?>
        <div class="antrian-card">
            <div class="antrian-header">
                <h3> Nomor Antrian Anda</h3>
                <span class="badge-terdaftar">TERDAFTAR</span>
            </div>
            <div class="nomor-antrian-box">
                <div class="label-antrian">NOMOR ANTRIAN</div>
                <div class="nomor-antrian"><?= $antrian_aktif['nomor_antrian'] ?></div>
                <div class="estimasi"> Estimasi Dilayani <?= date('H:i', strtotime($antrian_aktif['estimasi_waktu_jam'])) ?> WIB</div>
            </div>
            <div class="info-grid">
                <div>
                    <div class="info-label">Poli</div>
                    <div class="info-value"><?= htmlspecialchars($antrian_aktif['nama_poli']) ?></div>
                </div>
                <div>
                    <div class="info-label">Dokter</div>
                    <div class="info-value"><?= htmlspecialchars($antrian_aktif['nama_dokter']) ?></div>
                </div>
                <div>
                    <div class="info-label">Tanggal</div>
                    <div class="info-value"><?= date('d M Y', strtotime($antrian_aktif['tanggal_periksa'])) ?></div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="no-antrian">
            <p> Belum ada antrian aktif</p>
            <p style="font-size: 10px; margin-top: 4px;">Silahkan Lakukan Pendaftaran</p>
        </div>
        <?php endif; ?>

        <!-- Riwayat Pendaftaran -->
        <div class="riwayat-card">
            <h3> Riwayat Pendaftaran Terbaru</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Poli</th>
                            <th>Dokter</th>
                            <th>No. Antrian</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat->num_rows > 0): ?>
                            <?php while($row = $riwayat->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                    <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                    <td><?= $row['nomor_antrian'] ?></td>
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
                                <td colspan="5" style="text-align: center; padding: 16px;"> Belum ada riwayat </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="lihat-semua">
                <a href="riwayat.php">Lihat semua →</a>
            </div>
        </div>
    </div>
</body>
</html>