<?php
// API: Lấy danh sách đơn đặt hàng từ các nhà cung cấp
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

try {
    // Tạo bảng nếu chưa tồn tại
    $conn->exec("CREATE TABLE IF NOT EXISTS ingredient_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_name VARCHAR(150) NOT NULL,
        supplier_id INT,
        quantity_ordered DECIMAL(10,2) NOT NULL,
        unit VARCHAR(30),
        unit_price DECIMAL(10,2),
        total_cost DECIMAL(15,2),
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expected_date DATE,
        received_date DATE,
        status ENUM('pending','received','cancelled') DEFAULT 'pending',
        notes TEXT,
        created_by INT,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Lấy tham số lọc
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $sql = "SELECT io.*, s.company_name as supplier_name
            FROM ingredient_orders io
            LEFT JOIN suppliers s ON io.supplier_id = s.id";
    
    if ($status && $status !== 'all') {
        $sql .= " WHERE io.status = ?";
        $stmt = $conn->prepare($sql . " ORDER BY io.order_date DESC LIMIT 50");
        $stmt->execute([$status]);
    } else {
        $stmt = $conn->prepare($sql . " ORDER BY io.order_date DESC LIMIT 50");
        $stmt->execute();
    }
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => $orders,
        "count" => count($orders)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi cơ sở dữ liệu: " . $e->getMessage()]);
}
?>
