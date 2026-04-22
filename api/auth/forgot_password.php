<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

const OTP_TTL_MINUTES = 15;
const OTP_RESEND_COOLDOWN_SECONDS = 60;

function table_column_exists(PDO $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function load_smtp_settings(): array {
    $settings = [
        'host' => trim((string)getenv('SMTP_HOST')),
        'port' => (int)(getenv('SMTP_PORT') ?: 0),
        'username' => trim((string)getenv('SMTP_USERNAME')),
        'password' => (string)getenv('SMTP_PASSWORD'),
        'encryption' => strtolower(trim((string)getenv('SMTP_ENCRYPTION'))), // ssl | tls | none
        'from_email' => trim((string)getenv('SMTP_FROM_EMAIL')),
        'from_name' => trim((string)getenv('SMTP_FROM_NAME')),
    ];

    $configPath = dirname(__DIR__) . '/smtp_config.php';
    if (is_file($configPath)) {
        $fileSettings = require $configPath;
        if (is_array($fileSettings)) {
            $settings['host'] = $settings['host'] !== '' ? $settings['host'] : trim((string)($fileSettings['host'] ?? ''));
            $settings['port'] = $settings['port'] > 0 ? $settings['port'] : (int)($fileSettings['port'] ?? 0);
            $settings['username'] = $settings['username'] !== '' ? $settings['username'] : trim((string)($fileSettings['username'] ?? ''));
            $settings['password'] = $settings['password'] !== '' ? $settings['password'] : (string)($fileSettings['password'] ?? '');
            $settings['encryption'] = $settings['encryption'] !== '' ? $settings['encryption'] : strtolower(trim((string)($fileSettings['encryption'] ?? '')));
            $settings['from_email'] = $settings['from_email'] !== '' ? $settings['from_email'] : trim((string)($fileSettings['from_email'] ?? ''));
            $settings['from_name'] = $settings['from_name'] !== '' ? $settings['from_name'] : trim((string)($fileSettings['from_name'] ?? ''));
        }
    }

    return $settings;
}

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

    if (!table_column_exists($conn, 'password_reset_tokens', 'failed_attempts')) {
        $conn->exec("ALTER TABLE password_reset_tokens ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0 AFTER used");
    }
    if (!table_column_exists($conn, 'password_reset_tokens', 'requested_ip')) {
        $conn->exec("ALTER TABLE password_reset_tokens ADD COLUMN requested_ip VARCHAR(45) NULL AFTER failed_attempts");
    }
}

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = is_string($ip) ? trim($ip) : '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function read_email_input(): string {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    if (is_array($json) && isset($json['email'])) {
        return trim((string)$json['email']);
    }
    if (isset($_POST['email'])) {
        return trim((string)$_POST['email']);
    }
    return '';
}

