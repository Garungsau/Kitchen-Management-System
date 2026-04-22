<?php
header('Content-Type: application/json; charset=utf-8');

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$allow = in_array($remote, ['127.0.0.1', '::1'], true);
if (!$allow) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Forbidden: localhost only'
    ]);
    exit();
}

$root = realpath(__DIR__ . '/..');
$targets = [
    'api/dashboard_init.php',
    'api/register_meal.php',
    'api/toggle_meal.php',
    'api/get_month_status.php',
    'api/get_realtime_alerts.php',
    'api/get_menu.php',
    'employee_dashboard.html'
];

$files = [];
foreach ($targets as $rel) {
    $abs = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($abs === false || strpos($abs, $root) !== 0 || !is_file($abs)) {
        continue;
    }

    $files[$rel] = [
        'sha256' => hash_file('sha256', $abs),
        'size' => filesize($abs),
        'mtime' => gmdate('c', filemtime($abs))
    ];
}

echo json_encode([
    'status' => 'success',
    'base_path' => $root,
    'files' => $files
]);
