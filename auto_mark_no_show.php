<?php
/**
 * API: Auto-mark No-Show
 * Purpose: Background task to mark registered meals as 'no_show' if check-in window closed
 * Run by: Cron job or triggered at login
 * Logic: If meal_date = today AND current_time > meal_start_time + 1hour AND status = 'registered'
 *        THEN mark as 'no_show'
 */

require_once 'config.php';

// This can be triggered by a cron job or at user login
// Example cron: 0 13 * * * php /path/to/api/auto_mark_no_show.php (run at 1 PM daily)

$meal_times = [
    'lunch' => '11:30',
    'dinner' => '17:30'
];

$NO_SHOW_GRACE_MINUTES = intval(getenv('NO_SHOW_GRACE_MINUTES') ?: 60); // minutes after meal start before marking no-show

try {
    $conn->beginTransaction();

    $today = date('Y-m-d');
    $current_time = new DateTime();

    // Compute cutoff: latest meal start + grace, so dinner is respected.
    $cutoff_candidates = [];
    foreach ($meal_times as $time) {
        $candidate = (new DateTime($today . ' ' . $time))->modify("+{$NO_SHOW_GRACE_MINUTES} minutes");
        $cutoff_candidates[] = $candidate;
    }
    usort($cutoff_candidates, function ($a, $b) {
        return $a->getTimestamp() <=> $b->getTimestamp();
    });
    $final_cutoff = end($cutoff_candidates);

    if ($current_time <= $final_cutoff) {
        $conn->rollBack();
        echo json_encode([
            "status" => "noop",
            "message" => "No-show marking skipped: cutoff window not reached yet.",
            "date" => $today,
            "cutoff_time" => $final_cutoff->format('H:i'),
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Find all registered meals for today
    $stmt = $conn->prepare("SELECT ma.id, ma.user_id, ma.meal_date, ma.status
                           FROM meal_attendance ma
                           WHERE ma.meal_date = ? AND ma.status = 'registered'");
    $stmt->execute([$today]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $no_show_count = 0;

    foreach ($meals as $meal) {
        // Mark as no-show after overall daily cutoff
        $update_stmt = $conn->prepare("UPDATE meal_attendance 
                                      SET status = 'no_show', is_active = 0
                                      WHERE id = ?");
        $update_stmt->execute([$meal['id']]);

        // NOTE: No-show penalty logic removed - meal costs calculated at month-end for payroll

        $no_show_count++;

        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO meal_registration_history 
                                   (user_id, meal_date, action, new_status, reason)
                                   VALUES (?, ?, 'auto_cancelled', 'no_show', ?)");
        $log_stmt->execute([
            $meal['user_id'],
            $meal['meal_date'],
            'Automatically marked no-show - daily check-in window closed'
        ]);
    }

    $conn->commit();

    // Return response (useful if triggered via HTTP)
    echo json_encode([
        "status" => "success",
        "message" => "Auto no-show marking completed",
        "date" => $today,
        "no_show_marked_count" => $no_show_count,
        "cutoff_time" => $final_cutoff->format('H:i'),
        "timestamp" => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