function send_reset_mail(string $email, string $subject, string $html, string $fromName, string $fromEmail): bool {
    $smtp = load_smtp_settings();
    $smtpHost = $smtp['host'];
    $smtpPort = (int)$smtp['port'];
    $smtpUser = $smtp['username'];
    $smtpPass = $smtp['password'];
    $smtpEncryption = $smtp['encryption'];

    if ($smtpHost !== '' && $smtpPort > 0 && $smtpUser !== '' && $smtpPass !== '') {
        return send_via_smtp(
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $smtpEncryption,
            $fromEmail,
            $fromName,
            $email,
            $subject,
            $html
        );
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    return (bool)@mail($email, $subject, $html, $headers);
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        // SMTP multiline responses have '-' at position 4. End line has space.
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_expect($socket, array $okCodes): bool {
    $resp = smtp_read_response($socket);
    if ($resp === '') {
        return false;
    }
    $code = (int)substr($resp, 0, 3);
    return in_array($code, $okCodes, true);
}

function smtp_cmd($socket, string $command, array $okCodes): bool {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $okCodes);
}

function send_via_smtp(
    string $host,
    int $port,
    string $username,
    string $password,
    string $encryption,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $htmlBody
): bool {
    $timeout = 20;
    $transportHost = ($encryption === 'ssl') ? ('ssl://' . $host) : $host;
    $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("SMTP connect failed: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    if (!smtp_expect($socket, [220])) {
        fclose($socket);
        return false;
    }

    $localHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    if (!smtp_cmd($socket, 'EHLO ' . $localHost, [250])) {
        if (!smtp_cmd($socket, 'HELO ' . $localHost, [250])) {
            fclose($socket);
            return false;
        }
    }

    if ($encryption === 'tls') {
        if (!smtp_cmd($socket, 'STARTTLS', [220])) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        if (!smtp_cmd($socket, 'EHLO ' . $localHost, [250])) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_cmd($socket, 'AUTH LOGIN', [334])) {
        fclose($socket);
        return false;
    }
    if (!smtp_cmd($socket, base64_encode($username), [334])) {
        fclose($socket);
        return false;
    }
    if (!smtp_cmd($socket, base64_encode($password), [235])) {
        fclose($socket);
        return false;
    }

    if (!smtp_cmd($socket, 'MAIL FROM:<' . $fromEmail . '>', [250])) {
        fclose($socket);
        return false;
    }
    if (!smtp_cmd($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251])) {
        fclose($socket);
        return false;
    }
    if (!smtp_cmd($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $safeFromName = preg_replace('/[\r\n]+/', ' ', $fromName);
    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . $safeFromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $encodedSubject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
    fwrite($socket, $data . "\r\n");

    if (!smtp_expect($socket, [250])) {
        fclose($socket);
        return false;
    }

    smtp_cmd($socket, 'QUIT', [221]);
    fclose($socket);
    return true;
}

function is_local_environment(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1')
        || str_starts_with($host, '::1');
}

$email = read_email_input();

$smtp = load_smtp_settings();

$appName = 'He thong quan ly bep an CPC1';
$fromEmail = trim((string)($smtp['from_email'] ?? '')) ?: 'no-reply@cpc1.local';
$fromName = trim((string)($smtp['from_name'] ?? '')) ?: 'CPC1 Meal System';
$baseUrl = 'http://localhost/Smart-Meal-Management-System-main';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Email khong hop le.']);
    exit;
}

try {
    ensure_reset_token_schema($conn);

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $subject = 'Huong dan dat lai mat khau - ' . $appName;
        $html = "<html><body><p>Neu email ton tai trong he thong, ma dat lai mat khau se duoc gui.</p></body></html>";
        send_reset_mail($email, $subject, $html, $fromName, $fromEmail);

        echo json_encode(['status' => 'success', 'message' => 'Neu email ton tai, ma dat lai se duoc gui.']);
        exit;
    }

    // Resend cooldown: avoid spamming OTP requests.
    $activeStmt = $conn->prepare('SELECT id, created_at FROM password_reset_tokens WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $activeStmt->execute([$user['id']]);
    $active = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if ($active && isset($active['created_at'])) {
        $elapsed = time() - strtotime((string)$active['created_at']);
        if ($elapsed < OTP_RESEND_COOLDOWN_SECONDS) {
            $wait = OTP_RESEND_COOLDOWN_SECONDS - $elapsed;
            echo json_encode([
                'status' => 'error',
                'message' => "Vui long cho {$wait} giay roi thu gui lai OTP."
            ]);
            exit;
        }
    }

    $resetCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_TTL_MINUTES . ' minutes'));
    $requestIp = get_client_ip();

    $delStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0');
    $delStmt->execute([$user['id']]);

    $insertStmt = $conn->prepare('INSERT INTO password_reset_tokens (user_id, email, reset_code, expires_at, failed_attempts, requested_ip) VALUES (?, ?, ?, ?, 0, ?)');
    $insertStmt->execute([$user['id'], $email, $resetCode, $expiresAt, $requestIp]);

    $resetLink = $baseUrl . '/auth/verify_reset_code.html?code=' . urlencode($resetCode) . '&email=' . urlencode($email);

    $subject = 'Ma dat lai mat khau - ' . $appName;
    $htmlMessage = "<html><body style='font-family:Arial,sans-serif;'>"
        . "<h3>Dat lai mat khau</h3>"
        . "<p>Ban vua yeu cau dat lai mat khau cho tai khoan {$email}.</p>"
        . "<p>Ma xac nhan: <strong style='font-size:22px;letter-spacing:4px;'>{$resetCode}</strong></p>"
        . "<p><a href='{$resetLink}'>Nhan vao day de tiep tuc</a></p>"
        . "<p>Ma co hieu luc trong " . OTP_TTL_MINUTES . " phut.</p>"
        . "</body></html>";

    $mailSent = send_reset_mail($email, $subject, $htmlMessage, $fromName, $fromEmail);

    error_log("Reset code for {$email}: {$resetCode} (expires: {$expiresAt})");

    if (!$mailSent) {
        // Do not expose OTP to client. Revoke token if email delivery failed.
        $revokeStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0');
        $revokeStmt->execute([$user['id']]);

        error_log("Failed to send reset OTP email to {$email}. Token revoked.");
        $message = 'He thong gui email dang tam thoi loi. Vui long thu lai sau.';
        if (is_local_environment()) {
            $message = 'Chua cau hinh SMTP hoac sai thong tin SMTP. Vui long cap nhat api/smtp_config.php va khoi dong lai Apache.';
        }
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Neu email ton tai, ma dat lai se duoc gui.'
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Loi he thong khi gui ma dat lai.']);
}
?>

