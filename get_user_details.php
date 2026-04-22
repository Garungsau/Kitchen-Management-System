<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'kitchen_staff', 'admin']);

$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user_id = current_user_id();
$current_role = current_role();

if ($current_role !== 'admin' && $current_user_id !== $target_id) {
    echo json_encode(["status" => "error", "message" => "Access Denied"]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id, role, email, is_approved, is_blocked, created_at FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    $details = [];
    if ($user['role'] === 'employee') {
        $stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$target_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($user['role'] === 'kitchen_staff') {
        $stmt = $conn->prepare("SELECT * FROM kitchen_staff WHERE user_id = ?");
        $stmt->execute([$target_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Admin profile fallback from users table to keep UI stable.
        $details = [
            'full_name' => 'Administrator',
            'phone' => '',
            'hall_name' => '-',
            'dob' => null,
            'blood_group' => null,
            'gender' => null,
            'father_name' => '',
            'mother_name' => '',
            'present_address' => '',
            'permanent_address' => '',
            'nid_number' => '',
            'nid_no' => ''
        ];
    }

    echo json_encode([
        "status" => "success",
        "user" => $user,
        "details" => $details
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>