<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
start_session_if_needed();
header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $userId = current_user_id();
    $role = current_role();

    $fullName = '';
    if ($role === 'kitchen_staff') {
        $stmt = $conn->prepare('SELECT full_name FROM kitchen_staff WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $fullName = $stmt->fetchColumn() ?: '';
    } else {
        $stmt = $conn->prepare('SELECT full_name FROM students WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $fullName = $stmt->fetchColumn() ?: '';
    }

    echo json_encode([
        'status' => 'success',
        'user_id' => $userId,
        'role' => $role,
        'full_name' => $fullName
    ]);
    exit;
}

echo json_encode(['status' => 'not_logged_in']);
?>
