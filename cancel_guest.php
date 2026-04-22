<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = $input['booking_id'];
$user_id = current_user_id();

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT total_cost, quantity, booking_date FROM guest_bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    $target_date = $booking['booking_date'];
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $current_hour = intval(date('H'));

    if ($target_date <= $today) {
        throw new Exception("Cannot cancel past/today's bookings.");
    }

    if ($target_date === $tomorrow && $current_hour >= 22) {
        throw new Exception("Too late! Cancellation deadline for tomorrow was 10 PM.");
    }
    // -----------------------

    $del = $conn->prepare("DELETE FROM guest_bookings WHERE id = ?");
    $del->execute([$booking_id]);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Cancelled successfully."]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>