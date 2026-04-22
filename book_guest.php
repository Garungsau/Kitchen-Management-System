<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$quantity = intval($input['quantity']);
$target_date = $input['date']; 
$user_id = current_user_id();
$GUEST_RATE = 70;

if ($quantity < 1) {
    echo json_encode(["status" => "error", "message" => "Invalid quantity"]);
    exit();
}

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$current_hour = intval(date('H'));

if ($target_date <= $today) {
    echo json_encode(["status" => "error", "message" => "Cannot book for past dates or today."]);
    exit();
}

if ($target_date === $tomorrow && $current_hour >= 22) {
    echo json_encode(["status" => "error", "message" => "Time's up! Booking for tomorrow closes at 10 PM."]);
    exit();
}

try {
    $conn->beginTransaction();

    $ins = $conn->prepare("INSERT INTO guest_bookings (user_id, booking_date, quantity, total_cost) VALUES (?, ?, ?, ?)");
    $ins->execute([$user_id, $target_date, $quantity, 0]);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Booked successfully!"]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>