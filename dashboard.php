<?php
require "config.php";
// Matikan error reporting agar tidak merusak format JSON
error_reporting(0); 
header("Content-Type: application/json; charset=UTF-8");

// Set timezone Jakarta agar perhitungan 'hari ini' sinkron dengan input user
date_default_timezone_set('Asia/Jakarta');

$id_user = intval($_GET['id_user'] ?? 0);

if($id_user <= 0){
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// 🔥 OPTIMASI TANGGAL: Pakai range string lebih kenceng daripada fungsi MONTH()/YEAR()
$awal_bulan  = date("Y-m-01 00:00:00");
$akhir_bulan = date("Y-m-t 23:59:59");

try {
    // 1. AMBIL SALDO AWAL
    $stmtSaldo = $conn->prepare("SELECT IFNULL(jumlah_saldo_awal, 0) FROM saldo_awal WHERE id_user = :id_user LIMIT 1");
    $stmtSaldo->execute([':id_user' => $id_user]);
    $saldo_awal = (float) $stmtSaldo->fetchColumn();

    // 2. HITUNG RINGKASAN (Satu Query, Range Tanggal Optimal)
    // Menggunakan BETWEEN pada kolom tanggal yang sudah di-index jauh lebih cepat
    $stmtKalkulasi = $conn->prepare("
        SELECT 
            SUM(CASE WHEN jenis='Pemasukan' THEN jumlah ELSE 0 END) AS in_total,
            SUM(CASE WHEN jenis='Pengeluaran' THEN jumlah ELSE 0 END) AS out_total,
            SUM(CASE WHEN jenis='Pemasukan' AND tanggal BETWEEN :awal AND :akhir THEN jumlah ELSE 0 END) AS in_bulan,
            SUM(CASE WHEN jenis='Pengeluaran' AND tanggal BETWEEN :awal AND :akhir THEN jumlah ELSE 0 END) AS out_bulan
        FROM transaksi 
        WHERE id_user = :id_user
    ");
    
    $stmtKalkulasi->execute([
        ':id_user' => $id_user,
        ':awal'    => $awal_bulan,
        ':akhir'   => $akhir_bulan
    ]);
    $res = $stmtKalkulasi->fetch(PDO::FETCH_ASSOC);

    $saldo_saat_ini = $saldo_awal + (float)$res['in_total'] - (float)$res['out_total'];

    // 3. DAFTAR TRANSAKSI TERAKHIR (Limit ketat biar payload kecil)
    // Hanya ambil 10 data terakhir untuk dashboard.
    $stmtList = $conn->prepare("
        SELECT t.id_transaksi, t.tanggal, t.jenis, t.jumlah, t.deskripsi, k.nama_kategori AS kategori 
        FROM transaksi t 
        LEFT JOIN kategori k ON t.id_kategori = k.id_kategori 
        WHERE t.id_user = :id_user 
        ORDER BY t.tanggal DESC, t.id_transaksi DESC
        LIMIT 10
    ");
    $stmtList->execute([':id_user' => $id_user]);
    $transaksi = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    // 4. RESPONSE JSON (Kirim format angka murni ke Android)
    echo json_encode([
        "saldo" => (float)$saldo_saat_ini,
        "total_pemasukan_bulan_ini" => (float)($res["in_bulan"] ?? 0),
        "total_pengeluaran_bulan_ini" => (float)($res["out_bulan"] ?? 0),
        "transaksi_bulan_ini" => $transaksi
    ], JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);

} catch (PDOException $e) {
    http_response_code(500);
    // Sembunyikan detail error DB demi keamanan, log ke file server saja
    echo json_encode(["status" => "error", "message" => "Internal Server Error"]);
}
?>