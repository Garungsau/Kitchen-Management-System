<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$user_id = current_user_id();
$today_date = date('Y-m-d');
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));

try {
    $stmt = $conn->prepare("
        SELECT s.*, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $student = null;
    try {
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $profileEx) {
        $student = null;
    }

    if (!$student) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $student = [
            'user_id' => $user_id,
            'email' => $u['email'] ?? '',
            'full_name' => '',
            'student_id_no' => '',
            'photo_path' => null,
            'wallet_balance' => 0
        ];
    }

    $stmt = $conn->prepare("SELECT is_active FROM meal_attendance WHERE user_id = ? AND meal_date = ?");
    $stmt->execute([$user_id, $today_date]);
    $today_status = $stmt->fetchColumn(); 
    $meal_today = ($today_status === false) ? 0 : $today_status;

    $stmt = $conn->prepare("SELECT is_active FROM meal_attendance WHERE user_id = ? AND meal_date = ?");
    $stmt->execute([$user_id, $tomorrow_date]);
    $tmr_status = $stmt->fetchColumn();
    $meal_tomorrow = ($tmr_status === false) ? 0 : $tmr_status;

    $guest_bookings = [];
    try {
        $stmt = $conn->prepare("SELECT id, quantity, total_cost, booking_date FROM guest_bookings WHERE user_id = ? AND booking_date >= ? ORDER BY booking_date ASC");
        $stmt->execute([$user_id, $today_date]);
        $guest_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $guestEx) {
        $guest_bookings = [];
    }

    echo json_encode([
        "status" => "success",
        "data" => $student,
        "meal_today" => $meal_today,       
        "meal_tomorrow" => $meal_tomorrow, 
        "guest_bookings" => $guest_bookings,
        "dates" => [
            "today" => $today_date,
            "tomorrow" => $tomorrow_date
        ]
    ]);

} catch (PDOException $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
?>