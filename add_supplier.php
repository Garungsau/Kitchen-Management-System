<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$company = $data['company'] ?? '';
$contact = $data['contact_person'] ?? '';
$phone = $data['phone'] ?? '';
$email = $data['email'] ?? '';
$address = $data['address'] ?? '';

if (!$company || !$contact || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc']);
    exit;
}

try {
    // Check for duplicate company name
    $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE company_name = ? LIMIT 1");
    $checkStmt->execute([$company]);
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Nhà cung cấp "' . $company . '" đã tồn tại. Vui lòng sử dụng tên khác.']);
        exit;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO suppliers (company_name, contact_person, phone, email, address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$company, $contact, $phone, $email, $address]);
    echo json_encode(['status' => 'success', 'message' => 'Thêm thành công']);
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode(['status' => 'error', 'message' => 'Nhà cung cấp này đã tồn tại. Vui lòng kiểm tra lại tên.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}
?>
