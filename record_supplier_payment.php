<?php
// API: Ghi nhận thanh toán cho nhà cung cấp (chỉ ghi nhận, không xóa)
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['order_id']) || empty($data['amount'])) {
    echo json_encode(["status" => "error", "message" => "Mã đơn hoặc số tiền bắt buộc"]);
    exit();
}

try {
    // Tạo bảng nếu chưa tồn tại
    $conn->exec("CREATE TABLE IF NOT EXISTS supplier_payment_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL COMMENT 'Số tiền đã ghi nhận',
        payment_method VARCHAR(50) COMMENT 'Phương thức: transfer, cash, check...',
        notes TEXT COMMENT 'Ghi chú thanh toán',
        recorded_by INT,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES ingredient_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Kiểm tra đơn hàng tồn tại
    $checkStmt = $conn->prepare("SELECT total_cost FROM ingredient_orders WHERE id = ?");
    $checkStmt->execute([$data['order_id']]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(["status" => "error", "message" => "Đơn hàng không tồn tại"]);
        exit();
    }
    
    // Kiểm tra số tiền ghi nhận không vượt quá tổng tiền đơn hàng
    $amount = floatval($data['amount']);
    $maxAmount = floatval($order['total_cost']);
    
    if ($amount > $maxAmount) {
        echo json_encode([
            "status" => "error", 
            "message" => "Số tiền ghi nhận ({$amount}) không thể vượt quá tổng tiền đơn ({$maxAmount})"
        ]);
        exit();
    }
    
    // Ghi nhận thanh toán
    $sql = "INSERT INTO supplier_payment_records 
            (order_id, amount, payment_method, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['order_id'],
        $amount,
        $data['payment_method'] ?? 'transfer',
        $data['notes'] ?? null,
        current_user_id()
    ]);

    // Tính tổng đã ghi nhận để kiểm tra
    $totalRecordedStmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) as total_recorded FROM supplier_payment_records WHERE order_id = ?"
    );
    $totalRecordedStmt->execute([$data['order_id']]);
    $totalRecorded = $totalRecordedStmt->fetchColumn();

    echo json_encode([
        "status" => "success",
        "message" => "✅ Ghi nhận chi phí thành công",
        "payment_id" => intval($conn->lastInsertId()),
        "total_recorded" => floatval($totalRecorded),
        "total_order_cost" => $maxAmount,
        "remaining" => max(0, $maxAmount - floatval($totalRecorded))
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
}
?>
