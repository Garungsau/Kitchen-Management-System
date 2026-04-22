<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
$unit_cost = isset($_GET['unit_cost']) ? floatval($_GET['unit_cost']) : 35000;
if (!preg_match('/^\\d{4}-\\d{2}$/', $month)) {
    echo json_encode(["status" => "error", "message" => "Invalid month format"]);
    exit();
}

$current_start = $month . '-01';
$current_end = date('Y-m-t', strtotime($current_start));

try {
    $stmtMeal = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date BETWEEN ? AND ? AND status IN ('registered','checked_in')");
    $stmtNoShow = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date BETWEEN ? AND ? AND status = 'no_show'");
    $stmtGuest = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM guest_bookings WHERE booking_date BETWEEN ? AND ?");

    $stmtMeal->execute([$current_start, $current_end]);
    $employeeMeals = intval($stmtMeal->fetchColumn());

    $stmtGuest->execute([$current_start, $current_end]);
    $guestMeals = intval($stmtGuest->fetchColumn());

    $stmtNoShow->execute([$current_start, $current_end]);
    $noShowCount = intval($stmtNoShow->fetchColumn());

    $totalMeals = $employeeMeals + $guestMeals;
    $estimatedCost = round($totalMeals * $unit_cost, 2);
    $costPerMeal = $totalMeals > 0 ? round($estimatedCost / $totalMeals, 2) : 0;

    $trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime($current_start . " -$i month"));
        $s = $m . '-01';
        $e = date('Y-m-t', strtotime($s));

        $stmtMeal->execute([$s, $e]);
        $mEmp = intval($stmtMeal->fetchColumn());
        $stmtGuest->execute([$s, $e]);
        $mGuest = intval($stmtGuest->fetchColumn());

        $trend[] = [
            'month' => $m,
            'total_meals' => $mEmp + $mGuest
        ];
    }

    echo json_encode([
        "status" => "success",
        "month" => $month,
        "unit_cost" => $unit_cost,
        "summary" => [
            "employee_meals" => $employeeMeals,
            "guest_meals" => $guestMeals,
            "total_meals" => $totalMeals,
            "estimated_cost" => $estimatedCost,
            "cost_per_meal" => $costPerMeal,
            "no_show_count" => $noShowCount,
            "no_show_ratio" => $employeeMeals > 0 ? round(($noShowCount / $employeeMeals) * 100, 2) : 0
        ],
        "trend" => $trend
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>