<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$user_id = current_user_id();

try {
    $alerts = [];

    $stmtUser = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit();
    }

    if (intval($user['is_blocked']) === 1) {
        $alerts[] = [
            "type" => "account_blocked",
            "title" => "Tai khoan da bi khoa",
            "message" => "Tai khoan cua ban vua bi khoa boi Admin. Vui long lien he phong Hanh chinh.",
            "created_at" => date('Y-m-d H:i:s')
        ];
    }

    $stmtNoShow = $conn->prepare(
        "SELECT meal_date, check_in_time
         FROM meal_attendance
         WHERE user_id = ? AND status = 'no_show'
         ORDER BY meal_date DESC
         LIMIT 1"
    );
    $stmtNoShow->execute([$user_id]);
    $noShow = $stmtNoShow->fetch(PDO::FETCH_ASSOC);

    if ($noShow) {
        $alerts[] = [
            "type" => "no_show",
            "title" => "Canh bao no-show",
            "message" => "Ban co suat an bi no-show vao ngay " . $noShow['meal_date'] . ". He thong da ap dung quy dinh xu ly.",
            "meal_date" => $noShow['meal_date'],
            "created_at" => date('Y-m-d H:i:s')
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "alerts" => $alerts,
            "is_blocked" => intval($user['is_blocked'])
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error"]);
}
?>