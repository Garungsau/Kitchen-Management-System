<?php
// API: Xác nhận nhận hàng từ nhà cung cấp
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['order_id'])) {
    echo json_encode(["status" => "error", "message" => "ID đơn hàng bắt buộc"]);
    exit();
}

try {
    // Cập nhật trạng thái nhận hàng
    $sql = "UPDATE ingredient_orders 
            SET status = 'received', 
                received_date = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data['order_id']]);

    if ($stmt->rowCount() == 0) {
        echo json_encode(["status" => "error", "message" => "Không tìm thấy đơn hàng"]);
        exit();
    }

    // Cập nhật số lượng tồn kho nếu tồn tại table ingredients
    $orderStmt = $conn->prepare("SELECT ingredient_name, quantity_ordered FROM ingredient_orders WHERE id = ?");
    $orderStmt->execute([$data['order_id']]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Kiểm tra tồn tại bảng ingredients
        $tableCheck = $conn->query("SHOW TABLES LIKE 'ingredients'")->rowCount();
        if ($tableCheck > 0) {
            $updateStmt = $conn->prepare("UPDATE ingredients 
                                        SET stock_qty = stock_qty + ?
                                        WHERE name = ? LIMIT 1");
            $updateStmt->execute([
                floatval($order['quantity_ordered']),
                $order['ingredient_name']
            ]);
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "✅ Đã xác nhận nhận hàng thành công"
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
}
?>
