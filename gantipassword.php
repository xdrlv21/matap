<?php
$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db   = "db_pemeriksaan";

$koneksi = new mysqli($host, $user, $pass, $db);
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

$username_target = "rastra506";        
$username_baru   = "rastra506";     
$password_baru   = "password123"; 

$password_hash = password_hash($password_baru, PASSWORD_BCRYPT);

$stmt = $koneksi->prepare("UPDATE users SET username = ?, password = ? WHERE username = ?");
$stmt->bind_param("sss", $username_baru, $password_hash, $username_target);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SYSTEM UTILITY - PASSWORD UPDATER</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="main-dashboard login-size">
    <div class="dashboard-header" style="justify-content: center;">
        <div class="header-title">
            <h1>SYSTEM UTILITY</h1>
            <p>PASSWORD UPDATER</p>
        </div>
    </div>
    <div class="dashboard-body">
        <?php
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo "<p style='color: #10b981; font-weight: bold; text-align:center;'>[SUCCESS] Akun '$username_target' berhasil diubah!</p>";
            echo "<div style='background: rgba(255,255,255,0.4); padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border);'>";
            echo "<ul style='margin: 0; padding-left: 20px; font-size: 0.9rem;'>";
            echo "<li>Username Baru: <b>$username_baru</b></li>";
            echo "<li>Password Baru: <b>$password_baru</b> (Tersimpan aman)</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<p style='color: #ef4444; font-weight: bold; text-align:center;'>[GAGAL] Akun dengan username '$username_target' tidak ditemukan atau data yang kamu masukkan sama dengan yang lama.</p>";
        }
        $stmt->close();
        $koneksi->close();
        ?>
        <br>
        <a href="login.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none; box-sizing: border-box;">Kembali ke Login</a>
    </div>
</div>
</body>
</html>