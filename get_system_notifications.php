<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'kitchen_staff', 'admin']);

$userId = current_user_id();
$role = current_role();

try {
    $items = [];

    $now = new DateTime();
    $cutoff = new DateTime('tomorrow');
    $cutoff->setTime(8, 0, 0);
    $remain = max(0, $cutoff->getTimestamp() - $now->getTimestamp());
    $hh = str_pad((string) floor($remain / 3600), 2, '0', STR_PAD_LEFT);
    $mm = str_pad((string) floor(($remain % 3600) / 60), 2, '0', STR_PAD_LEFT);

    $items[] = [
        'type' => 'registration_deadline',
        'priority' => 'warning',
        'title' => 'Hạn đăng ký suất ăn',
        'message' => 'Con ' . $hh . ':' . $mm . ' đến hạn chốt 08:00 sáng ngày mai cho suất ăn ngày mai.'
    ];

    if ($role === 'employee') {
        $stmtNoShow = $conn->prepare("SELECT meal_date FROM meal_attendance WHERE user_id = ? AND status = 'no_show' ORDER BY meal_date DESC LIMIT 1");
        $stmtNoShow->execute([$userId]);
        $ns = $stmtNoShow->fetch(PDO::FETCH_ASSOC);
        if ($ns) {
            $items[] = [
                'type' => 'no_show',
                'priority' => 'danger',
                'title' => 'Cảnh báo no-show',
                'message' => 'Bạn có suất ăn no-show vào ngày ' . $ns['meal_date'] . '.'
            ];
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $stmtMenu = $conn->prepare("SELECT approval_status FROM daily_menu WHERE menu_date = ? LIMIT 1");
        $stmtMenu->execute([$tomorrow]);
        $menu = $stmtMenu->fetch(PDO::FETCH_ASSOC);
        if ($menu && $menu['approval_status'] === 'approved') {
            $items[] = [
                'type' => 'menu_approved',
                'priority' => 'success',
                'title' => 'Thực đơn đã được duyệt',
                'message' => 'Thực đơn ngày mai đã được duyệt và công bố cho nhân viên.'
            ];
        }
    }

    if (in_array($role, ['kitchen_staff', 'admin'])) {
        $stmtPending = $conn->query("SELECT COUNT(*) FROM daily_menu WHERE approval_status = 'pending_approval'");
        $pending = intval($stmtPending->fetchColumn());
        if ($pending > 0) {
            $items[] = [
                'type' => 'pending_menu_approval',
                'priority' => 'info',
                'title' => 'Menu chờ duyệt',
                'message' => 'Hiện có ' . $pending . ' thực đơn đang chờ duyệt.'
            ];
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS ingredients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            unit VARCHAR(20) NOT NULL DEFAULT 'kg',
            stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
            per_meal_norm DECIMAL(10,4) NOT NULL DEFAULT 0,
            low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmtLow = $conn->query("SELECT COUNT(*) FROM ingredients WHERE is_active = 1 AND stock_qty <= low_stock_threshold");
        $lowCount = intval($stmtLow->fetchColumn());
        if ($lowCount > 0) {
            $items[] = [
                'type' => 'low_stock',
                'priority' => 'danger',
                'title' => 'Cảnh báo thiếu hàng',
                'message' => 'Có ' . $lowCount . ' nguyên liệu đang dưới ngưỡng tồn kho.'
            ];
        }
    }

    echo json_encode(["status" => "success", "data" => $items]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>