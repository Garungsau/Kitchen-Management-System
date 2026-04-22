<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_name VARCHAR(150) NOT NULL,
        contact_person VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(120) NULL,
        address VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $conn->query("SELECT id, company_name as company, contact_person, phone, email, address, active FROM suppliers ORDER BY company_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $rows]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>