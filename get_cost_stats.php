<?php
require_once 'config.php';

try {
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('-7 days'));
    $month_start = date('Y-m-01');
    
    // Today cost
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity * unit_price), 0) as cost
        FROM kitchen_ingredients
        WHERE DATE(date_added) = ?
    ");
    $stmt->execute([$today]);
    $today_cost = $stmt->fetchColumn();
    
    // Week cost
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity * unit_price), 0) as cost
        FROM kitchen_ingredients
        WHERE DATE(date_added) >= ?
    ");
    $stmt->execute([$week_start]);
    $week_cost = $stmt->fetchColumn();
    
    // Month cost
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity * unit_price), 0) as cost
        FROM kitchen_ingredients
        WHERE DATE(date_added) >= ?
    ");
    $stmt->execute([$month_start]);
    $month_cost = $stmt->fetchColumn();
    
    // Get meal count for today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cnt
        FROM meal_attendance
        WHERE DATE(meal_date) = ? AND status = 'checked_in'
    ");
    $stmt->execute([$today]);
    $meal_count = (int)($stmt->fetchColumn() ?: 1);
    $per_meal = round($today_cost / $meal_count);
    
    echo json_encode([
        'status' => 'success',
        'today' => round($today_cost),
        'week' => round($week_cost),
        'month' => round($month_cost),
        'per_meal' => $per_meal
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
