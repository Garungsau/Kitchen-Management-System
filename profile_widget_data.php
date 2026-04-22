<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$user_id = current_user_id();
$today_date = date('Y-m-d');
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));

try {
    $hasStatusColumn = true;
    try {
        $conn->query("SELECT status FROM meal_attendance LIMIT 1");
    } catch (Exception $schemaEx) {
        $hasStatusColumn = false;
    }

    $profile = null;
    try {
        $stmt = $conn->prepare("SELECT s.full_name, s.student_id_no, s.photo_path
                                FROM students s
                                WHERE s.user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $profileEx) {
        $profile = null;
    }

    if (!$profile) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $profile = [
            'full_name' => '',
            'student_id_no' => '',
            'photo_path' => null,
            'email' => $userRow['email'] ?? ''
        ];
    }

    if ($hasStatusColumn) {
        $stmt = $conn->prepare("SELECT is_active, status
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date = ?
                                LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT is_active
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date = ?
                                LIMIT 1");
    }
    $stmt->execute([$user_id, $tomorrow_date]);
    $tomorrow_row = $stmt->fetch(PDO::FETCH_ASSOC);

    $guest_bookings = [];
    try {
        $stmt = $conn->prepare("SELECT id, quantity, total_cost, booking_date
                                FROM guest_bookings
                                WHERE user_id = ? AND booking_date >= ?
                                ORDER BY booking_date ASC");
        $stmt->execute([$user_id, $today_date]);
        $guest_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $guestEx) {
        $guest_bookings = [];
    }

    echo json_encode([
        'status' => 'success',
        'dates' => [
            'today' => $today_date,
            'tomorrow' => $tomorrow_date
        ],
        'profile' => array_merge($profile, ['wallet_balance' => 0]),
        'meal_tomorrow' => $tomorrow_row ? intval($tomorrow_row['is_active']) : 0,
        'meal_tomorrow_status' => $hasStatusColumn ? ($tomorrow_row['status'] ?? 'cancelled') : ((($tomorrow_row && intval($tomorrow_row['is_active']) === 1) ? 'registered' : 'cancelled')),
        'guest_bookings' => $guest_bookings
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>