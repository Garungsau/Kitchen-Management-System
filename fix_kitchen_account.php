<?php
/**
 * Fix Kitchen Staff Login
 * Ensure kitchen staff account exists and is properly configured
 */

require_once __DIR__ . '/config.php';

echo "🔧 Fixing Kitchen Staff Account...\n\n";

$kitchen_email = 'kitchen@company.com';
$kitchen_password = 'Kitchen@12345';
$kitchen_role = 'kitchen_staff';

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, password, role, is_approved, is_blocked FROM users WHERE email = ?");
    $stmt->execute([$kitchen_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Kitchen staff account not found. Creating now...\n";
        
        // Create kitchen staff account
        $pwd_hash = password_hash($kitchen_password, PASSWORD_BCRYPT);
        
        $insert = $conn->prepare("
            INSERT INTO users (email, password, role, name, is_approved, is_blocked)
            VALUES (?, ?, ?, ?, 1, 0)
        ");
        
        if ($insert->execute([$kitchen_email, $pwd_hash, $kitchen_role, 'Kitchen Staff'])) {
            echo "✅ Kitchen staff account created successfully!\n\n";
            echo "📋 Account Details:\n";
            echo "   Email: $kitchen_email\n";
            echo "   Password: $kitchen_password\n";
            echo "   Role: Kitchen Staff\n";
            echo "   Status: Approved & Active\n";
        } else {
            echo "❌ Failed to create account\n";
            exit(1);
        }
    } else {
        echo "✅ Kitchen staff account found!\n\n";
        echo "📋 Account Status:\n";
        echo "   Email: {$user['email']}\n";
        echo "   Role: {$user['role']}\n";
        echo "   Approved: " . ($user['is_approved'] ? 'Yes ✓' : 'No ❌') . "\n";
        echo "   Blocked: " . ($user['is_blocked'] ? 'Yes ❌' : 'No ✓') . "\n";
        
        // Fix if needed
        $needs_fix = false;
        
        if (!$user['is_approved']) {
            echo "\n   Fixing: Approving account...\n";
            $update = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            if ($update->execute([$user['id']])) {
                echo "   ✅ Account approved\n";
            }
            $needs_fix = true;
        }
        
        if ($user['is_blocked']) {
            echo "\n   Fixing: Unblocking account...\n";
            $update = $conn->prepare("UPDATE users SET is_blocked = 0 WHERE id = ?");
            if ($update->execute([$user['id']])) {
                echo "   ✅ Account unblocked\n";
            }
            $needs_fix = true;
        }
        
        // Verify password
        if (!password_verify($kitchen_password, $user['password'])) {
            echo "\n   Fixing: Resetting password...\n";
            $pwd_hash = password_hash($kitchen_password, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update->execute([$pwd_hash, $user['id']])) {
                echo "   ✅ Password updated\n";
                echo "   New password: $kitchen_password\n";
            }
            $needs_fix = true;
        }
        
        if (!$needs_fix) {
            echo "\n✅ Account is in perfect condition!\n";
        }
    }
    
    echo "\n✔️  All fixed! Try logging in with:\n";
    echo "   📧 Email: $kitchen_email\n";
    echo "   🔑 Password: $kitchen_password\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
