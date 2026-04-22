<?php
/**
 * Chart Data APIs for Dashboard Statistics
 * Provides data for: weekly registration rates, favorite meals, cost trends
 */
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['admin', 'kitchen_staff']);

$endpoint = isset($_GET['endpoint']) ? trim($_GET['endpoint']) : 'weekly_registration';
$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

try {
    switch ($endpoint) {
        case 'weekly_registration':
            echo json_encode(getWeeklyRegistration($month, $days));
            break;
        
        case 'favorite_meals':
            echo json_encode(getFavoriteMeals($month));
            break;
        
        case 'monthly_cost_trend':
            echo json_encode(getMonthlyCostTrend());
            break;
        
        case 'department_statistics':
            echo json_encode(getDepartmentStatistics($month));
            break;
        
        case 'daily_attendance':
            echo json_encode(getDailyAttendance($days));
            break;
        
        default:
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Unknown endpoint: $endpoint"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

/**
 * Get weekly registration rate (%) for last N days
 */
function getWeeklyRegistration($month, $days) {
    global $conn;
    
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-$days days"));
    
    // Get daily registration data
    $sql = "
        SELECT 
            DATE(meal_date) as date,
            COUNT(*) as total_registered,
            COUNT(CASE WHEN status IN ('checked_in') THEN 1 END) as checked_in,
            COUNT(CASE WHEN status = 'registered' THEN 1 END) as registered_only,
            COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show
        FROM meal_attendance
        WHERE meal_date BETWEEN ? AND ? AND user_id IN (
            SELECT id FROM users WHERE role = 'employee'
        )
        GROUP BY DATE(meal_date)
        ORDER BY date
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total employees for ratio calculation
    $stmtUsers = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'employee'");
    $stmtUsers->execute();
    $totalEmployees = intval($stmtUsers->fetchColumn()) ?: 1;
    
    $chartData = [];
    foreach ($dailyData as $day) {
        $chartData[] = [
            'date' => $day['date'],
            'registered' => intval($day['total_registered']),
            'checked_in' => intval($day['checked_in']),
            'no_show' => intval($day['no_show']),
            'registration_rate' => round((intval($day['total_registered']) / $totalEmployees) * 100, 1)
        ];
    }
    
    return [
        "status" => "success",
        "endpoint" => "weekly_registration",
        "period" => "{$startDate} to {$endDate}",
        "total_employees" => $totalEmployees,
        "data" => $chartData
    ];
}

/**
 * Get favorite meals (most ordered)
 */
function getFavoriteMeals($month) {
    global $conn;
    
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    // Get meal popularity
    $sql = "
        SELECT 
            dm.meal_name,
            dm.id as meal_id,
            COUNT(*) as order_count,
            COUNT(DISTINCT ma.user_id) as unique_users,
            COUNT(CASE WHEN ma.status = 'checked_in' THEN 1 END) as confirmed_count
        FROM daily_menu dm
        LEFT JOIN meal_attendance ma ON dm.id = ma.menu_id AND ma.meal_date BETWEEN ? AND ?
        WHERE dm.date BETWEEN ? AND ?
        GROUP BY dm.id, dm.meal_name
        ORDER BY order_count DESC
        LIMIT 15
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "status" => "success",
        "endpoint" => "favorite_meals",
        "month" => $month,
        "data" => $meals
    ];
}

/**
 * Get monthly cost trend (last 6 months)
 */
function getMonthlyCostTrend() {
    global $conn;
    
    $unitCost = 35000;
    $trend = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i month"));
        $mStart = $m . '-01';
        $mEnd = date('Y-m-t', strtotime($mStart));
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(CASE WHEN status IN ('registered', 'checked_in') THEN 1 END) as employee_meals,
                COALESCE(SUM(quantity), 0) as guest_meals
            FROM meal_attendance
            LEFT JOIN guest_bookings ON meal_attendance.user_id = guest_bookings.user_id 
                AND DATE(meal_attendance.meal_date) = DATE(guest_bookings.booking_date)
            WHERE meal_attendance.meal_date BETWEEN ? AND ?
        ");
        $stmt->execute([$mStart, $mEnd]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $employeeMeals = intval($result['employee_meals'] ?? 0);
        $totalMeals = $employeeMeals + intval($result['guest_meals'] ?? 0);
        $estimatedCost = $totalMeals * $unitCost;
        
        $trend[] = [
            'month' => $m,
            'employee_meals' => $employeeMeals,
            'total_meals' => $totalMeals,
            'estimated_cost' => $estimatedCost
        ];
    }
    
    return [
        "status" => "success",
        "endpoint" => "monthly_cost_trend",
        "unit_cost" => $unitCost,
        "data" => $trend
    ];
}

/**
 * Get department statistics
 */
function getDepartmentStatistics($month) {
    global $conn;
    
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    $sql = "
        SELECT 
            COALESCE(s.department, 'Không xác định') as department,
            COUNT(DISTINCT u.id) as total_employees,
            COUNT(ma.id) as total_meals,
            COUNT(CASE WHEN ma.status = 'checked_in' THEN 1 END) as checked_in,
            COUNT(CASE WHEN ma.status = 'no_show' THEN 1 END) as no_show,
            COUNT(CASE WHEN ma.status = 'cancelled' THEN 1 END) as cancelled,
            ROUND(COUNT(CASE WHEN ma.status = 'checked_in' THEN 1 END) / 
                  COUNT(ma.id) * 100, 1) as attendance_rate
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN meal_attendance ma ON u.id = ma.user_id AND ma.meal_date BETWEEN ? AND ?
        WHERE u.role = 'employee'
        GROUP BY s.department
        ORDER BY total_meals DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$monthStart, $monthEnd]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "status" => "success",
        "endpoint" => "department_statistics",
        "month" => $month,
        "data" => $departments
    ];
}

/**
 * Get daily attendance trend
 */
function getDailyAttendance($days) {
    global $conn;
    
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-$days days"));
    
    $sql = "
        SELECT 
            DATE(meal_date) as date,
            COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as checked_in,
            COUNT(CASE WHEN status = 'registered' THEN 1 END) as registered,
            COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
        FROM meal_attendance
        WHERE meal_date BETWEEN ? AND ?
        GROUP BY DATE(meal_date)
        ORDER BY date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "status" => "success",
        "endpoint" => "daily_attendance",
        "period" => "{$startDate} to {$endDate}",
        "data" => array_reverse($attendance)
    ];
}
?>
