<?php
require "config.php";
error_reporting(0);
header('Content-Type: application/json');

$id_user = intval($_GET['id_user'] ?? 0);

if($id_user <= 0){
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents("php://input");
$data = json_decode($input);

try {
    switch($method){
        case 'GET':
            $id_transaksi = intval($_GET['id'] ?? 0);
            $bulan = intval($_GET['bulan'] ?? 0);
            $tahun = intval($_GET['tahun'] ?? 0);

            if ($id_transaksi > 0) {
                // Ambil 1 data spesifik
                $stmt = $conn->prepare("SELECT t.*, k.nama_kategori as kategori FROM transaksi t LEFT JOIN kategori k ON t.id_kategori = k.id_kategori WHERE t.id_user = :id_user AND t.id_transaksi = :id");
                $stmt->execute([':id_user' => $id_user, ':id' => $id_transaksi]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass());
            } 
            else if ($bulan > 0 && $tahun > 0) {
                // 🔥 PERBAIKAN: Filter berdasarkan Bulan & Tahun biar GAK LEMOT
                $stmt = $conn->prepare("SELECT t.*, k.nama_kategori as kategori FROM transaksi t LEFT JOIN kategori k ON t.id_kategori = k.id_kategori WHERE t.id_user = :id_user AND MONTH(t.tanggal) = :bulan AND YEAR(t.tanggal) = :tahun ORDER BY t.tanggal DESC, t.id_transaksi DESC");
                $stmt->execute([':id_user' => $id_user, ':bulan' => $bulan, ':tahun' => $tahun]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } 
            else {
                // Ambil semua (Limit 50 biar ga nge-hang)
                $stmt = $conn->prepare("SELECT t.*, k.nama_kategori as kategori FROM transaksi t LEFT JOIN kategori k ON t.id_kategori = k.id_kategori WHERE t.id_user = :id_user ORDER BY t.tanggal DESC LIMIT 50");
                $stmt->execute([':id_user' => $id_user]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        case 'POST':
            // 🔥 PASTIKAN: id_transaksi di database lo itu AUTO_INCREMENT
            $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, jenis, jumlah, deskripsi, id_kategori, id_user) VALUES (:tgl, :jns, :jml, :desk, :id_kat, :uid)");
            $stmt->execute([
                ':tgl' => $data->tanggal,
                ':jns' => $data->jenis,
                ':jml' => $data->jumlah,
                ':desk' => $data->deskripsi,
                ':id_kat' => $data->id_kategori,
                ':uid' => $id_user
            ]);
            echo json_encode(["status" => "success", "id" => $conn->lastInsertId()]);
            break;

        case 'PUT':
            $id_update = intval($_GET['id'] ?? 0);
            if($id_update <= 0) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
                exit;
            }

            $stmt = $conn->prepare("UPDATE transaksi SET tanggal = :tgl, jenis = :jns, jumlah = :jml, deskripsi = :desk, id_kategori = :id_kat WHERE id_transaksi = :id AND id_user = :uid");
            $stmt->execute([
                ':tgl' => $data->tanggal,
                ':jns' => $data->jenis,
                ':jml' => $data->jumlah,
                ':desk' => $data->deskripsi,
                ':id_kat' => $data->id_kategori,
                ':id' => $id_update,
                ':uid' => $id_user
            ]);
            echo json_encode(["status" => "success", "message" => "Data updated"]);
            break;

        case 'DELETE':
            $id_del = intval($_GET['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM transaksi WHERE id_transaksi = :id AND id_user = :uid");
            $stmt->execute([':id' => $id_del, ':uid' => $id_user]);
            echo json_encode(["status" => "success"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>