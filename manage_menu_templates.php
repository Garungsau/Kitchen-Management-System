<?php
/**
 * Menu Templates Management (CRUD)
 * Create, list, apply, and delete weekly menu templates
 */
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';

try {
    switch ($action) {
        case 'list':
            handleListTemplates();
            break;
        
        case 'create':
            handleCreateTemplate();
            break;
        
        case 'get':
            handleGetTemplate();
            break;
        
        case 'apply':
            handleApplyTemplate();
            break;
        
        case 'delete':
            handleDeleteTemplate();
            break;
        
        case 'add_meal':
            handleAddMealToTemplate();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Unknown action"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

function handleListTemplates() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT id, template_name, description, created_at, updated_at
        FROM menu_templates
        ORDER BY updated_at DESC
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "templates" => $templates
    ]);
}

function handleCreateTemplate() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['template_name'] ?? '';
    $desc = $input['description'] ?? '';

    if (!$name) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template name is required"]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO menu_templates (template_name, description, created_by)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$name, $desc, $_SESSION['user_id'] ?? null]);
    $templateId = $conn->lastInsertId();

    echo json_encode([
        "status" => "success",
        "message" => "Template created",
        "template_id" => intval($templateId)
    ]);
}

function handleGetTemplate() {
    global $conn;
    $templateId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$templateId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template ID required"]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, template_name, description, created_at
        FROM menu_templates
        WHERE id = ?
    ");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Template not found"]);
        exit();
    }

    // Get meals in template
    $mealStmt = $conn->prepare("
        SELECT day_of_week, shift, meal_name, calories, protein_g, carbs_g, fat_g, image_url
        FROM menu_template_meals
        WHERE template_id = ?
        ORDER BY day_of_week, shift
    ");
    $mealStmt->execute([$templateId]);
    $template['meals'] = $mealStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "template" => $template
    ]);
}

function handleAddMealToTemplate() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $templateId = $input['template_id'] ?? 0;
    $dayOfWeek = $input['day_of_week'] ?? 0;
    $shift = $input['shift'] ?? 'lunch';
    $mealName = $input['meal_name'] ?? '';
    $nutrition = $input['nutrition'] ?? [];

    if (!$templateId || !$mealName) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template ID and meal name required"]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO menu_template_meals 
        (template_id, day_of_week, shift, meal_name, calories, protein_g, carbs_g, fat_g)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $templateId,
        intval($dayOfWeek),
        $shift,
        $mealName,
        intval($nutrition['calories'] ?? 0),
        floatval($nutrition['protein_g'] ?? 0),
        floatval($nutrition['carbs_g'] ?? 0),
        floatval($nutrition['fat_g'] ?? 0)
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Meal added to template"
    ]);
}

function handleApplyTemplate() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $templateId = $input['template_id'] ?? 0;
    $startDate = $input['start_date'] ?? date('Y-m-d', strtotime('next Monday'));

    if (!$templateId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template ID required"]);
        exit();
    }

    // Get template meals
    $stmt = $conn->prepare("
        SELECT day_of_week, shift, meal_name, calories, protein_g, carbs_g, fat_g
        FROM menu_template_meals
        WHERE template_id = ?
    ");
    $stmt->execute([$templateId]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$meals) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template has no meals"]);
        exit();
    }

    // Apply to 7 days starting from start_date
    $startTimestamp = strtotime($startDate);
    $inserted = 0;

    foreach ($meals as $meal) {
        $dayOffset = $meal['day_of_week'] - date('w', $startTimestamp);
        if ($dayOffset < 0) $dayOffset += 7;
        
        $applyDate = date('Y-m-d', $startTimestamp + ($dayOffset * 86400));
        
        $column = $meal['shift'] === 'lunch' ? 'lunch' : 'dinner';
        $nutritionSuffix = $meal['shift'] === 'lunch' ? '_lunch' : '_dinner';

        $insertStmt = $conn->prepare("
            INSERT INTO daily_menu 
            (menu_date, $column, {$column}_calories, {$column}_protein_g, {$column}_carbs_g, {$column}_fat_g, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, 'draft')
            ON DUPLICATE KEY UPDATE 
            $column = VALUES($column),
            {$column}_calories = VALUES({$column}_calories),
            {$column}_protein_g = VALUES({$column}_protein_g),
            {$column}_carbs_g = VALUES({$column}_carbs_g),
            {$column}_fat_g = VALUES({$column}_fat_g)
        ");

        $insertStmt->execute([
            $applyDate,
            $meal['meal_name'],
            intval($meal['calories']),
            floatval($meal['protein_g']),
            floatval($meal['carbs_g']),
            floatval($meal['fat_g'])
        ]);

        $inserted++;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Template applied to $inserted meal slots",
        "start_date" => $startDate
    ]);
}

function handleDeleteTemplate() {
    global $conn;
    $templateId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$templateId) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Template ID required"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM menu_templates WHERE id = ?");
    $stmt->execute([$templateId]);

    echo json_encode([
        "status" => "success",
        "message" => "Template deleted"
    ]);
}
?>
