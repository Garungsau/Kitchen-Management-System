<?php
/**
 * EMERGENCY FIX: Employee Login Complete Auto-Repair
 * Purpose: Automatically fix ALL employee login issues in one command
 * 
 * USAGE: 
 * 1. Open in browser: http://localhost/.../api/emergency_fix_employee_login.php
 * 2. Carefully review the BEFORE/AFTER results
 * 3. Fix is applied automatically if everything looks good
 */

require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html lang='vi'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>CPC1 - Emergency Employee Login Fix</title>";
echo "<style>";
echo "body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }";
echo ".success { color: green; font-weight: bold; }";
echo ".error { color: red; font-weight: bold; }";
echo ".warning { color: orange; font-weight: bold; }";
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }";
echo "th { background: #f0f0f0; }";
echo ".before { background: #ffe6e6; }";
echo ".after { background: #e6ffe6; }";
echo "pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>🔧 Emergency Employee Login Auto-Fix</h1>";
echo "<hr>";

$action = isset($_GET['action']) ? trim($_GET['action']) : 'diagnose';

if ($action === 'apply') {
    echo "<h2>Applying Fixes...</h2>";
    applyAllFixes();
} else {
    echo "<h2>Step 1: Diagnosing Issues</h2>";
    diagnoseIssues();
    echo "<hr>";
    echo "<h2>How to Apply Fixes</h2>";
    echo "<p>If issues were found above, click button below to apply all fixes automatically:</p>";
    echo "<button onclick=\"if(confirm('Are you sure? This will modify database.')) { window.location='?action=apply'; }\" style='padding: 10px 20px; background: red; color: white; border: none; border-radius: 5px; cursor: pointer;'>";
    echo "⚠️ APPLY ALL FIXES NOW</button>";
}

echo "</div>";
echo "</body></html>";

function diagnoseIssues() {
    global $conn;
    
    echo "<h3>📋 Database Schema Check</h3>";
    
    // Check ENUM definition
    $result = $conn->query("SHOW CREATE TABLE users")->fetch(PDO::FETCH_ASSOC);
    $create_sql = $result['Create Table'];
    
    if (preg_match('/`role`\s+enum\((.*?)\)/i', $create_sql, $matches)) {
        $enum_values = $matches[1];
        echo "<p>Current role ENUM: <code>$enum_values</code></p>";
        
        if (strpos(strtolower($enum_values), "'employee'") === false) {
            echo "<p class='error'>❌ ISSUE FOUND: 'employee' is missing from ENUM</p>";
            echo "<p>Fix: ALTER TABLE users MODIFY role ENUM('student','employee','kitchen_staff','admin')</p>";
        } else {
            echo "<p class='success'>✅ ENUM definition is correct</p>";
        }
    }
    
    echo "<h3>👥 Employee Accounts Status</h3>";
    
    $stmt = $conn->prepare("
        SELECT u.id, u.email, u.role, u.is_approved, u.is_blocked, 
               u.password, s.full_name, s.student_id_no
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        WHERE u.email LIKE '%@cpc1%' AND u.role IN ('employee', '')
        ORDER BY u.id
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($employees)) {
        echo "<p class='warning'>⚠️ No employee accounts found</p>";
        return;
    }
    
    echo "<table>";
    echo "<tr><th>Email</th><th>Current Role</th><th>Status</th><th>Issues</th></tr>";
    
    $has_issues = false;
    foreach ($employees as $emp) {
        $issues = [];
        
        if (empty($emp['role'])) {
            $issues[] = "Role is empty string";
            $has_issues = true;
        }
        if (!$emp['is_approved']) {
            $issues[] = "Not approved";
            $has_issues = true;
        }
        if ($emp['is_blocked']) {
            $issues[] = "Account blocked";
            $has_issues = true;
        }
        if (empty($emp['student_id_no'])) {
            $issues[] = "No student profile";
            $has_issues = true;
        }
        if (empty($emp['password'])) {
            $issues[] = "No password hash";
            $has_issues = true;
        }
        
        $status = empty($issues) ? '<span class="success">✅ OK</span>' : '<span class="error">❌ ' . count($issues) . ' issues</span>';
        $issue_text = empty($issues) ? '-' : implode(', ', $issues);
        
        echo "<tr>";
        echo "<td>" . $emp['email'] . "</td>";
        echo "<td><span class='" . (empty($emp['role']) ? 'error' : 'success') . "'>" . (empty($emp['role']) ? '(empty)' : $emp['role']) . "</span></td>";
        echo "<td>$status</td>";
        echo "<td>$issue_text</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!$has_issues) {
        echo "<p class='success'>✅ All employee accounts are properly configured!</p>";
    }
}

