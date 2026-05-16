<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header('Location: login.php');
    exit();
}

$id_pasien = $_SESSION['id_pasien'];
$tanggal_hari_ini = date('Y-m-d');

// Ambil data pasien
$pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_pasien'")->fetch_assoc();

// Ambil daftar poli dengan sisa kuota hari ini
$poli_list = $conn->query("
    SELECT p.*, 
           COALESCE(r.jumlah, 0) as terisi,
           (p.kuota_harian - COALESCE(r.jumlah, 0)) as sisa_kuota
    FROM poli p
    LEFT JOIN (
        SELECT id_poli, COUNT(*) as jumlah 
        FROM reservasi 
        WHERE tanggal_periksa = '$tanggal_hari_ini'
        GROUP BY id_poli
    ) r ON p.id_poli = r.id_poli
    ORDER BY p.nama_poli
");

// Ambil detail poli jika ada parameter id
$detail_poli = null;
$dokter_list = null;
if (isset($_GET['id'])) {
    $id_poli_detail = intval($_GET['id']);
    $detail_poli = $conn->query("SELECT * FROM poli WHERE id_poli = '$id_poli_detail'")->fetch_assoc();
    $dokter_list = $conn->query("
        SELECT d.*, 
               GROUP_CONCAT(DISTINCT j.hari ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') SEPARATOR ', ') as hari_praktek
        FROM dokter d
        LEFT JOIN jadwal_dokter j ON d.id_dokter = j.id_dokter
        WHERE d.id_poli = '$id_poli_detail'
        GROUP BY d.id_dokter
    ");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Poli - MEDKLIK</title>
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
            opacity: 0.85;
            font-size: 12px;
        }

        .poli-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .poli-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #eef2f6;
            text-decoration: none;
            display: block;
        }

        .poli-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #2a5298;
        }

        .poli-card.active {
            border: 2px solid #2a5298;
            background: #f0f4ff;
        }

        .poli-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .poli-name {
            font-size: 16px;
            font-weight: 600;
            color: #1e3c72;
            margin-bottom: 4px;
        }

        .poli-kuota {
            font-size: 11px;
            color: #6c757d;
            margin-top: 8px;
        }

        .kuota-tersedia {
            color: #059669;
            font-weight: 600;
        }

        .kuota-penuh {
            color: #dc2626;
            font-weight: 600;
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #eef2f6;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef2f6;
        }

        .detail-header h2 {
            font-size: 18px;
            color: #1e3c72;
        }

        .back-btn {
            background: #f1f3f5;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            font-size: 12px;
        }

        .dokter-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .dokter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .dokter-name {
            font-size: 15px;
            font-weight: 600;
            color: #1e3c72;
        }

        .dokter-spesialis {
            font-size: 11px;
            color: #6c757d;
        }

        .jadwal-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0;
        }

        .jadwal-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            padding: 3px 12px;
            font-size: 11px;
            color: #2a5298;
        }

        .jam-layanan {
            font-size: 10px;
            color: #059669;
            margin-top: 8px;
        }

        .btn-pilih {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .empty-dokter {
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
                padding: 16px;
            }
            .poli-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="antrian_saya.php" class="nav-item">Nomor Antrian</a>
            <a href="poli.php" class="nav-item active">Lihat Poli</a>
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
            <h1>Daftar Poli Klinik</h1>

        </div>

        <!-- Grid Poli dengan Sisa Kuota -->
        <div class="poli-grid">
            <?php while($poli = $poli_list->fetch_assoc()): ?>
            <a href="?id=<?= $poli['id_poli'] ?>" class="poli-card <?= (isset($_GET['id']) && $_GET['id'] == $poli['id_poli']) ? 'active' : '' ?>">
                <div class="poli-name"><?= htmlspecialchars($poli['nama_poli']) ?></div>
                <div class="poli-kuota">
                    Kuota: <?= $poli['kuota_harian'] ?> pasien/hari<br>
                    <?php if ($poli['sisa_kuota'] > 0): ?>
                        <span class="kuota-tersedia">Tersisa <?= $poli['sisa_kuota'] ?> kursi</span>
                    <?php else: ?>
                        <span class="kuota-penuh">Penuh hari ini</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endwhile; ?>
        </div>

        <!-- Detail Poli -->
        <?php if (isset($_GET['id']) && $detail_poli): ?>
        <div class="detail-card">
            <div class="detail-header">
                <h2><?= htmlspecialchars($detail_poli['nama_poli']) ?></h2>
                <a href="poli.php" class="back-btn">Kembali</a>
            </div>

            <?php if ($dokter_list && $dokter_list->num_rows > 0): ?>
                <?php while($dokter = $dokter_list->fetch_assoc()): ?>
                <div class="dokter-card">
                    <div class="dokter-header">
                        <div>
                            <div class="dokter-name"><?= htmlspecialchars($dokter['nama_dokter']) ?></div>
                            <div class="dokter-spesialis"><?= htmlspecialchars($dokter['spesialisasi'] ?? 'Dokter Umum') ?></div>
                        </div>
                        <a href="pendaftaran.php?poli=<?= $detail_poli['id_poli'] ?>&dokter=<?= $dokter['id_dokter'] ?>" class="btn-pilih">
                            Pilih
                        </a>
                    </div>
                    
                    <div class="jadwal-list">
                        <?php 
                        $hari_list = explode(', ', $dokter['hari_praktek'] ?? '');
                        if (!empty($hari_list) && $hari_list[0] != ''):
                            foreach($hari_list as $hari):
                        ?>
                            <span class="jadwal-item"><?= trim($hari) ?></span>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <span class="jadwal-item">Jadwal belum ditentukan</span>
                        <?php endif; ?>
                    </div>
                    <div class="jam-layanan">Pelayanan: Senin - Sabtu (08:00 - 12:00) | Minggu & Hari Libur Tutup</div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-dokter">
                    <p>Belum ada dokter yang bertugas di poli ini.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>