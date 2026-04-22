<?php
/**
 * API Endpoints Test & Diagnostic
 */

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

echo "{\n";
echo "  \"test_status\": \"running\",\n";
echo "  \"timestamp\": \"" . date('Y-m-d H:i:s') . "\",\n";

// Test 1: Database connection
echo "  \"database\": {\n";
try {
    require_once __DIR__ . '/config.php';
    echo "    \"status\": \"✅ Connected\",\n";
    echo "    \"host\": \"" . DB_HOST . "\",\n";
    echo "    \"database\": \"" . DB_NAME . "\"\n";
    echo "  },\n";
} catch (Exception $e) {
    echo "    \"status\": \"❌ Failed\",\n";
    echo "    \"error\": \"" . $e->getMessage() . "\"\n";
    echo "  },\n";
}

// Test 2: Check session
echo "  \"session\": {\n";
start_session_if_needed();
if (isset($_SESSION['user_id'])) {
    echo "    \"status\": \"✅ Active\",\n";
    echo "    \"user_id\": " . $_SESSION['user_id'] . ",\n";
    echo "    \"email\": \"" . ($_SESSION['email'] ?? 'N/A') . "\",\n";
    echo "    \"role\": \"" . ($_SESSION['role'] ?? 'N/A') . "\"\n";
} else {
    echo "    \"status\": \"⏳ No session\",\n";
    echo "    \"message\": \"Not logged in - try login first\"\n";
}
echo "  },\n";

// Test 3: Check critical API files
echo "  \"api_files\": {\n";
$api_files = [
    'get_daily_meals.php',
    'get_profile.php',
    'get_transactions.php',
    'admin_stats.php',
    'get_menu.php',
    'toggle_meal.php'
];

foreach ($api_files as $i => $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path) ? "✅ Found" : "❌ Missing";
    echo "    \"$file\": \"$exists\"";
    echo ($i < count($api_files) - 1 ? ",\n" : "\n");
}
echo "  },\n";

// Test 4: Test get_daily_meals.php
echo "  \"get_daily_meals_test\": {\n";
try {
    $result = file_get_contents(__DIR__ . '/get_daily_meals.php');
    if (strpos($result, 'SELECT') !== false) {
        echo "    \"status\": \"✅ Valid\",\n";
        echo "    \"has_sql\": true\n";
    } else {
        echo "    \"status\": \"⚠️ Check content\"\n";
    }
} catch (Exception $e) {
    echo "    \"status\": \"❌ Error\",\n";
    echo "    \"error\": \"" . $e->getMessage() . "\"\n";
}
echo "  },\n";

// Test 5: Check table data
echo "  \"tables\": {\n";
try {
    $tables = [
        'daily_menu' => ['count' => 'SELECT COUNT(*) FROM daily_menu'],
        'users' => ['count' => 'SELECT COUNT(*) FROM users WHERE role != "student"'],
        'kitchen_staff' => ['count' => 'SELECT COUNT(*) FROM kitchen_staff']
    ];
    
    foreach ($tables as $table => $query_arr) {
        $stmt = $conn->prepare($query_arr['count']);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "    \"$table\": " . $count;
        echo (next($tables) ? ",\n" : "\n");
    }
} catch (Exception $e) {
    echo "    \"error\": \"" . $e->getMessage() . "\"\n";
}
echo "  },\n";

// Test 6: Check config.php syntax
echo "  \"config_check\": {\n";
$config_path = __DIR__ . '/config.php';
$config_content = file_get_contents($config_path);
if (preg_match('/define.*DB_HOST/', $config_content) && 
    preg_match('/define.*DB_USER/', $config_content) &&
    preg_match('/define.*DB_PASSWORD/', $config_content) &&
    preg_match('/define.*DB_NAME/', $config_content)) {
    echo "    \"status\": \"✅ Valid\",\n";
    echo "    \"has_all_defines\": true\n";
} else {
    echo "    \"status\": \"❌ Missing constants\",\n";
    echo "    \"has_all_defines\": false\n";
}
echo "  }\n";

echo "}\n";
?>
