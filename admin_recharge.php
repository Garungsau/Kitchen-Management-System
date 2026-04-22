<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

// Payments are deprecated; keep endpoint but return informative error
echo json_encode([
    "status" => "error",
    "message" => "Wallet/recharge is disabled. Meal costs are handled outside the system."
]);
exit();
?>