<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'pasien') {
    header('Location: login.php');
    exit();
}

$id_pasien = $_SESSION['id_pasien'];
$error = '';
$success = '';

// Ambil data pasien
$pasien = $conn->query("SELECT * FROM pasien WHERE id_pasien = '$id_pasien'")->fetch_assoc();

// Ambil daftar poli
$poli_list = $conn->query("SELECT * FROM poli");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_poli = $conn->real_escape_string($_POST['id_poli']);
    $id_dokter = $conn->real_escape_string($_POST['id_dokter']);
    $keluhan = $conn->real_escape_string($_POST['keluhan']);
    $tanggal_periksa = $conn->real_escape_string($_POST['tanggal_periksa']);
    
    // Update data pasien
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $nik = $conn->real_escape_string($_POST['nik']);
    $tanggal_lahir = $conn->real_escape_string($_POST['tanggal_lahir']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $no_hp = $conn->real_escape_string($_POST['no_hp']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin']);
    $jenis_pasien = $conn->real_escape_string($_POST['jenis_pasien']);
    $no_bpjs = isset($_POST['no_bpjs']) ? $conn->real_escape_string($_POST['no_bpjs']) : null;
    
    $conn->query("UPDATE pasien SET 
                  nama_lengkap = '$nama_lengkap',
                  nik = '$nik',
                  tanggal_lahir = '$tanggal_lahir',
                  alamat = '$alamat',
                  no_hp = '$no_hp',
                  jenis_kelamin = '$jenis_kelamin',
                  jenis_pasien = '$jenis_pasien',
                  no_bpjs = " . ($no_bpjs ? "'$no_bpjs'" : "NULL") . "
                  WHERE id_pasien = '$id_pasien'");
    
    // CEK HARI MINGGU
    $hari = date('l', strtotime($tanggal_periksa));
    $hari_indonesia = '';
    
    switch($hari) {
        case 'Monday': $hari_indonesia = 'Senin'; break;
        case 'Tuesday': $hari_indonesia = 'Selasa'; break;
        case 'Wednesday': $hari_indonesia = 'Rabu'; break;
        case 'Thursday': $hari_indonesia = 'Kamis'; break;
        case 'Friday': $hari_indonesia = 'Jumat'; break;
        case 'Saturday': $hari_indonesia = 'Sabtu'; break;
        case 'Sunday': $hari_indonesia = 'Minggu'; break;
    }
    
    // CEK HARI MINGGU (TUTUP)
    if ($hari_indonesia == 'Minggu') {
        $error = 'Maaf, tidak ada pelayanan pada hari Minggu. Silakan pilih hari Senin - Sabtu.';
    } 
    // CEK JADWAL DOKTER
    else {
        $cek_jadwal = $conn->query("
            SELECT * FROM jadwal_dokter 
            WHERE id_dokter = '$id_dokter' AND hari = '$hari_indonesia'
        ");
        
        if ($cek_jadwal->num_rows == 0) {
            $error = "Dokter tidak praktek pada hari " . $hari_indonesia . ". Silakan pilih hari lain sesuai jadwal dokter.";
        } else {
            // Cek jumlah antrian hari ini
            $jumlah_reservasi = $conn->query("SELECT COUNT(*) as total FROM reservasi WHERE id_poli = '$id_poli' AND tanggal_periksa = '$tanggal_periksa'")->fetch_assoc()['total'];
            
            $poli_data = $conn->query("SELECT kuota_harian FROM poli WHERE id_poli = '$id_poli'")->fetch_assoc();
            
            if ($jumlah_reservasi >= $poli_data['kuota_harian']) {
                $error = 'Maaf, kuota hari ini sudah penuh!';
            } else {
                // Insert reservasi
                $conn->query("INSERT INTO reservasi (id_pasien, id_dokter, id_poli, tanggal_periksa, keluhan, status_reservasi) 
                              VALUES ('$id_pasien', '$id_dokter', '$id_poli', '$tanggal_periksa', '$keluhan', 'menunggu')");
                
                $id_reservasi = $conn->insert_id;
                
                // Generate nomor antrian
                $nomor_antrian = sprintf("%03d", $jumlah_reservasi + 1);
                $prefix = "A";
                $nomor_antrian_full = $prefix . "-" . $nomor_antrian;
                
                // Hitung estimasi waktu (jam mulai 08:00)
                $durasi_per_pasien = 15;
                $jam_mulai = "08:00:00";
                $total_menit = $jumlah_reservasi * $durasi_per_pasien;
                $estimasi_timestamp = strtotime($jam_mulai) + ($total_menit * 60);
                $estimasi_waktu = date("H:i:s", $estimasi_timestamp);
                
                // Insert antrian
                $conn->query("INSERT INTO antrian (id_reservasi, nomor_antrian, status_antrian, posisi_antrian, estimasi_waktu_jam) 
                              VALUES ('$id_reservasi', '$nomor_antrian_full', 'menunggu', '" . ($jumlah_reservasi + 1) . "', '$estimasi_waktu')");
                
                $success = "Pendaftaran berhasil! Nomor antrian: $nomor_antrian_full";
                header("refresh:2;url=dashboard.php");
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - MEDKLIK</title>
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
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 18px 22px;
            max-width: 550px;
            width: 100%;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f6;
            margin: 0 auto;
        }

        .form-card h2 {
            font-size: 18px;
            color: #1e3c72;
            margin-bottom: 4px;
        }

        .form-card .subtitle {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 16px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 12px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2a5298;
        }

        .radio-group {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            margin-bottom: 0;
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-secondary {
            background: #f1f3f5;
            color: #495057;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .alert {
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 11px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
        }

        hr {
            margin: 12px 0;
            border: none;
            border-top: 1px solid #eef2f6;
        }

        .section-title {
            font-size: 13px;
            color: #1e3c72;
            margin-bottom: 10px;
            margin-top: 6px;
            font-weight: 600;
        }

        .no-bpjs-field {
            transition: all 0.3s ease;
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
                padding: 12px;
            }
            .form-card {
                padding: 16px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
    <script>
        function loadDokter() {
            var id_poli = document.getElementById('id_poli').value;
            var dokterSelect = document.getElementById('id_dokter');
            
            if (id_poli) {
                fetch('get_dokter.php?id_poli=' + id_poli)
                    .then(response => response.json())
                    .then(data => {
                        dokterSelect.innerHTML = '<option value="">Pilih Dokter</option>';
                        if (data.length > 0) {
                            data.forEach(dokter => {
                                dokterSelect.innerHTML += '<option value="' + dokter.id_dokter + '">' + dokter.nama_dokter + ' - ' + (dokter.spesialisasi || 'Umum') + '</option>';
                            });
                        } else {
                            dokterSelect.innerHTML = '<option value="">Tidak ada dokter di poli ini</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        dokterSelect.innerHTML = '<option value="">Gagal memuat dokter</option>';
                    });
            } else {
                dokterSelect.innerHTML = '<option value="">Pilih Poli Terlebih Dahulu</option>';
            }
        }

        function validateTanggal() {
            var tanggal = document.getElementById('tanggal_periksa').value;
            if (tanggal) {
                var day = new Date(tanggal).getDay();
                if (day == 0) {
                    alert('Maaf, tidak ada jadwal dokter pada hari Minggu. Silakan pilih hari Senin - Sabtu.');
                    document.getElementById('tanggal_periksa').value = '';
                    return false;
                }
            }
            return true;
        }

        function toggleNoBPJS() {
            var bpjsRadio = document.getElementById('jenis_bpjs');
            var noBpjsGroup = document.getElementById('no_bpjs_group');
            
            if (bpjsRadio && bpjsRadio.checked) {
                noBpjsGroup.style.display = 'block';
                document.getElementById('no_bpjs').required = true;
            } else {
                noBpjsGroup.style.display = 'none';
                document.getElementById('no_bpjs').required = false;
                document.getElementById('no_bpjs').value = '';
            }
        }

        function checkRadio() {
            var umumRadio = document.getElementById('jenis_umum');
            var bpjsRadio = document.getElementById('jenis_bpjs');
            
            if (umumRadio) {
                umumRadio.addEventListener('change', toggleNoBPJS);
            }
            if (bpjsRadio) {
                bpjsRadio.addEventListener('change', toggleNoBPJS);
            }
            toggleNoBPJS();
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MEDKLIK</h2>
            <p>Reservasi Klinik</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="pendaftaran.php" class="nav-item active">Pendaftaran</a>
            <a href="riwayat.php" class="nav-item">Riwayat Medis</a>
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
        <div class="form-card">
            <h2>Form Pendaftaran Berobat</h2>
            <div class="subtitle">Lengkapi data diri dan pilih poli & dokter</div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?> - Mengalihkan ke dashboard...</div>
            <?php endif; ?>

            <form method="POST">
                <!-- DATA DIRI PASIEN -->
                <div class="section-title">Data Diri Pasien</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($pasien['nama_lengkap']) ?>" placeholder="Nama lengkap" required>
                    </div>
                    <div class="form-group">
                        <label>NIK</label>
                        <input type="text" name="nik" value="<?= htmlspecialchars($pasien['nik'] ?? '') ?>" placeholder="16 digit NIK" maxlength="16">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="<?= $pasien['tanggal_lahir'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin">
                            <option value="">Pilih</option>
                            <option value="L" <?= ($pasien['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= ($pasien['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Alamat</label>
                        <input type="text" name="alamat" value="<?= htmlspecialchars($pasien['alamat'] ?? '') ?>" placeholder="Alamat lengkap">
                    </div>
                    <div class="form-group">
                        <label>No HP</label>
                        <input type="tel" name="no_hp" value="<?= htmlspecialchars($pasien['no_hp'] ?? '') ?>" placeholder="Nomor telepon">
                    </div>
                </div>

                <div class="form-group">
                    <label>Jenis Pasien</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="jenis_pasien" id="jenis_umum" value="Umum" <?= ($pasien['jenis_pasien'] ?? 'Umum') == 'Umum' ? 'checked' : '' ?>> Umum
                        </label>
                        <label>
                            <input type="radio" name="jenis_pasien" id="jenis_bpjs" value="BPJS" <?= ($pasien['jenis_pasien'] ?? '') == 'BPJS' ? 'checked' : '' ?>> BPJS
                        </label>
                    </div>
                </div>

                <!-- Field No BPJS (hidden by default) -->
                <div class="form-group no-bpjs-field" id="no_bpjs_group" style="display: none;">
                    <label>No BPJS</label>
                    <input type="text" name="no_bpjs" id="no_bpjs" value="<?= htmlspecialchars($pasien['no_bpjs'] ?? '') ?>" placeholder="Masukkan nomor BPJS" maxlength="20">
                </div>

                <hr>

                <!-- DATA PENDAFTARAN -->
                <div class="section-title">Data Pendaftaran</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Pilih Poli</label>
                        <select name="id_poli" id="id_poli" required onchange="loadDokter()">
                            <option value="">Pilih Poli</option>
                            <?php 
                            $poli_list = $conn->query("SELECT * FROM poli");
                            while($poli = $poli_list->fetch_assoc()): ?>
                            <option value="<?= $poli['id_poli'] ?>"><?= htmlspecialchars($poli['nama_poli']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pilih Dokter</label>
                        <select name="id_dokter" id="id_dokter" required>
                            <option value="">Pilih Poli Terlebih Dahulu</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tanggal Periksa</label>
                    <input type="date" name="tanggal_periksa" id="tanggal_periksa" min="<?= date('Y-m-d') ?>" required onchange="validateTanggal()">
                    <small style="color: #6c757d; font-size: 9px;">*Pelayanan: Senin - Sabtu (08:00 - 12:00 WIB), Minggu & Hari Libur Tutup</small>
                </div>

                <div class="form-group">
                    <label>Keluhan</label>
                    <textarea name="keluhan" rows="2" placeholder="Jelaskan keluhan singkat..." required></textarea>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Daftar Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Inisialisasi saat halaman load
        document.addEventListener('DOMContentLoaded', function() {
            checkRadio();
        });
    </script>
</body>
</html>