<?php
require_once 'config.php';

// Create kitchen_ingredients table
$stmt = $conn->prepare("CREATE TABLE IF NOT EXISTS kitchen_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(15, 0) NOT NULL,
    date_added DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$stmt->execute();

// Create suppliers table
$stmt = $conn->prepare("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL UNIQUE,
    contact_person VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$stmt->execute();

// Create meal_complaints table if not exists
$stmt = $conn->prepare("CREATE TABLE IF NOT EXISTS meal_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    complaint_type VARCHAR(100),
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    response_note TEXT,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES students(user_id)
)");
$stmt->execute();

// Create complaint_files table if not exists
$stmt = $conn->prepare("CREATE TABLE IF NOT EXISTS complaint_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES meal_complaints(id)
)");
$stmt->execute();

echo json_encode(['status' => 'success', 'message' => 'Database tables created successfully']);
?>
