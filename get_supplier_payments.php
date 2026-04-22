<?php
// API: Lấy danh sách đơn hàng chưa ghi nhận thanh toán
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

try {
    $filter = $_GET['filter'] ?? 'all';
    
    $sql = "SELECT io.*, s.company_name as supplier_name,
                   (SELECT COALESCE(SUM(amount), 0) FROM supplier_payment_records WHERE order_id = io.id) as recorded_amount
            FROM ingredient_orders io
            LEFT JOIN suppliers s ON io.supplier_id = s.id
            WHERE io.status = 'received'";
    
    if ($filter === 'pending') {
        $sql .= " AND (SELECT COALESCE(SUM(amount), 0) FROM supplier_payment_records WHERE order_id = io.id) < io.total_cost";
    } elseif ($filter === 'recorded') {
        $sql .= " AND (SELECT COALESCE(SUM(amount), 0) FROM supplier_payment_records WHERE order_id = io.id) > 0";
    }
    
    $sql .= " ORDER BY io.order_date DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tính toán thống kê
    $totalOrders = count($orders);
    $totalAmountPending = 0;
    $totalAmountRecorded = 0;
    $suppliers = [];
    
    foreach ($orders as $order) {
        $remaining = floatval($order['total_cost']) - floatval($order['recorded_amount']);
        $totalAmountPending += max(0, $remaining);
        $totalAmountRecorded += floatval($order['recorded_amount']);
        
        if (!in_array($order['supplier_name'], $suppliers)) {
            $suppliers[] = $order['supplier_name'];
        }
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $orders,
        "stats" => [
            "total_orders" => $totalOrders,
            "total_pending" => $totalAmountPending,
            "total_recorded" => $totalAmountRecorded,
            "total_suppliers" => count($suppliers)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
}
?>
