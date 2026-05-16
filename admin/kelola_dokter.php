<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Proses Tambah Dokter
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $nama_dokter = $conn->real_escape_string($_POST['nama_dokter']);
        $spesialisasi = $conn->real_escape_string($_POST['spesialisasi']);
        $id_poli = intval($_POST['id_poli']);
        
        $check = $conn->query("SELECT * FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            $error = "Username sudah digunakan!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $conn->query("INSERT INTO users (username, password, role) VALUES ('$username', '$hashed_password', 'dokter')");
            $id_user = $conn->insert_id;
            
            $conn->query("INSERT INTO dokter (id_user, nama_dokter, spesialisasi, id_poli) 
                          VALUES ('$id_user', '$nama_dokter', '$spesialisasi', '$id_poli')");
            $id_dokter = $conn->insert_id;
            
            if (isset($_POST['hari']) && !empty($_POST['hari'])) {
                foreach ($_POST['hari'] as $hari) {
                    $conn->query("INSERT INTO jadwal_dokter (id_dokter, hari) VALUES ('$id_dokter', '$hari')");
                }
            }
            $success = "Dokter berhasil ditambahkan!";
            header("Location: kelola_dokter.php?success=1");
            exit();
        }
    }
    
    // Proses Edit Dokter via Modal
    if ($_POST['action'] == 'edit') {
        $id_dokter = intval($_POST['id_dokter']);
        $nama_dokter = $conn->real_escape_string($_POST['nama_dokter']);
        $spesialisasi = $conn->real_escape_string($_POST['spesialisasi']);
        $id_poli = intval($_POST['id_poli']);
        $new_password = isset($_POST['password']) ? $_POST['password'] : '';
        
        $conn->query("UPDATE dokter SET nama_dokter = '$nama_dokter', spesialisasi = '$spesialisasi', id_poli = '$id_poli' 
                      WHERE id_dokter = '$id_dokter'");
        
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $dokter = $conn->query("SELECT id_user FROM dokter WHERE id_dokter = '$id_dokter'")->fetch_assoc();
            $id_user = $dokter['id_user'];
            $conn->query("UPDATE users SET password = '$hashed_password' WHERE id_user = '$id_user'");
        }
        
        // Update jadwal
        $conn->query("DELETE FROM jadwal_dokter WHERE id_dokter = '$id_dokter'");
        if (isset($_POST['hari']) && !empty($_POST['hari'])) {
            foreach ($_POST['hari'] as $hari) {
                $conn->query("INSERT INTO jadwal_dokter (id_dokter, hari) VALUES ('$id_dokter', '$hari')");
            }
        }
        $success = "Dokter berhasil diupdate!";
        header("Location: kelola_dokter.php?success=1");
        exit();
    }
    
    // Proses Hapus Dokter
    if ($_POST['action'] == 'hapus') {
        $id_dokter = intval($_POST['id_dokter']);
        $dokter = $conn->query("SELECT id_user FROM dokter WHERE id_dokter = '$id_dokter'")->fetch_assoc();
        $id_user = $dokter['id_user'];
        
        $conn->query("DELETE FROM jadwal_dokter WHERE id_dokter = '$id_dokter'");
        $conn->query("DELETE FROM dokter WHERE id_dokter = '$id_dokter'");
        $conn->query("DELETE FROM users WHERE id_user = '$id_user'");
        
        $success = "Dokter berhasil dihapus!";
        header("Location: kelola_dokter.php?success=1");
        exit();
    }
}