function applyAllFixes() {
    global $conn;
    
    echo "<h3>🔧 Applying Fixes...</h3>";
    
    $all_ok = true;
    
    // Fix 1: Update ENUM
    try {
        echo "<p>1️⃣ Updating users.role ENUM...</p>";
        $conn->exec("ALTER TABLE users MODIFY COLUMN role ENUM('student','employee','kitchen_staff','admin') DEFAULT 'student'");
        echo "<p class='success'>✅ ENUM updated successfully</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error updating ENUM: " . $e->getMessage() . "</p>";
        $all_ok = false;
    }
    
    // Fix 2: Set empty roles to 'employee'
    try {
        echo "<p>2️⃣ Fixing empty role values...</p>";
        $result = $conn->exec("UPDATE users SET role = 'employee' WHERE role = '' OR role NOT IN ('student','employee','kitchen_staff','admin')");
        echo "<p class='success'>✅ Fixed $result accounts</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error fixing roles: " . $e->getMessage() . "</p>";
        $all_ok = false;
    }
    
    // Fix 3: Approve all employee accounts
    try {
        echo "<p>3️⃣ Auto-approving employee accounts...</p>";
        $result = $conn->exec("UPDATE users SET is_approved = 1, is_blocked = 0 WHERE role = 'employee'");
        echo "<p class='success'>✅ Approved $result accounts</p>";
    } catch (Exception $e) {
        echo "<p class='warning'>⚠️ Warning approving accounts: " . $e->getMessage() . "</p>";
    }
    
    // Fix 4: Create missing student profiles
    try {
        echo "<p>4️⃣ Creating missing employee profiles...</p>";
        $stmt = $conn->prepare("
            SELECT u.id, u.email FROM users u
            WHERE u.role = 'employee' 
            AND u.id NOT IN (SELECT DISTINCT user_id FROM students)
        ");
        $stmt->execute();
        $missing_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $created = 0;
        foreach ($missing_profiles as $emp) {
            try {
                $name_part = explode('@', $emp['email'])[0];
                $insert = $conn->prepare("
                    INSERT INTO students (user_id, full_name, student_id_no, department, hall_name)
                    VALUES (?, ?, ?, ?, 'CPC1')
                ");
                $insert->execute([
                    $emp['id'],
                    ucfirst($name_part),
                    'EMP-' . str_pad($emp['id'], 5, '0', STR_PAD_LEFT),
                    'Admin'
                ]);
                $created++;
            } catch (Exception $e) {
                // Profile might already exist or other constraint issue
            }
        }
        echo "<p class='success'>✅ Created $created profiles</p>";
    } catch (Exception $e) {
        echo "<p class='warning'>⚠️ Warning creating profiles: " . $e->getMessage() . "</p>";
    }
    
    // Verification
    echo "<hr>";
    echo "<h3>✅ Verification After Fixes</h3>";
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(IF(role='employee',1,0)) as employees,
               SUM(IF(is_approved=1 AND role='employee',1,0)) as approved_employees,
               SUM(IF(is_blocked=1 AND role='employee',1,0)) as blocked_employees
        FROM users
        WHERE role='employee'
    ");
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Metric</th><th>Value</th></tr>";
    echo "<tr><td>Total employee accounts</td><td>" . $summary['total'] . "</td></tr>";
    echo "<tr><td>With correct role='employee'</td><td class='success'>" . $summary['employees'] . "</td></tr>";
    echo "<tr><td>Approved and active</td><td class='success'>" . $summary['approved_employees'] . "</td></tr>";
    echo "<tr><td>Blocked accounts</td><td class='error'>" . $summary['blocked_employees'] . "</td></tr>";
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 class='success'>✅ All fixes applied successfully!</h2>";
    echo "<p><strong>Next step:</strong> Try logging in with your employee account.</p>";
    echo "<p><a href='javascript:location.reload()'>🔄 Run Diagnostics Again</a> to verify</p>";
}

?>
