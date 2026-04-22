<?php
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['employee']);

$user_id = intval($_SESSION['user_id']);
$today_date = date('Y-m-d');
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
$today_weekday = intval(date('w'));
$month = date('Y-m');
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

try {
    ensure_employee_profile_exists($conn, $user_id);

    $hasAttendanceStatusColumn = true;
    try {
        $conn->query("SELECT status FROM meal_attendance LIMIT 1");
    } catch (Exception $schemaEx) {
        $hasAttendanceStatusColumn = false;
    }

    $stmt = $conn->prepare("SELECT s.*, u.email, u.is_blocked
                            FROM students s
                            JOIN users u ON s.user_id = u.id
                            WHERE s.user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $stmt = $conn->prepare("SELECT email, is_blocked FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $student = [
            'user_id' => $user_id,
            'email' => $u['email'] ?? '',
            'is_blocked' => intval($u['is_blocked'] ?? 0),
            'full_name' => '',
            'student_id_no' => '',
            'registration_no' => '',
            'department' => '',
            'hall_name' => '',
            'father_name' => '',
            'mother_name' => '',
            'phone' => '',
            'present_address' => '',
            'permanent_address' => '',
            'dob' => null,
            'blood_group' => '',
            'gender' => '',
            'nid_no' => '',
            'birth_certificate_no' => '',
            'photo_path' => null,
            'id_card_path' => null,
            'wallet_balance' => 0,
            'employee_type' => 'production',
            'subsidy_rate' => 0
        ];
    }

    if ($hasAttendanceStatusColumn) {
        $stmt = $conn->prepare("SELECT is_active, status FROM meal_attendance WHERE user_id = ? AND meal_date = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT is_active FROM meal_attendance WHERE user_id = ? AND meal_date = ? LIMIT 1");
    }
    $stmt->execute([$user_id, $today_date]);
    $today_row = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->execute([$user_id, $tomorrow_date]);
    $tomorrow_row = $stmt->fetch(PDO::FETCH_ASSOC);

    $meal_today = $today_row ? intval($today_row['is_active']) : 0;
    $meal_tomorrow = $tomorrow_row ? intval($tomorrow_row['is_active']) : 0;

    try {
        $menu = null;

        // Employee flow: only approved menu is visible.
        $stmt = $conn->prepare("SELECT lunch, dinner, approval_status
                                FROM daily_menu
                                WHERE menu_date = ? AND approval_status = 'approved'
                                ORDER BY id DESC
                                LIMIT 1");
        $stmt->execute([$tomorrow_date]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$menu) {
            $menu = [
                'lunch' => '',
                'dinner' => '',
                'approval_status' => 'none'
            ];
        }
    } catch (Exception $menuEx) {
        // Backward compatibility: older schema may not have approval_status.
        $stmt = $conn->prepare("SELECT lunch, dinner FROM daily_menu WHERE menu_date = ? LIMIT 1");
        $stmt->execute([$tomorrow_date]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$menu) {
            $menu = [
                'lunch' => '',
                'dinner' => '',
                'approval_status' => 'draft'
            ];
        } else {
            $menu['approval_status'] = 'approved';
        }
    }

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

    $month_stats = [
        'registered' => 0,
        'checked_in' => 0,
        'no_show' => 0,
        'cancelled' => 0
    ];

    if ($hasAttendanceStatusColumn) {
        $stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date BETWEEN ? AND ?
                                GROUP BY status");
        $stmt->execute([$user_id, $month_start, $month_end]);
        $stat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stat_rows as $row) {
            $key = strtolower($row['status']);
            if (array_key_exists($key, $month_stats)) {
                $month_stats[$key] = intval($row['cnt']);
            }
        }
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date BETWEEN ? AND ? AND is_active = 1");
        $stmt->execute([$user_id, $month_start, $month_end]);
        $month_stats['registered'] = intval($stmt->fetchColumn() ?: 0);

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date BETWEEN ? AND ? AND is_active = 0");
        $stmt->execute([$user_id, $month_start, $month_end]);
        $month_stats['cancelled'] = intval($stmt->fetchColumn() ?: 0);
    }

    if ($hasAttendanceStatusColumn) {
        $stmt = $conn->prepare("SELECT meal_date, is_active, status
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date BETWEEN ? AND ?");
    } else {
        $stmt = $conn->prepare("SELECT meal_date, is_active
                                FROM meal_attendance
                                WHERE user_id = ? AND meal_date BETWEEN ? AND ?");
    }
    $stmt->execute([$user_id, $month_start, $month_end]);
    $month_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $calendar = [];
    $details = [];
    foreach ($month_rows as $row) {
        $calendar[$row['meal_date']] = intval($row['is_active']);
        $details[$row['meal_date']] = [
            'is_active' => intval($row['is_active']),
            'status' => $hasAttendanceStatusColumn
                ? (($row['status'] ?? '') ?: (intval($row['is_active']) === 1 ? 'registered' : 'cancelled'))
                : (intval($row['is_active']) === 1 ? 'registered' : 'cancelled')
        ];
    }

    $system_notifications = [];
    try {
        $stmt = $conn->prepare("SELECT title, message
                                FROM system_notifications
                                WHERE (target_role = 'all' OR target_role = 'employee')
                                ORDER BY created_at DESC
                                LIMIT 5");
        $stmt->execute();
        $system_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ignore) {
        $system_notifications = [];
    }

    $notices = [];
    try {
        $stmt = $conn->prepare("SELECT title, message
                                FROM notices
                                ORDER BY created_at DESC
                                LIMIT 5");
        $stmt->execute();
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ignore) {
        $notices = [];
    }

    // Fixed cutoff at 08:00 for tomorrow's meal
    $cutoff_hour = 8;
    $cutoff_minute = 0;

    echo json_encode([
        'status' => 'success',
        'dates' => [
            'today' => $today_date,
            'tomorrow' => $tomorrow_date,
            'month' => $month
        ],
        'profile' => $student,
        'meal_today' => $meal_today,
        'meal_tomorrow' => $meal_tomorrow,
        'meal_tomorrow_status' => $hasAttendanceStatusColumn
            ? ($tomorrow_row['status'] ?? 'cancelled')
            : ((intval($meal_tomorrow) === 1) ? 'registered' : 'cancelled'),
        'menu_tomorrow' => $menu,
        'guest_bookings' => $guest_bookings,
        'month_stats' => $month_stats,
        'month_status' => [
            'data' => $calendar,
            'details' => $details
        ],
        'cutoff' => [
            'hour' => $cutoff_hour,
            'minute' => $cutoff_minute
        ],
        'system_notifications' => $system_notifications,
        'notices' => $notices
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>