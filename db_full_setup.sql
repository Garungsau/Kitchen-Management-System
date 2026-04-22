-- Complete Database Setup for Smart Meal Management System
-- Run this in phpMyAdmin to create all required tables in correct order

-- ========================================
-- 1. DISABLE FOREIGN KEY CHECKS
-- ========================================
SET FOREIGN_KEY_CHECKS=0;

-- ========================================
-- 2. USERS TABLE (First - no dependencies)
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL COMMENT 'admin,kitchen_staff,employee,student',
    full_name VARCHAR(150) NOT NULL,
    identity_number VARCHAR(50),
    email VARCHAR(150),
    phone VARCHAR(20),
    username VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    credit_balance DECIMAL(15, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 3. SUPPLIERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(200) NOT NULL,
    contact_person VARCHAR(150),
    phone VARCHAR(20),
    email VARCHAR(150),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. DAILY_MENU TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS daily_menu (
    menu_date DATE PRIMARY KEY,
    lunch TEXT COMMENT 'Lunch meal items',
    dinner TEXT COMMENT 'Dinner meal items',
    created_by INT,
    approval_status ENUM('draft', 'pending_approval', 'approved', 'rejected') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 5. INGREDIENTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    unit VARCHAR(50) NOT NULL COMMENT 'kg, lít, chiếc, bộ, etc.',
    quantity_in_stock DECIMAL(10, 2) DEFAULT 0 COMMENT 'Số lượng hiện tại',
    min_threshold DECIMAL(10, 2) DEFAULT 10 COMMENT 'Mức tối thiểu cảnh báo',
    supplier_id INT,
    last_unit_price DECIMAL(10, 2) DEFAULT 0 COMMENT 'Giá đơn vị lần cuối',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 6. INGREDIENT_RECIPES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS ingredient_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_date DATE NOT NULL,
    meal_type ENUM('lunch', 'dinner') NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_needed DECIMAL(10, 2) NOT NULL COMMENT 'Số lượng cần dùng',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_date) REFERENCES daily_menu(menu_date) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 7. INVENTORY_TRANSACTIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    transaction_type ENUM('in', 'out', 'adjustment') COMMENT 'in=nhập, out=xuất, adjustment=điều chỉnh',
    quantity DECIMAL(10, 2) NOT NULL,
    reference_type VARCHAR(50) COMMENT 'purchase_order, daily_usage, manual_adjustment',
    reference_id INT COMMENT 'ID của purchase_order hoặc order',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 8. PURCHASE_ORDERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    supplier_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2),
    total_cost DECIMAL(15, 2),
    vat_percent DECIMAL(5, 2) DEFAULT 10,
    order_status ENUM('pending', 'confirmed', 'received', 'cancelled') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_delivery_date DATE,
    received_date DATE,
    received_by INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 9. MEALS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    meal_date DATE NOT NULL,
    meal_type ENUM('lunch', 'dinner') NOT NULL,
    status ENUM('registered', 'checked_in', 'no_show', 'cancelled') DEFAULT 'registered',
    check_in_time TIME,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 10. OTHER OPTIONAL TABLES
-- ========================================

-- Complaints
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT,
    complaint_type VARCHAR(100),
    description TEXT,
    status ENUM('pending', 'resolved', 'rejected') DEFAULT 'pending',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notices
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Face Embeddings (for face recognition)
CREATE TABLE IF NOT EXISTS face_embeddings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    embedding LONGTEXT COMMENT 'JSON array of embedding values',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 11. INDEXES FOR PERFORMANCE
-- ========================================
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_ingredients_stock ON ingredients(quantity_in_stock);
CREATE INDEX idx_ingredients_threshold ON ingredients(min_threshold);
CREATE INDEX idx_meals_user_date ON meals(user_id, meal_date);
CREATE INDEX idx_meals_status ON meals(status);
CREATE INDEX idx_transaction_date ON inventory_transactions(created_at);
CREATE INDEX idx_order_status ON purchase_orders(order_status);
CREATE INDEX idx_order_date ON purchase_orders(order_date);

-- ========================================
-- 12. RE-ENABLE FOREIGN KEY CHECKS
-- ========================================
SET FOREIGN_KEY_CHECKS=1;

-- ========================================
-- DONE!
-- ========================================
