<?php
/**
 * Monthly Meal Report Export (CSV/Excel)
 * Exports meal registration, attendance, and costs by employee/department
 */
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['admin']);

$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv'; // csv or xlsx
$groupBy = isset($_GET['group_by']) ? trim($_GET['group_by']) : 'employee'; // employee or department
$unitCost = isset($_GET['unit_cost']) ? floatval($_GET['unit_cost']) : 35000;

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid month format (YYYY-MM)"]);
    exit();
}

if (!in_array($format, ['csv', 'xlsx'], true)) {
    $format = 'csv';
}

$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

try {
    // Query: Meal attendance by employee
    $sql = "
        SELECT 
            u.id as user_id,
            s.full_name,
            s.student_id_no as employee_id,
            s.department,
            COUNT(CASE WHEN ma.status IN ('registered', 'checked_in') THEN 1 END) as meals_attended,
            COUNT(CASE WHEN ma.status = 'checked_in' THEN 1 END) as meals_confirmed,
            COUNT(CASE WHEN ma.status = 'no_show' THEN 1 END) as meals_absent,
            COUNT(CASE WHEN ma.status = 'cancelled' THEN 1 END) as meals_cancelled,
            COUNT(*) as total_registered
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN meal_attendance ma ON u.id = ma.user_id AND ma.meal_date BETWEEN ? AND ? AND ma.status IN ('registered', 'checked_in', 'no_show', 'cancelled')
        WHERE u.role = 'employee'
        GROUP BY u.id, s.full_name, s.student_id_no, s.department
        ORDER BY s.department, s.full_name
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$monthStart, $monthEnd]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build data structure
    if ($groupBy === 'department') {
        // Group by department
        $groupedData = [];
        foreach ($employees as $emp) {
            $dept = $emp['department'] ?: 'Chưa phân công';
            if (!isset($groupedData[$dept])) {
                $groupedData[$dept] = [
                    'department' => $dept,
                    'employees' => [],
                    'totals' => [
                        'meals_attended' => 0,
                        'meals_confirmed' => 0,
                        'meals_absent' => 0,
                        'meals_cancelled' => 0,
                        'total_estimated_cost' => 0
                    ]
                ];
            }
            $emp['estimated_cost'] = round($emp['meals_attended'] * $unitCost, 0);
            $groupedData[$dept]['employees'][] = $emp;
            $groupedData[$dept]['totals']['meals_attended'] += $emp['meals_attended'];
            $groupedData[$dept]['totals']['meals_confirmed'] += $emp['meals_confirmed'];
            $groupedData[$dept]['totals']['meals_absent'] += $emp['meals_absent'];
            $groupedData[$dept]['totals']['meals_cancelled'] += $emp['meals_cancelled'];
            $groupedData[$dept]['totals']['total_estimated_cost'] += $emp['estimated_cost'];
        }
        $reportData = $groupedData;
    } else {
        // Individual employee records
        foreach ($employees as &$emp) {
            $emp['estimated_cost'] = round($emp['meals_attended'] * $unitCost, 0);
        }
        $reportData = $employees;
    }

    // Generate CSV or XLSX
    if ($format === 'csv') {
        generateAndDownloadCSV($reportData, $month, $groupBy, $unitCost);
    } else {
        generateAndDownloadXLSX($reportData, $month, $groupBy, $unitCost);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}

/**
 * Generate and download CSV
 */
function generateAndDownloadCSV($data, $month, $groupBy, $unitCost) {
    $filename = "Báo_cáo_suất_ăn_{$month}_" . date('His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8
    fputs($output, "\xEF\xBB\xBF");

    // Header
    fprintf($output, "\"%s\",\"%s\",\"%s\",\"%s\"\n", 
        "BÁO CÁO SUẤT ĂN", $month, "Đơn giá", number_format($unitCost, 0));
    fprintf($output, "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
        "Ngày xuất", date('d/m/Y'), "Người lập", $_SESSION['user_name'] ?? 'Admin', "", "", "", "");

    fputs($output, "\n");

    if ($groupBy === 'department') {
        // Department grouped format
        fputs($output, "PHÒNG BAN,HỌ VÀ TÊN,MÃ NHÂN VIÊN,SUẤT ĂN TÍNH,SUẤT ĂN XÁC NHẬN,VẮNG MặT,HỦY,TỔNG CHI PHÍ (₫)\n");
        
        foreach ($data as $dept => $groupInfo) {
            foreach ($groupInfo['employees'] as $emp) {
                fprintf($output, "\"%s\",\"%s\",\"%s\",%d,%d,%d,%d,%d\n",
                    $groupInfo['department'],
                    $emp['full_name'],
                    $emp['employee_id'],
                    $emp['meals_attended'],
                    $emp['meals_confirmed'],
                    $emp['meals_absent'],
                    $emp['meals_cancelled'],
                    $emp['estimated_cost']
                );
            }
            // Department subtotal
            fprintf($output, "CỘNG %s,,,,%d,%d,%d,%d,%d\n",
                $groupInfo['department'],
                $groupInfo['totals']['meals_attended'],
                $groupInfo['totals']['meals_confirmed'],
                $groupInfo['totals']['meals_absent'],
                $groupInfo['totals']['meals_cancelled'],
                $groupInfo['totals']['total_estimated_cost']
            );
            fputs($output, "\n");
        }
    } else {
        // Individual employee format
        fputs($output, "HỌ VÀ TÊN,MÃ NHÂN VIÊN,PHÒNG BAN,SUẤT ĂN TÍNH,SUẤT ĂN XÁC NHẬN,VẮNG Mặt,HỦY,TỔNG CHI PHÍ (₫)\n");
        
        $totalRows = [
            'meals_attended' => 0,
            'meals_confirmed' => 0,
            'meals_absent' => 0,
            'meals_cancelled' => 0,
            'total_cost' => 0
        ];

        foreach ($data as $emp) {
            fprintf($output, "\"%s\",\"%s\",\"%s\",%d,%d,%d,%d,%d\n",
                $emp['full_name'],
                $emp['employee_id'],
                $emp['department'] ?: 'Chưa phân công',
                $emp['meals_attended'],
                $emp['meals_confirmed'],
                $emp['meals_absent'],
                $emp['meals_cancelled'],
                $emp['estimated_cost']
            );
            $totalRows['meals_attended'] += $emp['meals_attended'];
            $totalRows['meals_confirmed'] += $emp['meals_confirmed'];
            $totalRows['meals_absent'] += $emp['meals_absent'];
            $totalRows['meals_cancelled'] += $emp['meals_cancelled'];
            $totalRows['total_cost'] += $emp['estimated_cost'];
        }

        // Grand total
        fputs($output, "\n");
        fprintf($output, "\"TỔNG CỘNG\",,,,%d,%d,%d,%d,%d\n",
            $totalRows['meals_attended'],
            $totalRows['meals_confirmed'],
            $totalRows['meals_absent'],
            $totalRows['meals_cancelled'],
            $totalRows['total_cost']
        );
    }

    fclose($output);
    exit();
}

/**
 * Generate and download XLSX (simplified format using spreadsheet XML)
 */
function generateAndDownloadXLSX($data, $month, $groupBy, $unitCost) {
    // For now, fallback to CSV for XLSX
    // A full XLSX implementation would require creating ZIP with XML sheets
    generateAndDownloadCSV($data, $month, $groupBy, $unitCost);
}
?>
