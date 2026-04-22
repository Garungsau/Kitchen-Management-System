<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
$name = isset($input['supplier_name']) ? trim($input['supplier_name']) : (isset($input['company']) ? trim($input['company']) : '');
$contact = isset($input['contact_person']) ? trim($input['contact_person']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$address = isset($input['address']) ? trim($input['address']) : '';

if ($name === '') {
    echo json_encode(["status" => "error", "message" => "Supplier name is required"]);
    exit();
}

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL UNIQUE,
        contact_person VARCHAR(255) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(120) NULL,
        address TEXT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($id > 0) {
        // Check for duplicate company name (excluding current record)
        $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE company_name = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$name, $id]);
        if ($checkStmt->fetch()) {
            echo json_encode(["status" => "error", "message" => "Nhà cung cấp \"" . $name . "\" đã tồn tại. Vui lòng sử dụng tên khác."]);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE suppliers SET company_name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $contact, $phone, $email, $address, $id]);
    } else {
        // Check for duplicate company name
        $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE company_name = ? LIMIT 1");
        $checkStmt->execute([$name]);
        if ($checkStmt->fetch()) {
            echo json_encode(["status" => "error", "message" => "Nhà cung cấp \"" . $name . "\" đã tồn tại. Vui lòng sử dụng tên khác."]);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO suppliers (company_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $phone, $email, $address]);
    }

    echo json_encode(["status" => "success", "message" => "Nhà cung cấp đã lưu thành công"]);
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode(["status" => "error", "message" => "Nhà cung cấp này đã tồn tại. Vui lòng kiểm tra lại tên."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
    }
}
?>