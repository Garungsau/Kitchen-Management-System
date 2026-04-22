<?php
/**
 * BACKUP & CLEANUP SCRIPT
 * Backs up data, removes students data, cleans up student-specific files
 */

require_once __DIR__ . '/config.php';

echo "🔄 CONVERTING TO COMPANY KITCHEN MANAGEMENT SYSTEM\n";
echo str_repeat("=", 80) . "\n\n";

// STEP 1: Backup database
echo "STEP 1: Backing up database...\n";
$backup_file = __DIR__ . '/../backup_student_data_' . date('Y-m-d_H-i-s') . '.sql';

try {
    // Export users table (students only)
    $stmt = $conn->prepare("SELECT * FROM students");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Save backup JSON
    $backup = [
        "timestamp" => date('Y-m-d H:i:s'),
        "students" => $students,
        "record_count" => count($students)
    ];
    
    file_put_contents(str_replace('.sql', '.json', $backup_file), 
        json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "✅ Backed up " . count($students) . " student records\n";
    echo "   Location: backup_student_data_*.json\n";
} catch (Exception $e) {
    echo "⚠️  Backup error: " . $e->getMessage() . "\n";
}

echo "\n";

// STEP 2: Delete student-related data
echo "STEP 2: Deleting student-related data from database...\n";

try {
    // Get student user IDs first
    $stmt = $conn->prepare("SELECT user_id FROM students");
    $stmt->execute();
    $student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($student_ids)) {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        // Delete related data in order
        $tables_to_clean = [
            "DELETE FROM meal_registration_history WHERE user_id IN ($placeholders)",
            "DELETE FROM transactions WHERE user_id IN ($placeholders)",
            "DELETE FROM meal_attendance WHERE user_id IN ($placeholders)",
            "DELETE FROM meal_complaints WHERE user_id IN ($placeholders)",
            "DELETE FROM students WHERE user_id IN ($placeholders)",
            "DELETE FROM users WHERE id IN ($placeholders) AND role = 'student'"
        ];
        
        foreach ($tables_to_clean as $query) {
            $stmt = $conn->prepare($query);
            $stmt->execute($student_ids);
        }
        
        echo "✅ Deleted " . count($student_ids) . " student users and all related data\n";
    } else {
        echo "✅ No student data to delete\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// STEP 3: List files to delete
echo "STEP 3: Files to be removed (student-specific)...\n";

$files_to_delete = [
    'student_dashboard.html',
    'diagnose_login.php',
    'diagnose_enum_issue.php', 
    'diagnose_role_field.php',
    'apply_employee_role_fix.php',
    'emergency_fix_employee_login.php',
    'EMPLOYEE_LOGIN_DIAGNOSTIC_COMPLETE.md',
    'FINAL_VERIFICATION_REPORT.php',
    'LOGIN_ERROR_ANALYSIS.md',
    'COMPREHENSIVE_EMPLOYEE_LOGIN_DIAGNOSIS.md',
    'DATABASE_MIGRATION_CPC1.md',
    'DATABASE_MIGRATION_FACE_EMBEDDINGS.sql',
    'DATABASE_MIGRATION_FEATURES.sql',
    'DATABASE_SCHEMA_BASE.sql',
    'DEMO_DATA_SEED_CPC1.sql',
    'IMPLEMENTATION_COMPLETE.md',
    'IMPLEMENTATION_FIXES.md',
    'IMPLEMENTATION_SUMMARY_CPC1.md',
    'MEAL_REGISTRATION_MENU_APPROVAL_GUIDE.md',
    'ROOT_CAUSE_SUMMARY.md',
    'SCHEMA_SAFEGUARD_EMPLOYEE_ROLE.sql',
    'CODE_COMPARISON_EMPLOYEE_FIX.md',
    'CHANGES_REFERENCE.md',
    'kitchen_face_checkin.html',
    'employee_face_register.html',
    'help.html'
];

echo "Files to remove:\n";
foreach ($files_to_delete as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        echo "  ❌ $file\n";
    }
}

echo "\n";

// STEP 4: Final database status
echo "STEP 4: Final database status...\n";

$tables = [
    'users' => "SELECT COUNT(*) FROM users",
    'students' => "SELECT COUNT(*) FROM students",
    'kitchen_staff' => "SELECT COUNT(*) FROM kitchen_staff",
    'meal_attendance' => "SELECT COUNT(*) FROM meal_attendance",
    'daily_menu' => "SELECT COUNT(*) FROM daily_menu",
    'transactions' => "SELECT COUNT(*) FROM transactions"
];

foreach ($tables as $table => $query) {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "  $table: $count records\n";
}

echo "\n";

// STEP 5: User roles summary
echo "STEP 5: Remaining user roles...\n";

$stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($roles as $role) {
    echo "  {$role['role']}: {$role['count']} users\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ CONVERSION ANALYSIS COMPLETE\n";
echo "Ready to clean up files next.\n";
?>
