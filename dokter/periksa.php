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

// Ambil daftar pasien hari ini yang statusnya dipanggil (siap diperiksa)
$pasien_list = $conn->query("
    SELECT r.id_reservasi, r.tanggal_periksa, r.keluhan, r.status_reservasi,
           a.nomor_antrian, a.status_antrian,
           p.id_pasien, p.nama_lengkap, p.jenis_pasien, p.no_bpjs
    FROM reservasi r
    JOIN antrian a ON r.id_reservasi = a.id_reservasi
    JOIN pasien p ON r.id_pasien = p.id_pasien
    WHERE r.id_dokter = '$id_dokter' 
      AND r.tanggal_periksa = CURDATE()
      AND a.status_antrian = 'dipanggil'
    ORDER BY a.nomor_antrian ASC
");

// Ambil data pasien jika ada parameter id
$data_pasien = null;
$lihat_mode = isset($_GET['lihat']);
$id_reservasi = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_reservasi > 0) {
    $data_pasien = $conn->query("
        SELECT r.*, a.nomor_antrian, a.status_antrian,
               p.id_pasien, p.nama_lengkap, p.nik, p.alamat, p.no_hp, p.jenis_kelamin, p.jenis_pasien, p.no_bpjs,
               po.nama_poli
        FROM reservasi r
        JOIN antrian a ON r.id_reservasi = a.id_reservasi
        JOIN pasien p ON r.id_pasien = p.id_pasien
        JOIN poli po ON r.id_poli = po.id_poli
        WHERE r.id_reservasi = '$id_reservasi' AND r.id_dokter = '$id_dokter'
    ")->fetch_assoc();
}

// Proses simpan pemeriksaan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selesai'])) {
    $id_reservasi = intval($_POST['id_reservasi']);
    $diagnosa = $conn->real_escape_string($_POST['diagnosa']);
    $resep_obat = $conn->real_escape_string($_POST['resep_obat']);
    
    $conn->query("UPDATE antrian SET status_antrian = 'selesai' WHERE id_reservasi = '$id_reservasi'");
    $conn->query("UPDATE reservasi SET status_reservasi = 'selesai' WHERE id_reservasi = '$id_reservasi'");
    
    $cek_rm = $conn->query("SELECT * FROM rekam_medis WHERE id_reservasi = '$id_reservasi'");
    if ($cek_rm->num_rows > 0) {
        $conn->query("UPDATE rekam_medis SET diagnosa = '$diagnosa', resep_obat = '$resep_obat' WHERE id_reservasi = '$id_reservasi'");
    } else {
        $id_pasien = intval($_POST['id_pasien']);
        $conn->query("INSERT INTO rekam_medis (id_reservasi, id_dokter, id_pasien, diagnosa, resep_obat) 
                      VALUES ('$id_reservasi', '$id_dokter', '$id_pasien', '$diagnosa', '$resep_obat')");
    }
    
    echo "<script>alert('Pemeriksaan berhasil disimpan!'); window.location.href='periksa.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Periksa Pasien - MEDKLIK</title>
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
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-dipanggil {
            background: #dbeafe;
            color: #2563eb;
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

        .btn-back {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        /* Form Card untuk pemeriksaan */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #eef2f6;
        }

        .form-card h3 {
            font-size: 16px;
            color: #1e3c72;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eef2f6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
        }

        .info-label {
            font-size: 10px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }

        .keluhan-box {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .keluhan-box .label {
            font-size: 10px;
            color: #6c757d;
            margin-bottom: 6px;
        }

        .keluhan-box .text {
            font-size: 13px;
            color: #1e293b;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #2a5298;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
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
            .info-grid {
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
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="periksa.php" class="nav-item active">Periksa Pasien</a>
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
        <?php if ($data_pasien): ?>
            <!-- Form Pemeriksaan -->
            <div class="form-card">
                <h3><?= $lihat_mode ? 'Detail Pemeriksaan Pasien' : 'Pemeriksaan Pasien' ?></h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">No. Antrian</div>
                        <div class="info-value"><?= $data_pasien['nomor_antrian'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tanggal Periksa</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($data_pasien['tanggal_periksa'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Nama Pasien</div>
                        <div class="info-value"><?= htmlspecialchars($data_pasien['nama_lengkap']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Jenis Pasien</div>
                        <div class="info-value"><?= $data_pasien['jenis_pasien'] . ($data_pasien['no_bpjs'] ? ' (' . $data_pasien['no_bpjs'] . ')' : '') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Jenis Kelamin</div>
                        <div class="info-value"><?= $data_pasien['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">No HP</div>
                        <div class="info-value"><?= $data_pasien['no_hp'] ?? '-' ?></div>
                    </div>
                </div>

                <div class="keluhan-box">
                    <div class="label">Keluhan Pasien</div>
                    <div class="text"><?= nl2br(htmlspecialchars($data_pasien['keluhan'])) ?></div>
                </div>

                <?php if (!$lihat_mode && $data_pasien['status_antrian'] == 'dipanggil'): ?>
                    <form method="POST">
                        <input type="hidden" name="id_reservasi" value="<?= $data_pasien['id_reservasi'] ?>">
                        <input type="hidden" name="id_pasien" value="<?= $data_pasien['id_pasien'] ?>">
                        
                        <div class="form-group">
                            <label>Diagnosa</label>
                            <textarea name="diagnosa" rows="4" placeholder="Masukkan diagnosa penyakit..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Resep Obat</label>
                            <textarea name="resep_obat" rows="4" placeholder="Masukkan resep obat..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="periksa.php" class="btn-back">Kembali</a>
                            <button type="submit" name="selesai" class="btn-primary">Simpan & Selesai</button>
                        </div>
                    </form>
                <?php elseif ($lihat_mode): ?>
                    <?php
                    $rm = $conn->query("SELECT * FROM rekam_medis WHERE id_reservasi = '{$data_pasien['id_reservasi']}'")->fetch_assoc();
                    ?>
                    <div class="keluhan-box">
                        <div class="label">Diagnosa</div>
                        <div class="text"><?= nl2br(htmlspecialchars($rm['diagnosa'] ?? '-')) ?></div>
                    </div>
                    <div class="keluhan-box">
                        <div class="label">Resep Obat</div>
                        <div class="text"><?= nl2br(htmlspecialchars($rm['resep_obat'] ?? '-')) ?></div>
                    </div>
                    <div class="form-actions">
                        <a href="periksa.php" class="btn-back">Kembali ke Daftar</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Daftar Pasien yang Dipanggil -->
            <div class="welcome-card">
                <h1>Periksa Pasien</h1>
                <p>Pilih pasien yang sudah dipanggil untuk diperiksa</p>
            </div>

            <div class="table-card">
                <h3>Daftar Pasien Yang Dipanggil</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Antrian</th>
                                <th>Nama Pasien</th>
                                <th>Jenis Pasien</th>
                                <th>No BPJS</th>
                                <th>Keluhan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pasien_list->num_rows > 0): ?>
                                <?php while($row = $pasien_list->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= $row['nomor_antrian'] ?></strong></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= $row['jenis_pasien'] ?></td>
                                    <td><?= $row['no_bpjs'] ?? '-' ?></td>
                                    <td><?= htmlspecialchars(substr($row['keluhan'], 0, 35)) ?>...</td>
                                    <td>
                                        <a href="periksa.php?id=<?= $row['id_reservasi'] ?>" class="btn-periksa">Periksa</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">Belum ada pasien yang dipanggil hari ini</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>