<?php
/**
 * Dashboard Data Fetch Diagnostic
 */

require_once __DIR__ . '/auth.php';
start_session_if_needed();

echo "====================================\n";
echo "DASHBOARD FETCH DIAGNOSTIC\n";
echo "====================================\n\n";

// Check 1: Authentication
echo "1️⃣  SESSION CHECK\n";
echo str_repeat("-", 40) . "\n";
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in\n";
    echo "   User ID: " . $_SESSION['user_id'] . "\n";
    echo "   Email: " . ($_SESSION['email'] ?? 'Unknown') . "\n";
    echo "   Role: " . ($_SESSION['role'] ?? 'Unknown') . "\n";
} else {
    echo "❌ NO SESSION - Not logged in!\n";
    echo "   ⚠️  You must login first\n";
    echo "   Try: http://localhost/Smart-Meal-Management-System-main/auth/login.html\n";
}

echo "\n\n2️⃣  DATABASE CONNECTION\n";
echo str_repeat("-", 40) . "\n";

try {
    // Suppress headers
    ob_start();
    require_once __DIR__ . '/config.php';
    ob_end_clean();
    
    $test_query = $conn->query("SELECT COUNT(*) FROM users");
    $count = $test_query->fetchColumn();
    echo "✅ Database Connected\n";
    echo "   Total users: $count\n";
    
    // Check daily menu
    $menu_query = $conn->query("SELECT COUNT(*) FROM daily_menu WHERE menu_date >= CURDATE()");
    $menu_count = $menu_query->fetchColumn();
    echo "   Today/future menus: $menu_count\n";
    
} catch (Exception $e) {
    echo "❌ Database Error\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n\n3️⃣  REQUIRED API FILES\n";
echo str_repeat("-", 40) . "\n";

$required_apis = [
    'get_profile.php' => 'Get user profile',
    'get_menu.php' => 'Get daily menu',
    'toggle_meal.php' => 'Toggle meal registration',
    'get_transactions.php' => 'Get transactions',
    'admin_stats.php' => 'Admin stats'
];

foreach ($required_apis as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $file\n";
        echo "   $desc\n";
    } else {
        echo "❌ $file - MISSING!\n";
    }
}

echo "\n\n4️⃣  COMMON ISSUES\n";
echo str_repeat("-", 40) . "\n";

$issues = [];

// Check if session exists
if (!isset($_SESSION['user_id'])) {
    $issues[] = "❌ NOT LOGGED IN - Login first!";
}

// Check database
try {
    ob_start();
    require_once __DIR__ . '/config.php';
    ob_end_clean();
} catch (Exception $e) {
    $issues[] = "❌ Database connection failed";
}

// Check if config has errors
$config_code = file_get_contents(__DIR__ . '/config.php');
if (strpos($config_code, 'PDO') === false) {
    $issues[] = "⚠️  config.php might not have PDO";
}

if (count($issues) === 0) {
    echo "✅ No obvious issues found\n";
    echo "   Try opening browser console (F12) for errors\n";
} else {
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
}

echo "\n\n5️⃣  SOLUTION STEPS\n";
echo str_repeat("-", 40) . "\n";
echo "1. Make sure you're logged in\n";
echo "2. Open browser console (F12 > Console)\n";
echo "3. Check for JavaScript errors\n";
echo "4. Check Network tab to see API responses\n";
echo "5. Verify API returns JSON (not HTML errors)\n";

echo "\n✅ Diagnostic complete\n\n";
?>