// Ambil data dokter
$dokter_list = $conn->query("
    SELECT d.*, u.username, p.nama_poli,
           GROUP_CONCAT(j.hari ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') SEPARATOR ', ') as hari_praktek
    FROM dokter d
    JOIN users u ON d.id_user = u.id_user
    JOIN poli p ON d.id_poli = p.id_poli
    LEFT JOIN jadwal_dokter j ON d.id_dokter = j.id_dokter
    GROUP BY d.id_dokter
    ORDER BY d.id_dokter
");

$poli_list = $conn->query("SELECT * FROM poli ORDER BY nama_poli");

// Ambil data untuk modal edit
$edit_dokter = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $edit_dokter = $conn->query("
        SELECT d.*, u.username, p.nama_poli,
               GROUP_CONCAT(j.hari ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') SEPARATOR ', ') as hari_praktek
        FROM dokter d
        JOIN users u ON d.id_user = u.id_user
        JOIN poli p ON d.id_poli = p.id_poli
        LEFT JOIN jadwal_dokter j ON d.id_dokter = j.id_dokter
        WHERE d.id_dokter = '$id_edit'
        GROUP BY d.id_dokter
    ")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Dokter - MEDKLIK</title>
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 5px;
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

        .form-row {
            display: flex;
            gap: 16px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            font-size: 13px;
        }

        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
        }

        /* Tombol Edit seperti di kelola pasien */
        .btn-edit {
            background: #e0e7ff;
            color: #2a5298;
            display: inline-block;
            padding: 4px 10px;
            margin: 2px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-weight: 500;
        }

        .btn-hapus {
            background: #fee2e2;
            color: #dc2626;
            display: inline-block;
            padding: 4px 10px;
            margin: 2px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            border: none;
            font-weight: 500;
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

        .badge-hari {
            background: #e0e7ff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin: 2px;
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

        .action-buttons {
            white-space: nowrap;
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
            max-width: 500px;
            width: 90%;
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

        .btn-simpan {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-batal {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }

        .main-content::-webkit-scrollbar {
            width: 4px;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>MEDKLIK</h2><p>Admin Panel</p></div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="antrian.php" class="nav-item">Kelola Antrian</a>
            <a href="kelola_poli.php" class="nav-item">Kelola Poli</a>
            <a href="kelola_dokter.php" class="nav-item active">Kelola Dokter</a>
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
            <h1>Kelola Data Dokter</h1>
            <p>Tambah, edit, atau hapus data dokter</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Operasi berhasil dilakukan!</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form Tambah Dokter -->
        <div class="form-card">
            <h2>Tambah Dokter Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="form-row">
                    <div class="form-group"><label>Username</label><input type="text" name="username" placeholder="dr_bambang" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="password" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Nama Dokter</label><input type="text" name="nama_dokter" placeholder="dr. Bambang, Sp.PD" required></div>
                    <div class="form-group"><label>Spesialisasi</label><input type="text" name="spesialisasi" placeholder="Penyakit Dalam"></div>
                    <div class="form-group"><label>Pilih Poli</label>
                        <select name="id_poli" required>
                            <option value="">Pilih Poli</option>
                            <?php $poli_list2 = $conn->query("SELECT * FROM poli ORDER BY nama_poli"); while($poli = $poli_list2->fetch_assoc()): ?>
                            <option value="<?= $poli['id_poli'] ?>"><?= htmlspecialchars($poli['nama_poli']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Jadwal Praktek</label>
                    <div class="checkbox-group">
                        <?php $hari_arr = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; ?>
                        <?php foreach($hari_arr as $h): ?>
                        <label><input type="checkbox" name="hari[]" value="<?= $h ?>"> <?= $h ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Tambah Dokter</button>
            </form>
        </div>

        <!-- Daftar Dokter -->
        <div class="table-card">
            <h2>Daftar Dokter</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Dokter</th>
                            <th>Spesialisasi</th>
                            <th>Poli</th>
                            <th>Jadwal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $dokter_list->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id_dokter'] ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                            <td><?= htmlspecialchars($row['spesialisasi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                            <td>
                                <?php $jadwal = explode(', ', $row['hari_praktek'] ?? ''); foreach($jadwal as $h): if($h): ?>
                                <span class="badge-hari"><?= $h ?></span>
                                <?php endif; endforeach; ?>
                            </td>
                            <td class="action-buttons">
                                <a href="?edit=<?= $row['id_dokter'] ?>" class="btn-edit">Edit</a>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin ingin menghapus dokter <?= htmlspecialchars($row['nama_dokter']) ?>?')">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id_dokter" value="<?= $row['id_dokter'] ?>">
                                    <button type="submit" class="btn-hapus">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Edit Dokter -->
    <?php if ($edit_dokter): ?>
    <div class="modal" style="display: flex;">
        <div class="modal-content">
            <h3>Edit Data Dokter</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_dokter" value="<?= $edit_dokter['id_dokter'] ?>">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?= htmlspecialchars($edit_dokter['username']) ?>" disabled style="background:#f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Nama Dokter</label>
                    <input type="text" name="nama_dokter" value="<?= htmlspecialchars($edit_dokter['nama_dokter']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Spesialisasi</label>
                    <input type="text" name="spesialisasi" value="<?= htmlspecialchars($edit_dokter['spesialisasi'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Poli</label>
                    <select name="id_poli" required>
                        <?php $poli_list3 = $conn->query("SELECT * FROM poli ORDER BY nama_poli"); while($p = $poli_list3->fetch_assoc()): ?>
                        <option value="<?= $p['id_poli'] ?>" <?= $p['id_poli'] == $edit_dokter['id_poli'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_poli']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password Baru (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" placeholder="Masukkan password baru">
                </div>
                <div class="form-group">
                    <label>Jadwal Praktek</label>
                    <div class="checkbox-group">
                        <?php 
                        $hari_arr = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                        $jadwal_edit = explode(', ', $edit_dokter['hari_praktek'] ?? '');
                        foreach($hari_arr as $h): 
                        ?>
                        <label>
                            <input type="checkbox" name="hari[]" value="<?= $h ?>" <?= in_array($h, $jadwal_edit) ? 'checked' : '' ?>>
                            <?= $h ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-simpan">Simpan</button>
                    <a href="kelola_dokter.php" class="btn-batal">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>