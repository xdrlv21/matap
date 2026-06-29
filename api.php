<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$host = "localhost"; $user = "root"; $pass = ""; $db = "db_pemeriksaan";
$koneksi = new mysqli($host, $user, $pass, $db);

$headers = getallheaders();
if (!isset($headers['X-API-KEY'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Akses Ditolak. Membutuhkan X-API-KEY di Header."]);
    exit();
}

$api_key = $headers['X-API-KEY'];

// Cek autentikasi API key di database
$stmt = $koneksi->prepare("SELECT role FROM users WHERE api_key = ?");
$stmt->bind_param("s", $api_key); $stmt->execute(); $res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "X-API-KEY Salah atau Tidak Aktif."]);
    exit();
}

$role = $res->fetch_assoc()['role'];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Hanya Kepsek dan Guru yang boleh menembak data massal dari API ini
    if ($role == 'kepsek' || $role == 'guru') {
        $result = $koneksi->query("SELECT dp.id, s.nama, dp.pakai_topi, dp.rambut_rapi, dp.pakai_sabuk, dp.status_pemeriksaan, dp.created_at FROM data_pemeriksaan dp JOIN siswa s ON dp.siswa_id = s.id ORDER BY dp.id DESC");
        $data = [];
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        
        http_response_code(200);
        echo json_encode(["status" => "success", "client_role" => $role, "data" => $data]);
    } else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Role Akun Anda tidak diberi izin akses API."]);
    }
}
$koneksi->close();
?>