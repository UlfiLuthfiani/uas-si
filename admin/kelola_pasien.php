<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses Tambah Pasien Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $email = $conn->real_escape_string($_POST['email']);
        $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
        $nik = $conn->real_escape_string($_POST['nik']);
        $tanggal_lahir = $conn->real_escape_string($_POST['tanggal_lahir']);
        $alamat = $conn->real_escape_string($_POST['alamat']);
        $no_hp = $conn->real_escape_string($_POST['no_hp']);
        $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);
        $jenis_pasien = $conn->real_escape_string($_POST['jenis_pasien']);
        $no_bpjs = isset($_POST['no_bpjs']) ? $conn->real_escape_string($_POST['no_bpjs']) : null;
        
        $check = $conn->query("SELECT * FROM users WHERE username = '$username'");
        if ($check && $check->num_rows > 0) {
            $error = "Username sudah digunakan!";
        } else {
            $conn->query("INSERT INTO users (username, password, role) VALUES ('$username', '$password', 'pasien')");
            $id_user = $conn->insert_id;
            
            $sql = "INSERT INTO pasien (id_user, nama_lengkap, nik, email, tanggal_lahir, alamat, no_hp, jenis_kelamin, jenis_pasien, no_bpjs) 
                    VALUES ('$id_user', '$nama_lengkap', '$nik', '$email', '$tanggal_lahir', '$alamat', '$no_hp', '$jenis_kelamin', '$jenis_pasien', " . ($no_bpjs ? "'$no_bpjs'" : "NULL") . ")";
            
            if ($conn->query($sql)) {
                $success = "Pasien berhasil ditambahkan!";
                header("Location: kelola_pasien.php?success=1");
                exit();
            } else {
                $error = "Gagal menambahkan pasien: " . $conn->error;
            }
        }
    }
    
    // Proses Edit Pasien
    if ($_POST['action'] == 'edit') {
        $id_pasien = intval($_POST['id_pasien']);
        $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
        $nik = $conn->real_escape_string($_POST['nik']);
        $tanggal_lahir = $conn->real_escape_string($_POST['tanggal_lahir']);
        $alamat = $conn->real_escape_string($_POST['alamat']);
        $no_hp = $conn->real_escape_string($_POST['no_hp']);
        $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);
        $jenis_pasien = $conn->real_escape_string($_POST['jenis_pasien']);
        $no_bpjs = isset($_POST['no_bpjs']) ? $conn->real_escape_string($_POST['no_bpjs']) : null;
        
        $sql = "UPDATE pasien SET 
                nama_lengkap = '$nama_lengkap',
                nik = '$nik',
                tanggal_lahir = '$tanggal_lahir',
                alamat = '$alamat',
                no_hp = '$no_hp',
                jenis_kelamin = '$jenis_kelamin',
                jenis_pasien = '$jenis_pasien',
                no_bpjs = " . ($no_bpjs ? "'$no_bpjs'" : "NULL") . "
                WHERE id_pasien = '$id_pasien'";
        
        if ($conn->query($sql)) {
            $success = "Data pasien berhasil diupdate!";
            header("Location: kelola_pasien.php?success=1");
            exit();
        } else {
            $error = "Gagal update: " . $conn->error;
        }
    }
}

// Proses Hapus Pasien
if (isset($_GET['hapus'])) {
    $id_pasien = intval($_GET['hapus']);
    $pasien_data = $conn->query("SELECT id_user FROM pasien WHERE id_pasien = '$id_pasien'");
    if ($pasien_data && $pasien_data->num_rows > 0) {
        $pasien = $pasien_data->fetch_assoc();
        $id_user = $pasien['id_user'];
        
        $conn->query("DELETE FROM rekam_medis WHERE id_pasien = '$id_pasien'");
        $conn->query("DELETE FROM antrian WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE id_pasien = '$id_pasien')");
        $conn->query("DELETE FROM reservasi WHERE id_pasien = '$id_pasien'");
        $conn->query("DELETE FROM pasien WHERE id_pasien = '$id_pasien'");
        $conn->query("DELETE FROM users WHERE id_user = '$id_user'");
    }
    header("Location: kelola_pasien.php?success=1");
    exit();
}

// Ambil daftar pasien
$query = "SELECT p.*, u.username 
          FROM pasien p 
          JOIN users u ON p.id_user = u.id_user 
          WHERE u.role = 'pasien'";

if ($search) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (p.nama_lengkap LIKE '%$search%' OR p.nik LIKE '%$search%' OR p.no_hp LIKE '%$search%')";
}

$query .= " ORDER BY p.id_pasien DESC";
$pasien_list = $conn->query($query);

// Ambil data pasien untuk edit
$edit_pasien = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_edit'");
    if ($result && $result->num_rows > 0) {
        $edit_pasien = $result->fetch_assoc();
    }
}

