<?php
/**
 * DEBUG - Kiểm tra timezone và giờ server
 */
header('Content-Type: application/json; charset=utf-8');

// Get server timezone
$serverTZ = date_default_timezone_get();
$now = new DateTime();
$today = $now->format('Y-m-d');
$tomorrowDate = (new DateTime('+1 day'))->format('Y-m-d');
$nowTime = $now->format('Y-m-d H:i:s');

// Fixed cutoff at 08:00
$cutoffTimeStr = '08:00';

$cutoffTime = (new DateTime($tomorrowDate . ' ' . $cutoffTimeStr . ':00'))->format('Y-m-d H:i:s');

// Calculate diff
$diff = (new DateTime($cutoffTime))->getTimestamp() - $now->getTimestamp();
$diffHours = floor($diff / 3600);
$diffMins = floor(($diff % 3600) / 60);

echo json_encode([
    'status' => 'success',
    'server_timezone' => $serverTZ,
    'server_now' => $nowTime,
    'server_today' => $today,
    'server_tomorrow' => $tomorrowDate,
    'cutoff_time' => $cutoffTime,
    'diff_seconds' => $diff,
    'diff_hours' => $diffHours,
    'diff_display' => "$diffHours h $diffMins m",
    'php_ini_timezone' => ini_get('date.timezone')
], JSON_UNESCAPED_UNICODE);
?>
