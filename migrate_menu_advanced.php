<?php
/**
 * Database migration to add advanced menu management
 * Extends daily_menu table with nutrition info and images
 * Creates menu templates and history tracking
 */
require_once 'config.php';

$migrations = [

    // 1. Extend daily_menu with nutrition and image
    "1. Extend daily_menu table" => "
        ALTER TABLE daily_menu ADD COLUMN (
            lunch_image_url VARCHAR(255),
            lunch_calories INT DEFAULT 0,
            lunch_protein_g DECIMAL(5,1) DEFAULT 0,
            lunch_carbs_g DECIMAL(5,1) DEFAULT 0,
            lunch_fat_g DECIMAL(5,1) DEFAULT 0,
            
            dinner_image_url VARCHAR(255),
            dinner_calories INT DEFAULT 0,
            dinner_protein_g DECIMAL(5,1) DEFAULT 0,
            dinner_carbs_g DECIMAL(5,1) DEFAULT 0,
            dinner_fat_g DECIMAL(5,1) DEFAULT 0
        )
    ",

    // 2. Create menu templates table
    "2. Create menu_templates table" => "
        CREATE TABLE IF NOT EXISTS menu_templates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_name VARCHAR(100) NOT NULL,
            description TEXT,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ",

    // 3. Create template meals table
    "3. Create menu_template_meals table" => "
        CREATE TABLE IF NOT EXISTS menu_template_meals (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_id INT NOT NULL,
            day_of_week INT, -- 0=Sunday to 6=Saturday
            shift VARCHAR(20), -- 'lunch' or 'dinner'
            meal_name VARCHAR(100) NOT NULL,
            image_url VARCHAR(255),
            calories INT DEFAULT 0,
            protein_g DECIMAL(5,1) DEFAULT 0,
            carbs_g DECIMAL(5,1) DEFAULT 0,
            fat_g DECIMAL(5,1) DEFAULT 0,
            FOREIGN KEY (template_id) REFERENCES menu_templates(id) ON DELETE CASCADE
        )
    ",

    // 4. Create menu history table
    "4. Create menu_history table" => "
        CREATE TABLE IF NOT EXISTS menu_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            menu_date DATE NOT NULL,
            shift VARCHAR(20), -- 'lunch' or 'dinner'
            meal_name VARCHAR(100) NOT NULL,
            served_by INT,
            served_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (served_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_date_shift (menu_date, shift),
            INDEX idx_meal_name (meal_name)
        )
    "
];

try {
    $successCount = 0;
    $errorCount = 0;

    foreach ($migrations as $name => $sql) {
        try {
            // Check if column/table already exists (basic handling)
            if (strpos($sql, 'ALTER TABLE') !== false) {
                // For ALTER, try but don't fail if column exists
                try {
                    $conn->exec($sql);
                    echo "✓ $name\n";
                    $successCount++;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                        strpos($e->getMessage(), 'already exists') !== false) {
                        echo "⚠ $name (already exists)\n";
                        $successCount++;
                    } else {
                        throw $e;
                    }
                }
            } else {
                $conn->exec($sql);
                echo "✓ $name\n";
                $successCount++;
            }
        } catch (PDOException $e) {
            echo "✗ $name: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }

    echo "\n📊 Migration Summary:\n";
    echo "✓ Success: $successCount\n";
    echo "✗ Failed: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n✅ All migrations completed!\n";
    }

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
