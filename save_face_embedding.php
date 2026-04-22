<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'error',
    'message' => 'Endpoint da bi vo hieu hoa vi ly do bao mat. Vui long dung api/register_face.php (server-side embedding).'
]);
?>