// Ambil riwayat untuk modal
$riwayat_pasien = null;
$riwayat_data = null;
if (isset($_GET['riwayat'])) {
    $id_riwayat = intval($_GET['riwayat']);
    $riwayat_pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_riwayat'")->fetch_assoc();
    $riwayat_data = $conn->query("
        SELECT r.*, a.nomor_antrian, a.status_antrian,
               po.nama_poli, d.nama_dokter,
               rm.diagnosa, rm.resep_obat
        FROM reservasi r
        JOIN antrian a ON r.id_reservasi = a.id_reservasi
        JOIN poli po ON r.id_poli = po.id_poli
        JOIN dokter d ON r.id_dokter = d.id_dokter
        LEFT JOIN rekam_medis rm ON r.id_reservasi = rm.id_reservasi
        WHERE r.id_pasien = '$id_riwayat'
        ORDER BY r.created_at DESC
    ");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pasien - MEDKLIK</title>
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

        /* Filter Box */
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
            width: 250px;
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

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
        }

        .form-card h2 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 16px;
        }

        .form-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .form-group {
            margin-bottom: 12px;
            flex: 1;
            min-width: 150px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 10px;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #eef2f6;
        }

        .table-card h2 {
            font-size: 18px;
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

        /* Tombol Aksi dalam satu baris */
        .action-buttons {
            white-space: nowrap;
        }

        .btn-edit, .btn-hapus, .btn-riwayat {
            display: inline-block;
            padding: 4px 10px;
            margin: 2px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .btn-edit {
            background: #e0e7ff;
            color: #2a5298;
        }

        .btn-hapus {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-riwayat {
            background: #6c757d;
            color: white;
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

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 16px;
            border-bottom: 1px solid #eef2f6;
            padding-bottom: 12px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .btn-tutup {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-simpan-modal {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-batal-modal {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }

        .no-bpjs-field {
            transition: all 0.3s ease;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
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

        .riwayat-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .riwayat-table th,
        .riwayat-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            font-size: 12px;
        }

        .riwayat-table th {
            background: #f8fafc;
            font-weight: 500;
            color: #6c757d;
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
            .form-row {
                flex-direction: column;
            }
        }
    </style>
    <script>
        function toggleNoBPJS(formType) {
            var selectId = formType === 'tambah' ? 'jenis_pasien_tambah' : 'jenis_pasien_edit';
            var groupId = formType === 'tambah' ? 'no_bpjs_group_tambah' : 'no_bpjs_group_edit';
            var jenisPasien = document.getElementById(selectId);
            var noBpjsGroup = document.getElementById(groupId);
            
            if (jenisPasien && noBpjsGroup) {
                if (jenisPasien.value === 'BPJS') {
                    noBpjsGroup.style.display = 'block';
                } else {
                    noBpjsGroup.style.display = 'none';
                }
            }
        }
    </script>
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
            <a href="kelola_pasien.php" class="nav-item active">Kelola Pasien</a>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="welcome-card">
            <h1>Kelola Data Pasien</h1>
            <p>Tambah, lihat, edit, atau hapus data pasien</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Operasi berhasil dilakukan!</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form Tambah Pasien -->
        <div class="form-card">
            <h2>Tambah Pasien Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="form-row">
                    <div class="form-group"><label>Username</label><input type="text" name="username" placeholder="Username" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Password" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="Email" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" placeholder="Nama lengkap" required></div>
                    <div class="form-group"><label>NIK</label><input type="text" name="nik" placeholder="16 digit NIK" maxlength="16"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir"></div>
                    <div class="form-group"><label>Jenis Kelamin</label>
                        <select name="jenis_kelamin">
                            <option value="">Pilih</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Alamat</label><input type="text" name="alamat" placeholder="Alamat lengkap"></div>
                    <div class="form-group"><label>No HP</label><input type="tel" name="no_hp" placeholder="Nomor telepon"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Jenis Pasien</label>
                        <select name="jenis_pasien" id="jenis_pasien_tambah" onchange="toggleNoBPJS('tambah')" required>
                            <option value="Umum">Umum</option>
                            <option value="BPJS">BPJS</option>
                        </select>
                    </div>
                    <div class="form-group no-bpjs-field" id="no_bpjs_group_tambah" style="display: none;">
                        <label>No BPJS</label><input type="text" name="no_bpjs" placeholder="Nomor BPJS" maxlength="20">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Tambah Pasien</button>
            </form>
        </div>

        <!-- Filter Pencarian -->
        <div class="filter-box">
            <form method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                <div class="filter-group">
                    <label>Cari Pasien (Nama / NIK / No HP)</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama, NIK, atau no HP...">
                </div>
                <button type="submit" class="btn-filter">Cari</button>
                <?php if ($search): ?>
                    <a href="kelola_pasien.php" class="btn-reset">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Daftar Pasien -->
        <div class="table-card">
            <h2>Daftar Pasien</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>NIK</th>
                            <th>No HP</th>
                            <th>JK</th>
                            <th>Jenis</th>
                            <th>No BPJS</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pasien_list && $pasien_list->num_rows > 0): ?>
                            <?php while($row = $pasien_list->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id_pasien'] ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($row['nik'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['no_hp'] ?? '-') ?></td>
                                <td><?= $row['jenis_kelamin'] == 'L' ? 'L' : ($row['jenis_kelamin'] == 'P' ? 'P' : '-') ?></td>
                                <td><?= $row['jenis_pasien'] ?></td>
                                <td><?= $row['no_bpjs'] ?? '-' ?></td>
                                <td class="action-buttons">
                                    <a href="?edit=<?= $row['id_pasien'] ?>" class="btn-edit">Edit</a>
                                    <a href="?hapus=<?= $row['id_pasien'] ?>" class="btn-hapus" onclick="return confirm('Yakin ingin menghapus pasien <?= htmlspecialchars($row['nama_lengkap']) ?>? Semua riwayat akan hilang.')">Hapus</a>
                                    <a href="?riwayat=<?= $row['id_pasien'] ?>" class="btn-riwayat">Riwayat</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">Belum ada data pasien</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pasien -->
    <?php if ($edit_pasien): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <h3>Edit Data Pasien</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_pasien" value="<?= $edit_pasien['id_pasien'] ?>">
                
                <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" value="<?= htmlspecialchars($edit_pasien['nama_lengkap']) ?>" required></div>
                <div class="form-group"><label>NIK</label><input type="text" name="nik" value="<?= htmlspecialchars($edit_pasien['nik'] ?? '') ?>" maxlength="16"></div>
                <div class="form-group"><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" value="<?= $edit_pasien['tanggal_lahir'] ?? '' ?>"></div>
                <div class="form-group"><label>Alamat</label><input type="text" name="alamat" value="<?= htmlspecialchars($edit_pasien['alamat'] ?? '') ?>"></div>
                <div class="form-group"><label>No HP</label><input type="tel" name="no_hp" value="<?= htmlspecialchars($edit_pasien['no_hp'] ?? '') ?>"></div>
                <div class="form-group"><label>Jenis Kelamin</label>
                    <select name="jenis_kelamin">
                        <option value="">Pilih</option>
                        <option value="L" <?= ($edit_pasien['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= ($edit_pasien['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group"><label>Jenis Pasien</label>
                    <select name="jenis_pasien" id="jenis_pasien_edit" onchange="toggleNoBPJS('edit')" required>
                        <option value="Umum" <?= ($edit_pasien['jenis_pasien'] ?? '') == 'Umum' ? 'selected' : '' ?>>Umum</option>
                        <option value="BPJS" <?= ($edit_pasien['jenis_pasien'] ?? '') == 'BPJS' ? 'selected' : '' ?>>BPJS</option>
                    </select>
                </div>
                <div class="form-group no-bpjs-field" id="no_bpjs_group_edit" style="<?= ($edit_pasien['jenis_pasien'] ?? '') == 'BPJS' ? 'display:block' : 'none' ?>">
                    <label>No BPJS</label><input type="text" name="no_bpjs" value="<?= htmlspecialchars($edit_pasien['no_bpjs'] ?? '') ?>" maxlength="20">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-simpan-modal">Simpan</button>
                    <a href="kelola_pasien.php" class="btn-batal-modal">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <script>toggleNoBPJS('edit');</script>
    <?php endif; ?>

    <!-- Modal Riwayat Pasien -->
    <?php if ($riwayat_pasien && $riwayat_data): ?>
    <div id="riwayatModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <h3>Riwayat Pendaftaran - <?= htmlspecialchars($riwayat_pasien['nama_lengkap']) ?></h3>
            <?php if ($riwayat_data->num_rows > 0): ?>
                <table class="riwayat-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Poli</th>
                            <th>Dokter</th>
                            <th>No. Antrian</th>
                            <th>Status</th>
                            <th>Diagnosa</th>
                            <th>Resep Obat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $riwayat_data->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_periksa'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                            <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                            <td><?= $row['nomor_antrian'] ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status_antrian'] ?>">
                                    <?= ucfirst($row['status_antrian']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['diagnosa'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['resep_obat'] ?? '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 30px;">Belum ada riwayat pendaftaran</p>
            <?php endif; ?>
            <div class="modal-actions">
                <a href="kelola_pasien.php" class="btn-tutup">Tutup</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>