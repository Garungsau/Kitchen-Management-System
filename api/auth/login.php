<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
start_session_if_needed();
header('Content-Type: application/json; charset=utf-8');

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip)) {
        return '';
    }
    $ip = trim($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '';
}

function ensure_login_attempts_table(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(255) NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
        INDEX idx_login_attempts_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function get_failed_attempts_in_window(PDO $conn, string $ip): int {
    if ($ip === '') {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= (NOW() - INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn();
}

function get_lock_remaining_seconds(PDO $conn, string $ip): int {
    if ($ip === '') {
        return 0;
    }
    $stmt = $conn->prepare("SELECT attempted_at FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= (NOW() - INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE) ORDER BY attempted_at DESC LIMIT 1");
    $stmt->execute([$ip]);
    $lastAttempt = $stmt->fetchColumn();
    if (!$lastAttempt) {
        return 0;
    }

    $lockUntil = strtotime((string)$lastAttempt . ' +' . LOGIN_WINDOW_MINUTES . ' minutes');
    $remaining = $lockUntil - time();
    return $remaining > 0 ? (int)$remaining : 0;
}

function record_login_attempt(PDO $conn, string $ip, string $email, int $success): void {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)");
    $stmt->execute([$ip, $email, $success]);
}

function clear_failed_attempts(PDO $conn, string $ip): void {
    if ($ip === '') {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0");
    $stmt->execute([$ip]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 'error', 405);
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    json_error('Email và mật khẩu là bắt buộc.', 'error', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Định dạng email không hợp lệ.', 'error', 400);
}

if (strlen($password) < 6) {
    json_error('Mật khẩu phải dài tối thiểu 6 ký tự.', 'error', 400);
}

try {
    ensure_login_attempts_table($conn);

    $clientIp = get_client_ip();
    if ($clientIp !== '') {
        $failedCount = get_failed_attempts_in_window($conn, $clientIp);
        if ($failedCount >= LOGIN_MAX_ATTEMPTS) {
            $remainingSeconds = get_lock_remaining_seconds($conn, $clientIp);
            $remainingMinutes = (int)max(1, ceil($remainingSeconds / 60));
            json_error("Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau {$remainingMinutes} phút.", 'error', 429);
        }
    }

    $stmt = $conn->prepare("SELECT id, password, role, is_approved, is_blocked FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        if (!empty($clientIp)) {
            record_login_attempt($conn, $clientIp, $email, 0);
        }
        json_error('Email hoặc mật khẩu không đúng.', 'error', 401);
    }

    if (!password_verify($password, $user['password'])) {
        if (!empty($clientIp)) {
            record_login_attempt($conn, $clientIp, $email, 0);
            $failedCount = get_failed_attempts_in_window($conn, $clientIp);
            if ($failedCount >= LOGIN_MAX_ATTEMPTS) {
                $remainingSeconds = get_lock_remaining_seconds($conn, $clientIp);
                $remainingMinutes = (int)max(1, ceil($remainingSeconds / 60));
                json_error("Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau {$remainingMinutes} phút.", 'error', 429);
            }
        }
        json_error('Email hoặc mật khẩu không đúng.', 'error', 401);
    }

    if (intval($user['is_blocked']) === 1) {
        json_error('Tài khoản đã bị khóa. Liên hệ quản trị.', 'error', 403);
    }

    if (intval($user['is_approved']) === 0) {
        json_error('Tài khoản đang chờ duyệt.', 'error', 403);
    }

    $rawRole = isset($user['role']) ? (string)$user['role'] : '';
    $normalizedRole = normalize_role($rawRole);
    if ($normalizedRole === null) {
        $normalizedRole = 'employee';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $normalizedRole;
    $_SESSION['logged_in'] = true;

    if ($normalizedRole === 'employee') {
        ensure_employee_profile_exists($conn, intval($user['id']), $email);
    }

    if (!empty($clientIp)) {
        record_login_attempt($conn, $clientIp, $email, 1);
        clear_failed_attempts($conn, $clientIp);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Đăng nhập thành công',
        'role' => $normalizedRole,
        'redirect_url' => $normalizedRole . '_dashboard.html'
    ]);
} catch (PDOException $e) {
    json_error('Database error.', 'error', 500);
}
?>
