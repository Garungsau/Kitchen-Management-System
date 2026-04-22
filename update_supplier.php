<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;
$company = $data['company'] ?? '';
$contact = $data['contact_person'] ?? '';
$phone = $data['phone'] ?? '';
$email = $data['email'] ?? '';
$address = $data['address'] ?? '';

if (!$id || !$company || !$contact || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin bắt buộc']);
    exit;
}

try {
    // Check for duplicate company name (excluding current record)
    $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE company_name = ? AND id != ? LIMIT 1");
    $checkStmt->execute([$company, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Nhà cung cấp "' . $company . '" đã tồn tại. Vui lòng sử dụng tên khác.']);
        exit;
    }
    
    $stmt = $conn->prepare("
        UPDATE suppliers 
        SET company_name = ?, contact_person = ?, phone = ?, email = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([$company, $contact, $phone, $email, $address, $id]);
    echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode(['status' => 'error', 'message' => 'Nhà cung cấp này đã tồn tại. Vui lòng kiểm tra lại tên.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}
?>
