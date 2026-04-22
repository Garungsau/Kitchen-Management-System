<?php
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
header('Content-Type: application/json; charset=utf-8');

require_role(['employee']);

function column_exists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function meal_type_supports_both(PDO $conn): bool {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM `meal_attendance` LIKE 'meal_type'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col || !isset($col['Type'])) return false;
        return stripos((string)$col['Type'], "'both'") !== false;
    } catch (Exception $e) {
        return false;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

$status = isset($input['status']) ? intval($input['status']) : -1;
$target_date = isset($input['date']) ? $input['date'] : date('Y-m-d', strtotime('+1 day'));
$meal_type = isset($input['meal_type']) ? trim($input['meal_type']) : 'lunch'; // lunch, dinner
$user_id = $_SESSION['user_id'];
$MEAL_COST = MEAL_COST; 
$MAX_ADVANCE_DAYS = 14; // limit how far ahead a user can register/cancel

if ($status !== 0 && $status !== 1) {
    echo json_encode(["status" => "error", "message" => "Trạng thái suất ăn không hợp lệ."]);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    echo json_encode(["status" => "error", "message" => "Ngày không hợp lệ."]);
    exit();
}

$currentDatetime = new DateTime();
$targetDatetime = DateTime::createFromFormat('Y-m-d', $target_date);
$todayDatetime = new DateTime('today');
if (!$targetDatetime) {
    echo json_encode(["status" => "error", "message" => "Ngày không hợp lệ."]);
    exit();
}

$daysAhead = (int)$todayDatetime->diff($targetDatetime)->days;

if ($targetDatetime <= $todayDatetime) {
    echo json_encode(["status" => "error", "message" => "Chỉ cho phép chỉnh suất ăn từ ngày mai."]);
    exit();
}

if (!in_array($meal_type, ['lunch', 'dinner'], true)) {
    echo json_encode(["status" => "error", "message" => "Loại suất ăn không hợp lệ."]);
    exit();
}

if ($daysAhead > $MAX_ADVANCE_DAYS) {
    echo json_encode(["status" => "error", "message" => "Chỉ cho phép đăng ký trong vòng $MAX_ADVANCE_DAYS ngày tới."]);
    exit();
}

// Dynamic cutoff time from meal_cutoff_settings; fallback 08:00 if not configured
$cutoffTime = '08:00'; // fixed cutoff at 08:00

$cutoffDatetime = new DateTime($target_date . ' ' . $cutoffTime . ':00');
if ($currentDatetime >= $cutoffDatetime) {
    echo json_encode(["status" => "error", "message" => "Đã qua hạn chỉnh sửa cho ngày $target_date ($cutoffTime)."]);
    exit();
}

try {
    $hasStatusColumn = column_exists($conn, 'meal_attendance', 'status');
    $hasRegistrationTimeColumn = column_exists($conn, 'meal_attendance', 'registration_time');
    $hasCancelTimeColumn = column_exists($conn, 'meal_attendance', 'cancel_time');
    $hasMealTypeColumn = column_exists($conn, 'meal_attendance', 'meal_type');
    
    // Ensure meal_type column exists and supports full-day value 'both'
    if (!$hasMealTypeColumn) {
        try {
            $conn->exec("ALTER TABLE meal_attendance ADD COLUMN meal_type ENUM('lunch','dinner','both') DEFAULT 'lunch' AFTER is_active");
            $hasMealTypeColumn = true;
        } catch (Exception $alterEx) {
            // Try flexible approach: Insert/Update with meal_type parameter and handle missing column gracefully
            // But still try one more time to check if column exists now
            $hasMealTypeColumn = column_exists($conn, 'meal_attendance', 'meal_type');
            if (!$hasMealTypeColumn) {
                // Last resort: log and continue - but ALWAYS validate on cancel based on our parameters
                error_log("Warning: meal_type column missing in meal_attendance table. Validation may fail.");
            }
        }
    } else {
        if (!meal_type_supports_both($conn)) {
            try {
                $conn->exec("ALTER TABLE meal_attendance MODIFY COLUMN meal_type ENUM('lunch','dinner','both') DEFAULT 'lunch'");
            } catch (Exception $alterEx) {
                // Keep flow running; frontend will fallback to cached value if DB schema cannot be changed now.
            }
        }
    }

    $conn->beginTransaction();

    $selectFields = ['id', 'is_active'];
    if ($hasStatusColumn) $selectFields[] = 'status';
    if ($hasMealTypeColumn) $selectFields[] = 'meal_type';
    if ($hasRegistrationTimeColumn) $selectFields[] = 'registration_time';
    if ($hasCancelTimeColumn) $selectFields[] = 'cancel_time';

    $stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . " FROM meal_attendance WHERE user_id = ? AND meal_date = ? FOR UPDATE");
    $stmt->execute([$user_id, $target_date]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_status = ($existing === false) ? 0 : intval($existing['is_active']);
    $current_meal_type = ($existing && isset($existing['meal_type'])) ? $existing['meal_type'] : null;
    $current_state = ($existing === false)
        ? 'none'
        : (($hasStatusColumn ? ($existing['status'] ?? '') : '') ?: (intval($existing['is_active']) === 1 ? 'registered' : 'cancelled'));

    $responseMealType = $meal_type;

    if ($status === 1 && $current_status == 0) {
        try {
            // Prefer approved menu for the target date; otherwise allow the most recent draft/pending menu for that date.
            $menuStmt = $conn->prepare("SELECT id FROM daily_menu WHERE menu_date = ? AND approval_status = 'approved'");
            $menuStmt->execute([$target_date]);
            $menuRow = $menuStmt->fetch(PDO::FETCH_ASSOC);

            if (!$menuRow) {
                $menuStmt = $conn->prepare("SELECT id FROM daily_menu WHERE menu_date = ? ORDER BY id DESC LIMIT 1");
                $menuStmt->execute([$target_date]);
                $menuRow = $menuStmt->fetch(PDO::FETCH_ASSOC);
            }

            // If the date has no entries at all, fall back to the most recently created menu (any date) so registration is not blocked.
            if (!$menuRow) {
                $menuStmt = $conn->prepare("SELECT id FROM daily_menu ORDER BY menu_date DESC, id DESC LIMIT 1");
                $menuStmt->execute();
                $menuRow = $menuStmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $menuEx) {
            $menuStmt = $conn->prepare("SELECT id FROM daily_menu ORDER BY menu_date DESC, id DESC LIMIT 1");
            $menuStmt->execute();
            $menuRow = $menuStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$menuRow) {
            throw new Exception("Chưa có thực đơn khả dụng để đăng ký.");
        }

        // NOTE: Wallet check removed - meal costs calculated at month-end for payroll

        if ($existing) {
            $updateParts = ["is_active = 1"];
            if ($hasStatusColumn) {
                $updateParts[] = "status = 'registered'";
            }
            if ($hasMealTypeColumn) {
                $updateParts[] = "meal_type = ?";
            }
            if ($hasRegistrationTimeColumn) {
                $updateParts[] = "registration_time = NOW()";
            }
            if ($hasCancelTimeColumn) {
                $updateParts[] = "cancel_time = NULL";
            }

            $mealTypeParams = [];
            if ($hasMealTypeColumn) {
                $mealTypeParams = [$meal_type];
            }
            $conn->prepare("UPDATE meal_attendance SET " . implode(', ', $updateParts) . " WHERE id = ?")
                ->execute(array_merge($mealTypeParams, [$existing['id']]));
        } else {
            $insertCols = ['user_id', 'meal_date', 'is_active'];
            $insertVals = ['?', '?', '1'];
            $insertParams = [$user_id, $target_date];

            if ($hasStatusColumn) {
                $insertCols[] = 'status';
                $insertVals[] = "'registered'";
            }
            if ($hasMealTypeColumn) {
                $insertCols[] = 'meal_type';
                $insertVals[] = '?';
                $insertParams[] = $meal_type;
            }
            if ($hasRegistrationTimeColumn) {
                $insertCols[] = 'registration_time';
                $insertVals[] = 'NOW()';
            }

            $conn->prepare("INSERT INTO meal_attendance (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")")
                ->execute($insertParams);
        }

        try {
            $conn->prepare("INSERT INTO meal_registration_history (user_id, meal_date, action, previous_status, new_status, reason) VALUES (?, ?, 'registered', ?, 'registered', ?)")
                ->execute([$user_id, $target_date, $current_state, 'Registered via dashboard toggle']);
        } catch (Exception $historyEx) {
            // Keep toggle flow working even if history table is unavailable.
        }

    } elseif ($status === 1 && $current_status == 1) {
        if ($existing && $hasMealTypeColumn) {
            $existingMealType = ($existing['meal_type'] ?? '') ?: 'lunch';
            if ($existingMealType !== $meal_type) {
                $updateParts = [];
                if ($hasStatusColumn) {
                    $updateParts[] = "status = 'registered'";
                }
                $updateParts[] = "meal_type = 'both'";
                if ($hasCancelTimeColumn) {
                    $updateParts[] = "cancel_time = NULL";
                }

                $conn->prepare("UPDATE meal_attendance SET " . implode(', ', $updateParts) . " WHERE id = ?")
                    ->execute([$existing['id']]);
                $responseMealType = 'both';
            }
        }

    } elseif ($status === 0 && $current_status == 1) {
        if ($current_state === 'checked_in') {
            throw new Exception("Meal already checked in and cannot be cancelled.");
        }

        // For old records without meal_type, set it to default 'lunch' so validation works
        if ($existing && $current_meal_type === null) {
            $current_meal_type = 'lunch'; // Default for old data
        }

        // If full-day is registered, cancelling one shift should keep the other shift active.
        if ($current_meal_type === 'both') {
            $remainingShift = ($meal_type === 'lunch') ? 'dinner' : 'lunch';
            if ($existing) {
                $updateParts = ["is_active = 1"];
                if ($hasStatusColumn) {
                    $updateParts[] = "status = 'registered'";
                }
                if ($hasMealTypeColumn) {
                    $updateParts[] = "meal_type = ?";
                }
                if ($hasCancelTimeColumn) {
                    $updateParts[] = "cancel_time = NULL";
                }

                $params = [];
                if ($hasMealTypeColumn) {
                    $params[] = $remainingShift;
                }
                $params[] = $existing['id'];

                $conn->prepare("UPDATE meal_attendance SET " . implode(', ', $updateParts) . " WHERE id = ?")
                    ->execute($params);
            }

            $responseMealType = $meal_type;

            try {
                $conn->prepare("INSERT INTO meal_registration_history (user_id, meal_date, action, previous_status, new_status, reason) VALUES (?, ?, 'cancelled', ?, 'registered', ?)")
                    ->execute([$user_id, $target_date, $current_state, 'Cancelled one shift from full-day registration']);
            } catch (Exception $historyEx) {
                // Keep toggle flow working even if history table is unavailable.
            }
        } else {
        // Validate that user is cancelling the correct shift - ALWAYS ENFORCE THIS
        if ($current_meal_type && $current_meal_type !== $meal_type) {
            $shiftNames = ['lunch' => 'Ca Trưa (☀️)', 'dinner' => 'Ca Tối (🌙)'];
            $registered = $shiftNames[$current_meal_type] ?? $current_meal_type;
            $requested = $shiftNames[$meal_type] ?? $meal_type;
            throw new Exception("Bạn chỉ đăng ký {$registered}, không thể hủy {$requested}");
        }

        // NOTE: Wallet refund removed - meal costs calculated at month-end for payroll
        if ($existing) {
            $updateParts = ["is_active = 0"];
            if ($hasStatusColumn) {
                $updateParts[] = "status = 'cancelled'";
            }
            if ($hasMealTypeColumn) {
                // Ensure meal_type is saved for future validations
                $updateParts[] = "meal_type = ?";
            }
            if ($hasCancelTimeColumn) {
                $updateParts[] = "cancel_time = NOW()";
            }

            $cancelMealTypeParams = [];
            if ($hasMealTypeColumn) {
                $cancelMealTypeParams = [$current_meal_type]; // Save the actual shift being cancelled
            }
            $conn->prepare("UPDATE meal_attendance SET " . implode(', ', $updateParts) . " WHERE id = ?")
                ->execute(array_merge($cancelMealTypeParams, [$existing['id']]));
        }

        try {
            $conn->prepare("INSERT INTO meal_registration_history (user_id, meal_date, action, previous_status, new_status, reason) VALUES (?, ?, 'cancelled', ?, 'cancelled', ?)")
                ->execute([$user_id, $target_date, $current_state, 'Cancelled via dashboard toggle']);
        } catch (Exception $historyEx) {
            // Keep toggle flow working even if history table is unavailable.
        }
        }
    }

    $conn->commit();
    
    // Prepare response with consistent format
    $shiftNames = ['lunch' => 'Ca Trưa', 'dinner' => 'Ca Tối', 'both' => 'Cả ngày'];
    $shiftDisplay = $shiftNames[$responseMealType] ?? $responseMealType;
    
    $message = ($status === 1) 
        ? "Đăng ký $shiftDisplay ngày $target_date thành công" 
        : "Hủy đăng ký $shiftDisplay ngày $target_date thành công";
    
    echo json_encode([
        "status" => "success", 
        "message" => $message,
        "new_status" => $status,
        "meal_date" => $target_date,
        "meal_type" => $responseMealType,
        "cost" => $MEAL_COST
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>