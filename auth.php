<?php
// Shared auth helper for API endpoints

function start_session_if_needed(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function json_error(string $message, string $status = 'error', int $httpCode = 200): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
    exit();
}

function normalize_role(?string $role): ?string {
    if ($role === null) {
        return null;
    }

    $normalized = strtolower(trim($role));
    if ($normalized === '') {
        return null;
    }

    $normalized = str_replace([' ', '-'], '_', $normalized);

    $aliases = [
        'student' => 'employee',
        'kitchen' => 'kitchen_staff',
        'kitchenstaff' => 'kitchen_staff',
        'kitchen_staff' => 'kitchen_staff',
        'nhan_vien' => 'employee',
        'staff' => 'employee'
    ];

    return $aliases[$normalized] ?? $normalized;
}

function require_role(array $roles): void {
    start_session_if_needed();
    $role = normalize_role($_SESSION['role'] ?? null);
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId || !$role) {
        json_error('Unauthorized', 'error', 401);
    }

    $allowedRoles = [];
    foreach ($roles as $allowed) {
        $nr = normalize_role((string)$allowed);
        if ($nr !== null) {
            $allowedRoles[] = $nr;
        }
    }

    if (!in_array($role, $allowedRoles, true)) {
        json_error('Forbidden', 'error', 403);
    }
}

function current_user_id(): int {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
}

function current_role(): ?string {
    return normalize_role($_SESSION['role'] ?? null);
}

function ensure_employee_profile_exists(PDO $conn, int $userId, string $email = ''): void {
    if ($userId <= 0) {
        return;
    }

    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
        if (!$tableCheck || !$tableCheck->fetchColumn()) {
            return;
        }

        $stmt = $conn->prepare("SELECT user_id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $colsStmt = $conn->query("SHOW COLUMNS FROM students");
        $columns = [];
        foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (!empty($c['Field'])) {
                $columns[] = $c['Field'];
            }
        }

        if (empty($columns) || !in_array('user_id', $columns, true)) {
            return;
        }

        $emailLocal = trim(explode('@', $email)[0] ?? '');
        $fullName = trim(ucwords(str_replace(['.', '_', '-'], ' ', $emailLocal)));
        if ($fullName === '') {
            $fullName = 'Nhan vien #' . $userId;
        }

        $studentId = 'EMP' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);
        if (in_array('student_id_no', $columns, true)) {
            $sidStmt = $conn->prepare("SELECT user_id FROM students WHERE student_id_no = ? LIMIT 1");
            $sidStmt->execute([$studentId]);
            $owner = $sidStmt->fetchColumn();
            if ($owner && intval($owner) !== $userId) {
                $studentId = $studentId . '_' . $userId;
            }
        }

        $valuesByColumn = [
            'user_id' => $userId,
            'full_name' => $fullName,
            'student_id_no' => $studentId,
            'registration_no' => $studentId,
            'department' => 'Nhan vien',
            'wallet_balance' => 0,
            'employee_type' => 'production',
            'subsidy_rate' => 0
        ];

        $insertCols = [];
        $placeholders = [];
        $params = [];

        foreach ($valuesByColumn as $col => $val) {
            if (!in_array($col, $columns, true)) {
                continue;
            }
            $insertCols[] = $col;
            $placeholders[] = '?';
            $params[] = $val;
        }

        if (empty($insertCols)) {
            return;
        }

        $sql = 'INSERT INTO students (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $ins = $conn->prepare($sql);
        $ins->execute($params);
    } catch (Exception $e) {
        // Non-blocking self-heal helper: never break request flow.
    }
}
