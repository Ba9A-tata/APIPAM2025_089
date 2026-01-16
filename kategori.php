<?php
require "config.php";
error_reporting(0); 
header('Content-Type: application/json');

$id_user = intval($_GET['id_user'] ?? 0);

if($id_user <= 0){
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: ID User tidak valid"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents("php://input");
$data = json_decode($input);

try {
    switch($method){
        case 'GET':
            $stmt = $conn->prepare("SELECT id_kategori, nama_kategori, jenis_default 
                                    FROM kategori WHERE id_user = :id_user
                                    ORDER BY jenis_default DESC, nama_kategori ASC");
            $stmt->execute([':id_user' => $id_user]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            $nama = trim($data->nama_kategori ?? '');
            $jenis = trim($data->jenis_default ?? '');
            $jenis = ucfirst(strtolower($jenis));

            if(!$nama || !in_array($jenis, ['Pemasukan', 'Pengeluaran'])){
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Data kategori tidak valid"]);
                exit;
            }

            $stmt = $conn->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = :nama AND id_user = :id_user");
            $stmt->execute([':nama' => $nama, ':id_user' => $id_user]);
            if($stmt->fetchColumn() > 0){
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Kategori sudah ada"]);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori, jenis_default, id_user) VALUES (:nama, :jenis, :id_user)");
            $stmt->execute([':nama' => $nama, ':jenis' => $jenis, ':id_user' => $id_user]);
            echo json_encode(["id_kategori" => (int)$conn->lastInsertId(), "nama_kategori" => $nama, "jenis_default" => $jenis]); 
            break;
            
        case 'DELETE':
            $id_kategori = intval($_GET['id'] ?? 0);
            if($id_kategori <= 0){
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
                exit;
            }

            // 1. CEK DULU: Apakah kategori ini sedang dipakai di tabel transaksi?
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM transaksi WHERE id_kategori = :id AND id_user = :uid");
            $stmtCheck->execute([':id' => $id_kategori, ':uid' => $id_user]);
            $count = $stmtCheck->fetchColumn();

            if($count > 0){
                // JIKA ADA TRANSAKSI: Kirim pesan error yang jujur, jangan hapus apapun!
                http_response_code(400); // Bad Request
                echo json_encode([
                    "status" => "error", 
                    "message" => "Gagal: Kategori ini tidak bisa dihapus karena masih digunakan oleh $count data transaksi."
                ]);
                exit;
            }

            // 2. JIKA KOSONG: Baru jalankan perintah hapus
            $stmt = $conn->prepare("DELETE FROM kategori WHERE id_kategori = :id AND id_user = :uid");
            $stmt->execute([':id' => $id_kategori, ':uid' => $id_user]);

            if($stmt->rowCount() > 0){
                echo json_encode(["status" => "success", "message" => "Kategori berhasil dihapus"]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Kategori tidak ditemukan"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>