<?php
require "config.php";
error_reporting(0);
header('Content-Type: application/json');

$input = file_get_contents("php://input");
$data = json_decode($input);

$nama     = trim($data->namaLengkap ?? ''); 
$username = trim($data->username ?? '');
$password = trim($data->password ?? '');

if(empty($nama) || empty($username) || empty($password)){
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
    exit;
}

try {
    // Cek Username
    $stmtCek = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmtCek->execute([':username' => $username]);
    if($stmtCek->fetchColumn() > 0){
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Username sudah ada"]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $conn->beginTransaction(); // Mulai transaksi DB agar kalau gagal, semua batal

    // 1. Simpan User
    $stmt = $conn->prepare("INSERT INTO users (nama_lengkap, username, password) VALUES (:nama, :username, :pass)");
    $stmt->execute([':nama' => $nama, ':username' => $username, ':pass' => $hash]);
    $newUserId = $conn->lastInsertId();

    // 2. 🔥 INPUT KATEGORI DEFAULT
    $defaults = [
        ['Penjualan', 'Pemasukan'],
        ['Bonus', 'Pemasukan'],
        ['Belanja Bahan', 'Pengeluaran'],
        ['Gaji', 'Pengeluaran'],
        ['Operasional', 'Pengeluaran'],
        ['Transportasi', 'Pengeluaran']
    ];

    $stmtKat = $conn->prepare("INSERT INTO kategori (nama_kategori, jenis_default, id_user) VALUES (?, ?, ?)");
    foreach ($defaults as $kat) {
        $stmtKat->execute([$kat[0], $kat[1], $newUserId]);
    }

    $conn->commit(); // Simpan permanen

    http_response_code(201);
    echo json_encode(["status" => "success", "message" => "Registrasi berhasil"]);

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>