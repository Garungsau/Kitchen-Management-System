<?php
// API: Tạo đơn đặt hàng mới cho nguyên liệu
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$data = json_decode(file_get_contents('php://input'), true);

// Validation
$errors = [];
if (empty($data['ingredient_name'])) $errors[] = "Tên nguyên liệu bắt buộc";
if (empty($data['quantity_ordered'])) $errors[] = "Số lượng đặt hàng bắt buộc";
if (empty($data['supplier_id'])) $errors[] = "Nhà cung cấp bắt buộc";
if (empty($data['unit'])) $errors[] = "Đơn vị bắt buộc";

if (!empty($errors)) {
    echo json_encode(["status" => "error", "message" => implode(", ", $errors)]);
    exit();
}

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

    $sql = "INSERT INTO ingredient_orders 
            (ingredient_name, supplier_id, quantity_ordered, unit, unit_price, total_cost, expected_date, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['ingredient_name'],
        $data['supplier_id'],
        floatval($data['quantity_ordered']),
        $data['unit'],
        floatval($data['unit_price'] ?? 0),
        floatval(($data['unit_price'] ?? 0) * floatval($data['quantity_ordered'])),
        $data['expected_date'] ?? null,
        $data['notes'] ?? null,
        current_user_id()
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "✅ Đơn đặt hàng đã được tạo thành công",
        "order_id" => intval($conn->lastInsertId())
    ]);

} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
}
?>
