<?php
// Configure session to work across entire domain
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Only set CORS headers if making real cross-origin requests
// For now, rely on same-origin requests
// header("Access-Control-Allow-Origin: *");

header("Content-Type: application/json; charset=UTF-8");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

define('MEAL_COST', 60000); // ₫60,000 per meal

$host = getenv('DB_HOST') ?: "localhost";
$db_name = getenv('DB_NAME') ?: "meal_db";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";

// Keep constants for backward compatibility with endpoints that still rely on them.
if (!defined('DB_HOST')) define('DB_HOST', $host);
if (!defined('DB_NAME')) define('DB_NAME', $db_name);
if (!defined('DB_USER')) define('DB_USER', $username);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $password);

try {
    $dsnCandidates = [
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4"
    ];

    if ($db_name !== 'meal_db') {
        $dsnCandidates[] = "mysql:host=$host;dbname=meal_db;charset=utf8mb4";
    }

    $lastException = null;
    foreach ($dsnCandidates as $dsn) {
        try {
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            break;
        } catch (PDOException $ex) {
            $lastException = $ex;
        }
    }

    if (!isset($conn) || !$conn) {
        throw $lastException ?: new PDOException('Unknown database connection error');
    }
} catch(PDOException $exception) {
    echo json_encode(["status" => "error", "message" => "Connection error: " . $exception->getMessage()]);
    exit();
}

/**
 * Helper: Validate required input fields
 * @param array $data - Input data array
 * @param array $required_fields - Array of field names that must be present
 * @throws Exception if any required field is missing or empty
 */
function validateInput($data, $required_fields) {
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            throw new Exception("Missing or empty required field: $field");
        }
    }
}

/**
 * Helper: Log errors to file
 * @param string $message - Error message
 * @param array $context - Additional context data
 * @return bool - True if logged successfully
 */
function logError($message, $context = []) {
    $log_dir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_line = "[$timestamp] $message$context_str\n";
    
    return error_log($log_line, 3, $log_file);
}

/**
 * Helper: Send JSON response and exit
 * @param string $status - 'success' or 'error'
 * @param string $message - Message text
 * @param array $data - Additional data to include in response
 */
function sendResponse($status, $message, $data = []) {
    $response = [
        "status" => $status,
        "message" => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Helper: Safe database query execution with error handling
 * @param PDO $conn - Database connection
 * @param string $sql - SQL query
 * @param array $params - Parameters for prepared statement
 * @return PDOStatement
 * @throws Exception on query error
 */
function executeQuery($conn, $sql, $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        logError("Database query failed", [
            "sql" => $sql,
            "error" => $e->getMessage(),
            "code" => $e->getCode()
        ]);
        throw new Exception("Database operation failed. Please try again.");
    }
}