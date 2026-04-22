<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

function parse_embedding($raw)
{
    if ($raw === null) {
        return null;
    }

    if (is_array($raw)) {
        return $raw;
    }

    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (strpos($raw, ',') !== false) {
        $parts = array_map('trim', explode(',', $raw));
        $vals = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $vals[] = floatval($p);
        }
        return $vals;
    }

    return null;
}

function get_rows_from_query($conn, $sql)
{
    try {
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$source_rows = [];

$queries = [
    "SELECT s.student_id_no AS employee_id, s.full_name AS name, s.department,
            fe.embedding_json AS embedding
     FROM face_embeddings fe
     JOIN students s ON s.user_id = fe.user_id
         WHERE fe.embedding_json IS NOT NULL AND (fe.is_active = 1 OR fe.is_active IS NULL)",

    "SELECT s.student_id_no AS employee_id, s.full_name AS name, s.department,
            fe.embedding AS embedding
     FROM face_embeddings fe
     JOIN students s ON s.user_id = fe.user_id
         WHERE fe.embedding IS NOT NULL AND (fe.is_active = 1 OR fe.is_active IS NULL)",

    "SELECT s.student_id_no AS employee_id, s.full_name AS name, s.department,
            s.face_embedding AS embedding
     FROM students s
     WHERE s.face_embedding IS NOT NULL"
];

foreach ($queries as $sql) {
    $rows = get_rows_from_query($conn, $sql);
    if (!empty($rows)) {
        $source_rows = $rows;
        break;
    }
}

$result = [];
foreach ($source_rows as $row) {
    $embedding = parse_embedding($row['embedding'] ?? null);
    if (!$embedding || count($embedding) < 64) {
        continue;
    }

    $result[] = [
        'employee_id' => (string)($row['employee_id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'department' => (string)($row['department'] ?? '-'),
        'embedding' => array_map('floatval', $embedding)
    ];
}

echo json_encode($result);
?>