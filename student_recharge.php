<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

// Payments are disabled; keep endpoint for compatibility
echo json_encode([
    "status" => "error",
    "message" => "Wallet/recharge is disabled. Meal costs are handled outside the system."
]);
exit();
?>