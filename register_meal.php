<?php
/**
 * DEPRECATED API - Please use toggle_meal.php instead
 * 
 * This endpoint has been consolidated for consistency and security.
 * Last updated: 2026-03-24
 */
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['employee', 'admin']);
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    "status" => "error",
    "message" => "API endpoint deprecated. Use api/toggle_meal.php instead.",
    "instruction" => "POST to api/toggle_meal.php with { status: 1, date: 'YYYY-MM-DD' } to register meal. Use status: 0 to cancel.",
    "deprecation_date" => "2026-03-24"
]);
exit();
