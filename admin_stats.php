<?php
require_once 'config.php';
try {
    $total_money = 0; // Wallet/payments disabled

    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('employee','student') AND is_approved=1 AND is_blocked=0");
    $total_students = $stmt->fetchColumn();

    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date = ? AND is_active = 1");
    $stmt->execute([$tomorrow]);
    $student_meals = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT SUM(quantity) FROM guest_bookings WHERE booking_date = ?");
    $stmt->execute([$tomorrow]);
    $guest_meals = $stmt->fetchColumn() ?: 0;

    echo json_encode([
        "status" => "success",
        "total_money" => $total_money,
        "total_students" => $total_students,
        "meals_tomorrow" => $student_meals + $guest_meals
    ]);
} catch (PDOException $e) { echo json_encode(["status" => "error"]); }
?>