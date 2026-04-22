-- SQL Migration for Inventory Management System
-- Run this in phpMyAdmin or MySQL to create required tables

-- Table: ingredients (Danh sách nguyên liệu)
CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    unit VARCHAR(50) NOT NULL COMMENT 'kg, lít, chiếc, bộ, etc.',
    quantity_in_stock DECIMAL(10, 2) DEFAULT 0 COMMENT 'Số lượng hiện tại',
    min_threshold DECIMAL(10, 2) DEFAULT 10 COMMENT 'Mức tối thiểu cảnh báo',
    supplier_id INT COMMENT 'Nhà cung cấp chính',
    last_unit_price DECIMAL(10, 2) DEFAULT 0 COMMENT 'Giá đơn vị lần cuối',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ingredient_recipes (Công thức liên kết nguyên liệu với thực đơn)
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

-- Table: inventory_transactions (Lịch sử giao dịch kho)
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

-- Table: purchase_orders (Đơn đặt hàng từ nhà cung cấp)
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    supplier_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2),
    total_cost DECIMAL(15, 2),
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

-- Index để cải thiện tốc độ query
CREATE INDEX idx_ingredient_stock ON ingredients(quantity_in_stock);
CREATE INDEX idx_ingredient_threshold ON ingredients(min_threshold);
CREATE INDEX idx_transaction_date ON inventory_transactions(created_at);
CREATE INDEX idx_order_status ON purchase_orders(order_status);
CREATE INDEX idx_order_date ON purchase_orders(order_date);
