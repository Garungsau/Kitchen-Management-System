<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

try {
    $countStmt = $conn->query("SELECT COUNT(*) FROM transactions");
    $total_rows = $countStmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT t.*, s.full_name, s.student_id_no 
            FROM transactions t 
            LEFT JOIN students s ON t.user_id = s.user_id
            ORDER BY t.created_at DESC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $transactions,
        "total_pages" => $total_pages,
        "current_page" => $page
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>