<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header('Location: login.php');
    exit();
}

$id_pasien = $_SESSION['id_pasien'];

// Ambil data pasien
$pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_pasien'")->fetch_assoc();

// Ambil antrian aktif yang PALING BARU
$antrian_aktif = $conn->query("
    SELECT r.*, a.nomor_antrian, a.status_antrian, a.estimasi_waktu_jam, a.posisi_antrian,
           p.nama_poli, d.nama_dokter
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN poli p ON r.id_poli = p.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
    WHERE r.id_pasien = '$id_pasien' 
      AND a.status_antrian IN ('menunggu', 'dipanggil')
    ORDER BY r.tanggal_periksa DESC, r.created_at DESC
    LIMIT 1
")->fetch_assoc();

// Ambil SEMUA riwayat antrian
$riwayat_antrian = $conn->query("
    SELECT r.*, a.nomor_antrian, a.status_antrian, a.estimasi_waktu_jam,
           p.nama_poli, d.nama_dokter
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN poli p ON r.id_poli = p.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
    WHERE r.id_pasien = '$id_pasien'
    ORDER BY r.tanggal_periksa DESC, r.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomor Antrian - MEDKLIK</title>
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

        .antrian-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
            text-align: center;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
        }

        .antrian-header h3 {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            letter-spacing: 1px;
        }

        .antrian-header h2 {
            font-size: 15px;
            color: #1e3c72;
            margin-top: 4px;
        }

        .badge-terdaftar {
            display: inline-block;
            background: #d1fae5;
            color: #059669;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .nomor-antrian-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .label-antrian {
            font-size: 10px;
            color: #6c757d;
            letter-spacing: 1px;
        }

        .nomor-antrian {
            font-size: 44px;
            font-weight: 800;
            color: #1e3c72;
            margin: 6px 0;
        }

        .estimasi {
            font-size: 12px;
            color: #2a5298;
            font-weight: 500;
        }

        .info-detail {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #eef2f6;
        }

        .info-label {
            font-size: 9px;
            color: #6c757d;
        }

        .info-value {
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
        }

        .no-antrian-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            max-width: 450px;
            margin: 0 auto;
            border: 1px solid #eef2f6;
        }

        .btn-daftar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }

        .riwayat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
        }

        .riwayat-card h3 {
            font-size: 14px;
            color: #1e3c72;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 11px;
        }

        th {
            color: #6c757d;
            font-weight: 500;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 12px;
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
            margin-top: 10px;
        }

        .lihat-semua a {
            color: #2a5298;
            font-size: 11px;
            text-decoration: none;
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
            <a href="riwayat.php" class="nav-item">Riwayat Medis</a>
            <a href="antrian_saya.php" class="nav-item active">Nomor Antrian</a>
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
        <?php if ($antrian_aktif): ?>
            <div class="antrian-card">
                <div class="antrian-header">
                    <h3>MEDKLIK RESERVASI</h3>
                </div>
                <div class="badge-terdaftar">TERDAFTAR</div>
                <div class="nomor-antrian-box">
                    <div class="label-antrian">NOMOR ANTRIAN</div>
                    <div class="nomor-antrian"><?= $antrian_aktif['nomor_antrian'] ?></div>
                    <div class="estimasi">Estimasi <?= date('H:i', strtotime($antrian_aktif['estimasi_waktu_jam'])) ?> WIB</div>
                </div>
                <div class="info-detail">
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
            <div class="no-antrian-card">
                <p>Belum ada antrian aktif</p>
                <a href="pendaftaran.php" class="btn-daftar">+ Pendaftaran</a>
            </div>
        <?php endif; ?>

        <div class="riwayat-card">
            <h3>Riwayat Pendaftaran</h3>
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
                        <?php if ($riwayat_antrian->num_rows > 0): ?>
                            <?php while($row = $riwayat_antrian->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                <td><strong><?= $row['nomor_antrian'] ?></strong></td>
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
                                <td colspan="5" style="text-align: center; padding: 24px;">Belum ada riwayat pendaftaran</td>
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