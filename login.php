<?php
require "config.php";

// Matikan error reporting yang berupa teks, tapi biarkan catch menangkap error
error_reporting(0);
header('Content-Type: application/json');

// Mengambil data input
$input = file_get_contents("php://input");
$data = json_decode($input);

$username = isset($data->username) ? trim($data->username) : '';
$password = isset($data->password) ? trim($data->password) : '';

// Jika diakses via browser tanpa input, kirim pesan ini (HTTP 200 agar bisa dibaca)
if(empty($username) || empty($password)){
    echo json_encode(["status" => "error", "message" => "Username dan password wajib diisi"]);
    exit;
}

try {
    // DISESUAIKAN: Menghapus nama_lengkap karena tidak ada di tabel user kamu
    $stmt = $conn->prepare("SELECT id_user, username, password FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifikasi password
    if($user && password_verify($password, $user['password'])){
        echo json_encode([
            "status" => "success",
            "message" => "Login berhasil",
            "data" => [
                "id_user" => (int)$user['id_user'],
                "username" => $user['username']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Username atau password salah"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Masalah database: " . $e->getMessage()]);
}
?>