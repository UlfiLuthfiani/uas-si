<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Proses update status antrian (Admin hanya bisa panggil, tidak bisa selesai)
if (isset($_GET['panggil'])) {
    $id_reservasi = intval($_GET['panggil']);
    $conn->query("UPDATE antrian SET status_antrian = 'dipanggil' WHERE id_reservasi = '$id_reservasi'");
    $conn->query("UPDATE reservasi SET status_reservasi = 'dipanggil' WHERE id_reservasi = '$id_reservasi'");
    $success = "Status berhasil diubah menjadi Dipanggil";
    header("Location: antrian.php");
    exit();
}

// HAPUS PROSES SELESAI (Admin tidak boleh selesai)
// if (isset($_GET['selesai'])) { ... }

// Filter berdasarkan poli
$filter_poli = isset($_GET['poli']) ? intval($_GET['poli']) : 0;

// Ambil semua poli untuk dropdown filter
$poli_list = $conn->query("SELECT * FROM poli ORDER BY nama_poli");

// Query ambil antrian
$query = "
    SELECT r.id_reservasi, r.tanggal_periksa, r.keluhan,
           a.nomor_antrian, a.status_antrian,
           p.id_pasien, p.nama_lengkap, p.nik, p.no_hp, p.jenis_pasien, p.no_bpjs,
           po.id_poli, po.nama_poli,
           d.id_dokter, d.nama_dokter
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN pasien p ON r.id_pasien = p.id_pasien
    JOIN poli po ON r.id_poli = po.id_poli
    JOIN dokter d ON r.id_dokter = d.id_dokter
";

if ($filter_poli > 0) {
    $query .= " WHERE po.id_poli = '$filter_poli'";
}

$query .= " ORDER BY r.tanggal_periksa DESC, po.nama_poli, a.nomor_antrian ASC";

$antrian_list = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Antrian - Admin</title>
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

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            min-width: 150px;
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

        .btn-panggil {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            min-width: 60px;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-panggil:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(30,60,114,0.3);
        }

        .btn-lihat {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            min-width: 60px;
            text-align: center;
            cursor: default;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
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
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Admin Panel</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="antrian.php" class="nav-item active">Kelola Antrian</a>
            <a href="kelola_poli.php" class="nav-item">Kelola Poli</a>
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

    <div class="main-content">
        <div class="welcome-card">
            <h1>Kelola Antrian Pasien</h1>
            <p>Pantau dan perbarui status antrian pasien</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="filter-box">
            <form method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                <div class="filter-group">
                    <label>Filter Poli</label>
                    <select name="poli">
                        <option value="0">Semua Poli</option>
                        <?php 
                        $poli_list2 = $conn->query("SELECT * FROM poli ORDER BY nama_poli");
                        while($poli = $poli_list2->fetch_assoc()): ?>
                        <option value="<?= $poli['id_poli'] ?>" <?= $filter_poli == $poli['id_poli'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($poli['nama_poli']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Filter</button>
                <?php if ($filter_poli > 0): ?>
                    <a href="antrian.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <h3>Daftar Antrian Pasien</h3>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Antrian</th>
                            <th>Poli</th>
                            <th>Nama Pasien</th>
                            <th>Jenis Pasien</th>
                            <th>No BPJS</th>
                            <th>Dokter</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($antrian_list->num_rows > 0): ?>
                            <?php while($row = $antrian_list->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                                <td><strong><?= $row['nomor_antrian'] ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td><?= $row['jenis_pasien'] ?></td>
                                <td><?= $row['no_bpjs'] ?? '-' ?></td>
                                <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                <td><?= htmlspecialchars(substr($row['keluhan'], 0, 30)) ?>...</td>
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
                                <td>
                                    <?php if ($row['status_antrian'] == 'menunggu'): ?>
                                        <a href="?panggil=<?= $row['id_reservasi'] ?>" class="btn-panggil" onclick="return confirm('Panggil pasien <?= $row['nama_lengkap'] ?>?')">Panggil</a>
                                    <?php elseif ($row['status_antrian'] == 'dipanggil'): ?>
                                        <span class="btn-lihat" style="background:#dbeafe; color:#2563eb; cursor:default;">Dipanggil</span>
                                    <?php elseif ($row['status_antrian'] == 'selesai'): ?>
                                        <span class="btn-lihat" style="background:#d1fae5; color:#059669; cursor:default;">✓ Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="empty-state">Belum ada data antrian</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>