<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: pemeriksaanseragam.php");
    exit();
}

$host = "localhost"; $user = "root"; $pass = ""; $db = "db_pemeriksaan";
$koneksi = new mysqli($host, $user, $pass, $db);

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = 'siswa';

    if (empty($nama_lengkap) || empty($username) || empty($password)) {
        $error = "Semua kolom wajib diisi!";
    } else {
        // Cek letak kolom nisn untuk menghindari error "Unknown column"
        $cek_kolom_siswa = $koneksi->query("SHOW COLUMNS FROM siswa LIKE 'nisn'");
        $is_nisn_in_siswa = ($cek_kolom_siswa && $cek_kolom_siswa->num_rows > 0);
        
        // 1. Ambil NISN terakhir dari tabel yang tepat (siswa atau users)
        $tabel_target = $is_nisn_in_siswa ? 'siswa' : 'users';
        $query_max = "SELECT nisn FROM {$tabel_target} WHERE nisn REGEXP '^[0-9]+$' ORDER BY CAST(nisn AS UNSIGNED) DESC LIMIT 1";
        $res_max = $koneksi->query($query_max);
        
        if ($res_max && $res_max->num_rows > 0) {
            $row_max = $res_max->fetch_assoc();
            $last_nisn = $row_max['nisn'];
            // Tambah 1 & pertahankan jumlah karakter berawalan nol (misal 00012345 -> 00012346)
            $next_nisn = str_pad(intval($last_nisn) + 1, strlen($last_nisn), '0', STR_PAD_LEFT);
        } else {
            $next_nisn = "00012345"; // Nilai awal jika database kosong
        }

        // 2. Validasi Username agar tidak duplikat
        $cek_user = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
        $cek_user->bind_param("s", $username);
        $cek_user->execute();
        if ($cek_user->get_result()->num_rows > 0) {
            $error = "Username '@" . htmlspecialchars($username) . "' sudah digunakan.";
        } else {
            // 3. Masukkan data ke tabel siswa
            if ($is_nisn_in_siswa) {
                $stmt_siswa = $koneksi->prepare("INSERT INTO siswa (nama, nisn) VALUES (?, ?)");
                $stmt_siswa->bind_param("ss", $nama_lengkap, $next_nisn);
            } else {
                $stmt_siswa = $koneksi->prepare("INSERT INTO siswa (nama) VALUES (?)");
                $stmt_siswa->bind_param("s", $nama_lengkap);
            }
            
            if ($stmt_siswa->execute()) {
                $siswa_id = $koneksi->insert_id;
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $api_key = bin2hex(random_bytes(16));

                // Cek apakah tabel users juga punya kolom nisn
                $cek_kolom_users = $koneksi->query("SHOW COLUMNS FROM users LIKE 'nisn'");
                if ($cek_kolom_users && $cek_kolom_users->num_rows > 0) {
                    $stmt_user = $koneksi->prepare("INSERT INTO users (username, password, role, nisn, siswa_id, api_key) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_user->bind_param("ssssis", $username, $hashed_password, $role, $next_nisn, $siswa_id, $api_key);
                } else {
                    $stmt_user = $koneksi->prepare("INSERT INTO users (username, password, role, siswa_id, api_key) VALUES (?, ?, ?, ?, ?)");
                    $stmt_user->bind_param("sssis", $username, $hashed_password, $role, $siswa_id, $api_key);
                }
                
                if ($stmt_user->execute()) {
                    $success = "Akun berhasil dibuat! NISN Anda: <b>" . $next_nisn . "</b>";
                } else {
                    $error = "Gagal menyimpan akun autentikasi.";
                }
            } else {
                $error = "Gagal menyimpan profil data siswa.";
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
    <title>REGISTRASI AKUN — SKADIK 506</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: url('langit.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 35px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            z-index: 10;
        }
        .centered-logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .centered-logos img { height: 42px; width: auto; }
        .centered-divider { height: 22px; width: 1.5px; background: rgba(255,255,255,0.5); }
        .success-box {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46; padding: 12px; border-radius: 12px;
            font-size: 0.85rem; text-align: center; margin-bottom: 16px;
        }
        /* UKURAN IKON KECIL & PRESISI */
        .input-icon {
            width: 16px !important;
            height: 16px !important;
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.7;
            color: #0f172a;
        }
        .input-icon-wrap input {
            padding-left: 42px !important;
        }
    </style>
</head>
<body>

    <div class="motion-wrapper">
        <div class="graphic-orb orb-1"></div>
        <div class="graphic-orb orb-2"></div>
    </div>

    <div class="glass-card">
        <div class="centered-logos">
            <img src="skadik.png" alt="SKADIK 506">
            <div class="centered-divider"></div>
            <img src="tniau.png" alt="TNI AU">
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: #0f172a;">SKADIK 506</h2>
            <p style="margin: 3px 0 0 0; font-size: 0.82rem; color: #475569;">Pendaftaran Akun Baru Siswa</p>
        </div>

        <?php if($error): ?>
            <div class="login-error" style="margin-bottom: 16px;">⚠ <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success-box">✓ <?= $success; ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="input-group">
                <label style="color:#0f172a;">Nama Lengkap Siswa</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input type="text" name="nama_lengkap" placeholder="Nama sesuai daftar hadir" required style="background: rgba(255,255,255,0.5);">
                </div>
            </div>

            <div class="input-group" style="margin-top: 12px;">
                <label style="color:#0f172a;">ID Pengguna (Username)</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/>
                    </svg>
                    <input type="text" name="username" placeholder="Buat nama pengguna" required style="background: rgba(255,255,255,0.5);">
                </div>
            </div>

            <div class="input-group" style="margin-top: 12px;">
                <label style="color:#0f172a;">Kata Sandi / Password</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password" placeholder="••••••••" required style="background: rgba(255,255,255,0.5);">
                </div>
            </div>

            <button type="submit" class="btn-submit" style="margin-top:20px; width: 100%; padding:12px; font-weight:700;">BUAT AKUN SEKARANG →</button>
        </form>

        <div style="margin-top:20px;">
            <a href="login.php" style="display:block; text-align:center; font-size:0.82rem;
                    font-weight:700; color: #0284c7; text-decoration:none;
                    padding:10px; border-radius:12px; border:1px solid rgba(255,255,255,0.4);
                    background:rgba(255,255,255,0.3); transition:all 0.2s ease;"
               onmouseover="this.style.background='rgba(255,255,255,0.5)'"
               onmouseout="this.style.background='rgba(255,255,255,0.3)'">
                ← Sudah punya akun? Masuk di sini
            </a>
        </div>
    </div>

</body>
</html>