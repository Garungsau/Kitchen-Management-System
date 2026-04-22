<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

const OTP_MAX_VERIFY_ATTEMPTS = 5;

function ensure_reset_token_schema(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        reset_code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        failed_attempts INT NOT NULL DEFAULT 0,
        requested_ip VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_used (user_id, used),
        INDEX idx_email_used (email, used),
        INDEX idx_code_used (reset_code, used)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    $input = [];
}

$email = isset($input['email']) ? trim((string)$input['email']) : trim((string)($_POST['email'] ?? ''));
$resetCode = isset($input['reset_code']) ? trim((string)$input['reset_code']) : trim((string)($_POST['reset_code'] ?? ''));
$newPassword = isset($input['new_password']) ? (string)$input['new_password'] : (string)($_POST['new_password'] ?? '');

if ($email === '' || $resetCode === '' || $newPassword === '') {
    echo json_encode(['status' => 'error', 'message' => 'Email, mã xác nhận và mật khẩu không được để trống']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ']);
    exit;
}

if (!preg_match('/^\d{6}$/', $resetCode)) {
    echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận phải gồm 6 chữ số']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải ít nhất 8 ký tự']);
    exit;
}

try {
    ensure_reset_token_schema($conn);

    $stmt = $conn->prepare('SELECT id, user_id, email, expires_at, used, failed_attempts FROM password_reset_tokens WHERE email = ? AND reset_code = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $resetCode]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token) {
        $latestStmt = $conn->prepare('SELECT id, failed_attempts FROM password_reset_tokens WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $latestStmt->execute([$email]);
        $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
        if ($latest) {
            $nextFailed = ((int)($latest['failed_attempts'] ?? 0)) + 1;
            if ($nextFailed >= OTP_MAX_VERIFY_ATTEMPTS) {
                $lockStmt = $conn->prepare('UPDATE password_reset_tokens SET used = 1, failed_attempts = ? WHERE id = ?');
                $lockStmt->execute([$nextFailed, $latest['id']]);
                echo json_encode(['status' => 'error', 'message' => 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng yêu cầu mã mới.']);
                exit;
            }
            $incStmt = $conn->prepare('UPDATE password_reset_tokens SET failed_attempts = ? WHERE id = ?');
            $incStmt->execute([$nextFailed, $latest['id']]);
            $remaining = OTP_MAX_VERIFY_ATTEMPTS - $nextFailed;
            echo json_encode(['status' => 'error', 'message' => "Mã xác nhận không hợp lệ. Còn {$remaining} lần thử."]);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận không hợp lệ hoặc đã hết hạn']);
        exit;
    }

    if ((int)($token['failed_attempts'] ?? 0) >= OTP_MAX_VERIFY_ATTEMPTS) {
        echo json_encode(['status' => 'error', 'message' => 'Mã OTP đã bị khóa. Vui lòng yêu cầu mã mới.']);
        exit;
    }

    $expiresAt = new DateTime($token['expires_at']);
    if (new DateTime() > $expiresAt) {
        $expireStmt = $conn->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?');
        $expireStmt->execute([$token['id']]);
        echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận đã hết hạn. Vui lòng yêu cầu mã mới']);
        exit;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $token['user_id']]);

    $useStmt = $conn->prepare('UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0');
    $useStmt->execute([$token['user_id']]);

    echo json_encode(['status' => 'success', 'message' => 'Mật khẩu đã được đặt lại.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu']);
}
?>
