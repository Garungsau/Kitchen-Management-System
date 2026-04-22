<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function load_smtp_settings(): array {
    $settings = [
        'host' => trim((string)getenv('SMTP_HOST')),
        'port' => (int)(getenv('SMTP_PORT') ?: 0),
        'username' => trim((string)getenv('SMTP_USERNAME')),
        'password' => (string)getenv('SMTP_PASSWORD'),
        'encryption' => strtolower(trim((string)getenv('SMTP_ENCRYPTION'))),
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

    if ($settings['encryption'] === '') {
        $settings['encryption'] = 'none';
    }

    return $settings;
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return trim($response);
}

function smtp_expect($socket, array $okCodes, array &$steps, string $label): bool {
    $resp = smtp_read_response($socket);
    $steps[] = $label . ': ' . ($resp !== '' ? $resp : '[empty response]');
    if ($resp === '') {
        return false;
    }
    $code = (int)substr($resp, 0, 3);
    return in_array($code, $okCodes, true);
}

function smtp_cmd($socket, string $command, array $okCodes, array &$steps, string $label): bool {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $okCodes, $steps, $label);
}

function is_local_request(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return $ip === '127.0.0.1' || $ip === '::1';
}

if (!is_local_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint chi cho phep truy cap local.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Chi ho tro POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$toEmail = trim((string)($input['to_email'] ?? ''));

$smtp = load_smtp_settings();
if ($toEmail === '') {
    $toEmail = (string)($smtp['username'] ?? '');
}

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'to_email khong hop le.']);
    exit;
}

$host = (string)$smtp['host'];
$port = (int)$smtp['port'];
$username = (string)$smtp['username'];
$password = (string)$smtp['password'];
$encryption = (string)$smtp['encryption'];
$fromEmail = trim((string)$smtp['from_email']) !== '' ? (string)$smtp['from_email'] : $username;
$fromName = trim((string)$smtp['from_name']) !== '' ? (string)$smtp['from_name'] : 'CPC1 Meal System';

if ($host === '' || $port <= 0 || $username === '' || $password === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'SMTP chua du cau hinh trong api/smtp_config.php hoac env.',
        'config' => [
            'host' => $host,
            'port' => $port,
            'username_set' => $username !== '',
            'password_set' => $password !== '',
            'encryption' => $encryption,
        ]
    ]);
    exit;
}

$steps = [];
$timeout = 20;
$transportHost = ($encryption === 'ssl') ? ('ssl://' . $host) : $host;
$socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
if (!$socket) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Khong ket noi duoc SMTP server.',
        'steps' => $steps,
        'error' => $errno . ' ' . $errstr
    ]);
    exit;
}

stream_set_timeout($socket, $timeout);

if (!smtp_expect($socket, [220], $steps, 'Connect')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'SMTP greeting khong hop le.', 'steps' => $steps]);
    exit;
}

$localHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
if (!smtp_cmd($socket, 'EHLO ' . $localHost, [250], $steps, 'EHLO')) {
    if (!smtp_cmd($socket, 'HELO ' . $localHost, [250], $steps, 'HELO')) {
        fclose($socket);
        echo json_encode(['status' => 'error', 'message' => 'EHLO/HELO that bai.', 'steps' => $steps]);
        exit;
    }
}

if ($encryption === 'tls') {
    if (!smtp_cmd($socket, 'STARTTLS', [220], $steps, 'STARTTLS')) {
        fclose($socket);
        echo json_encode(['status' => 'error', 'message' => 'STARTTLS that bai.', 'steps' => $steps]);
        exit;
    }

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket);
        echo json_encode(['status' => 'error', 'message' => 'Bat TLS crypto that bai.', 'steps' => $steps]);
        exit;
    }

    if (!smtp_cmd($socket, 'EHLO ' . $localHost, [250], $steps, 'EHLO after TLS')) {
        fclose($socket);
        echo json_encode(['status' => 'error', 'message' => 'EHLO sau TLS that bai.', 'steps' => $steps]);
        exit;
    }
}

if (!smtp_cmd($socket, 'AUTH LOGIN', [334], $steps, 'AUTH LOGIN')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'Lenh AUTH LOGIN that bai.', 'steps' => $steps]);
    exit;
}
if (!smtp_cmd($socket, base64_encode($username), [334], $steps, 'SMTP USER')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'SMTP username khong hop le.', 'steps' => $steps]);
    exit;
}
if (!smtp_cmd($socket, base64_encode($password), [235], $steps, 'SMTP PASS')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'SMTP password that bai.', 'steps' => $steps]);
    exit;
}

if (!smtp_cmd($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $steps, 'MAIL FROM')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'MAIL FROM bi tu choi.', 'steps' => $steps]);
    exit;
}
if (!smtp_cmd($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], $steps, 'RCPT TO')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'RCPT TO bi tu choi.', 'steps' => $steps]);
    exit;
}
if (!smtp_cmd($socket, 'DATA', [354], $steps, 'DATA')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'DATA step that bai.', 'steps' => $steps]);
    exit;
}

$subject = 'SMTP Test CPC1 ' . date('Y-m-d H:i:s');
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$safeFromName = preg_replace('/[\r\n]+/', ' ', $fromName);
$body = '<html><body><p>SMTP test OK from CPC1.</p></body></html>';
$headers = [];
$headers[] = 'Date: ' . date('r');
$headers[] = 'From: ' . $safeFromName . ' <' . $fromEmail . '>';
$headers[] = 'To: <' . $toEmail . '>';
$headers[] = 'Subject: ' . $encodedSubject;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$messageData = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
fwrite($socket, $messageData . "\r\n");

if (!smtp_expect($socket, [250], $steps, 'SEND BODY')) {
    fclose($socket);
    echo json_encode(['status' => 'error', 'message' => 'Gui noi dung email that bai.', 'steps' => $steps]);
    exit;
}

smtp_cmd($socket, 'QUIT', [221], $steps, 'QUIT');
fclose($socket);

echo json_encode([
    'status' => 'success',
    'message' => 'SMTP test gui thanh cong.',
    'steps' => $steps,
    'meta' => [
        'to_email' => $toEmail,
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
    ]
]);
?>