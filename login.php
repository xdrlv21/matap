<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: pemeriksaanseragam.php");
    exit();
}

$host = "localhost"; $user = "root"; $pass = ""; $db = "db_pemeriksaan";
$koneksi = new mysqli($host, $user, $pass, $db);

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        if (password_verify($password, $user_data['password'])) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['siswa_id'] = $user_data['siswa_id'];
            $_SESSION['api_key'] = $user_data['api_key'];
            header("Location: pemeriksaanseragam.php");
            exit();
        } else { $error = "Password atau Security Key yang Anda masukkan salah."; }
    } else { $error = "Username tidak ditemukan dalam database sistem."; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GATEWAY LOGIN — SKADIK 506</title>
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

        <div style="text-align: center; margin-bottom: 24px;">
            <h2 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: #0f172a;">SKADIK 506</h2>
            <p style="margin: 3px 0 0 0; font-size: 0.82rem; color: #475569;">Sistem Informasi Disiplin Seragam</p>
        </div>

        <?php if($error): ?>
            <div class="login-error" style="margin-bottom: 16px;">⚠ <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="input-group">
                <label style="color:#0f172a;">ID Pengguna (Username)</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                    <input type="text" name="username" placeholder="Masukkan username" required style="background: rgba(255,255,255,0.5);">
                </div>
            </div>

            <div class="input-group" style="margin-top: 14px;">
                <label style="color:#0f172a;">Security Key / Password</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password" placeholder="••••••••" required style="background: rgba(255,255,255,0.5);">
                </div>
            </div>

            <button type="submit" class="btn-submit" style="margin-top:24px; width: 100%; padding:12px; font-weight:700;">VERIFY &amp; MASUK →</button>
        </form>

        <div style="margin-top:20px; display:flex; flex-direction:column; gap:12px;">
            <a href="register.php" style="display:block; text-align:center; font-size:0.82rem;
                    font-weight:700; color: #0284c7; text-decoration:none;
                    padding:10px; border-radius:12px; border:1px solid rgba(255,255,255,0.4);
                    background:rgba(255,255,255,0.3); transition:all 0.2s ease;"
               onmouseover="this.style.background='rgba(255,255,255,0.5)'"
               onmouseout="this.style.background='rgba(255,255,255,0.3)'">
                Belum punya akun? Daftar di sini →
            </a>
        </div>
    </div>

</body>
</html>