<?php
/**
 * DIAGNOSTIC API: Check Employee Account Status
 * Purpose: Debug login issues for employee accounts
 */

require_once 'config.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : 'nhanvien1@cpc1.com';

try {
    echo "=== EMPLOYEE ACCOUNT DIAGNOSTIC ===\n\n";
    echo "Checking: " . htmlspecialchars($email) . "\n\n";
    
    // Check in users table
    $stmt = $conn->prepare("SELECT id, email, role, is_approved, is_blocked FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ ACCOUNT NOT FOUND in users table\n";
        // List all employees
        echo "\nAll employee accounts in database:\n";
        $all = $conn->query("SELECT id, email, role, is_approved FROM users WHERE role = 'employee' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all as $emp) {
            echo "  - " . $emp['email'] . " (ID: " . $emp['id'] . ", Role: " . $emp['role'] . ", Approved: " . $emp['is_approved'] . ")\n";
        }
        exit;
    }
    
    echo "✅ FOUND in users table\n";
    echo "   ID: " . $user['id'] . "\n";
    echo "   Email: " . $user['email'] . "\n";
    echo "   Role: " . $user['role'] . "\n";
    echo "   Approved: " . ($user['is_approved'] ? "YES" : "NO") . "\n";
    echo "   Blocked: " . ($user['is_blocked'] ? "YES" : "NO") . "\n\n";
    
    // Check in students table
    echo "Checking students table (employee profile):\n";
    $stmt = $conn->prepare("SELECT id, user_id, full_name, student_id_no FROM students WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo "✅ Employee profile exists\n";
        echo "   Name: " . $student['full_name'] . "\n";
        echo "   Employee ID: " . $student['student_id_no'] . "\n";
    } else {
        echo "❌ NO employee profile in students table\n";
    }
    
    // Check password hash
    echo "\nPassword Hash Status:\n";
    $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $pwd_hash = $stmt->fetchColumn();
    echo "   Hash exists: " . (empty($pwd_hash) ? "NO ❌" : "YES ✅") . "\n";
    
    // List all table columns to verify schema
    echo "\n=== DATABASE SCHEMA CHECK ===\n";
    $tables_to_check = ['users', 'students', 'daily_menu', 'meal_attendance'];
    foreach ($tables_to_check as $table) {
        $columns = $conn->query("SHOW COLUMNS FROM " . $table)->fetchAll(PDO::FETCH_ASSOC);
        echo "\nTable: " . $table . " (" . count($columns) . " columns)\n";
        if (count($columns) > 10) {
            echo "   Columns: " . implode(", ", array_map(fn($c) => $c['Field'], array_slice($columns, 0, 5))) . "...\n";
        }
    }
    
    echo "\n=== RECOMMENDATION ===\n";
    if (!$student) {
        echo "⚠️  Employee profile missing. Need to:\n";
        echo "   1. Manually create profile in students table\n";
        echo "   2. Or re-register employee account\n";
    } elseif (!$user['is_approved']) {
        echo "⚠️  Account not approved by admin. Admin must approve before login.\n";
    } elseif ($user['is_blocked']) {
        echo "⚠️  Account is blocked by admin.\n";
    } else {
        echo "✅ Account looks OK. Issue may be with password reset or session handling.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
