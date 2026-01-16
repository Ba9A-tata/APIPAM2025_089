<?php
session_start();

$host = "localhost";
$user = "pembuku3_umkm";
$pass = "Xux@V5F56@ffMxp";
$db   = "pembuku3_umkm";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "DB Error: " . $e->getMessage()]);
    exit;
}

// Helper: cek login
function checkLogin() {
    if(!isset($_SESSION['id_user'])){
        http_response_code(401);
        echo json_encode(["message" => "Anda harus login"]);
        exit;
    }
}

// Ambil id_user dari session
function getUserId() {
    return $_SESSION['id_user'];
}
?>
