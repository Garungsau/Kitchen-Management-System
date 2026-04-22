<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$account_type = isset($input['account_type']) ? trim($input['account_type']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password_raw = isset($input['password']) ? $input['password'] : '';
$full_name = isset($input['full_name']) ? trim($input['full_name']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$employee_id = isset($input['employee_id']) ? trim($input['employee_id']) : '';
$department = isset($input['department']) ? trim($input['department']) : '';

if ($account_type === '' || $email === '' || $password_raw === '' || $full_name === '') {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

$type_map = [
    'employee_labor' => ['role' => 'employee', 'department' => 'Lao dong', 'employee_type' => 'production'],
    'employee_office' => ['role' => 'employee', 'department' => 'Hanh chinh', 'employee_type' => 'admin'],
    'kitchen_staff' => ['role' => 'kitchen_staff', 'department' => 'Bep', 'employee_type' => null],
    'admin' => ['role' => 'admin', 'department' => 'Admin', 'employee_type' => null],
];

if (!isset($type_map[$account_type])) {
    echo json_encode(["status" => "error", "message" => "Invalid account type"]);
    exit();
}

$mapped = $type_map[$account_type];
$role = $mapped['role'];
if ($department === '') {
    $department = $mapped['department'];
}

if (($role === 'employee' || $role === 'kitchen_staff') && $employee_id === '') {
    echo json_encode(["status" => "error", "message" => "Employee ID is required"]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }

    if ($role === 'employee' || $role === 'kitchen_staff') {
        $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id_no = ?");
        $stmt->execute([$employee_id]);
        if ($stmt->fetch()) {
            throw new Exception('Employee ID already exists');
        }
    }

    $conn->beginTransaction();

    $password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, is_approved, is_blocked) VALUES (?, ?, ?, 1, 0)");
    $stmt->execute([$email, $password_hash, $role]);
    $user_id = $conn->lastInsertId();

    if ($role === 'employee') {
        $stmt = $conn->prepare("INSERT INTO students (
            user_id, full_name, student_id_no, registration_no, department, hall_name,
            father_name, mother_name, phone, present_address, permanent_address,
            dob, blood_group, gender, nid_no, birth_certificate_no,
            photo_path, id_card_path, wallet_balance, employee_type
        ) VALUES (?, ?, ?, ?, ?, ?, '', '', ?, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, ?)");

        $stmt->execute([
            $user_id,
            $full_name,
            $employee_id,
            $employee_id,
            $department,
            $department,
            $phone,
            $mapped['employee_type'] ?: 'production'
        ]);
    }

    if ($role === 'kitchen_staff') {
        $stmt = $conn->prepare("INSERT INTO kitchen_staff (
            user_id, full_name, father_name, mother_name, hall_name, phone,
            nid_number, present_address, permanent_address, dob,
            blood_group, gender, photo_path
        ) VALUES (?, ?, '', '', ?, ?, ?, '', '', NULL, NULL, NULL, NULL)");

        $stmt->execute([
            $user_id,
            $full_name,
            $department,
            $phone,
            $employee_id
        ]);
    }

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Account created successfully",
        "data" => [
            "user_id" => intval($user_id),
            "role" => $role,
            "email" => $email
        ]
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>