<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

const RECOGNITION_DISTANCE_THRESHOLD = 0.60;
const FACE_MIN_EMBED_DIM = 64;
const FACE_MAX_EMBED_DIM = 512;

function decode_data_url_image(string $dataUrl, ?string &$ext = null): ?string {
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl, $m)) {
        return null;
    }
    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    $base64 = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $dataUrl);
    $binary = base64_decode((string)$base64, true);
    return ($binary === false) ? null : $binary;
}

function save_watermarked_image(string $dataUrl, string $folder, string $prefix): ?string {
    $ext = null;
    $binary = decode_data_url_image($dataUrl, $ext);
    if ($binary === null) {
        return null;
    }

    $image = @imagecreatefromstring($binary);
    if (!$image) {
        return null;
    }

    $w = imagesx($image);
    $h = imagesy($image);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($image);
        return null;
    }

    $label = 'TS ' . date('Y-m-d H:i:s');
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 55);
    $text = imagecolorallocate($image, 255, 255, 255);
    $textY = max(10, $h - 20);
    imagefilledrectangle($image, 6, $textY - 4, min($w - 6, 260), $textY + 16, $shadow);
    imagestring($image, 3, 10, $textY, $label, $text);

    $dirAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces' . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0777, true)) {
        imagedestroy($image);
        return null;
    }

    $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $fileAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
    $ok = imagejpeg($image, $fileAbs, 88);
    imagedestroy($image);

    if (!$ok) {
        return null;
    }

    return 'assets/uploads/faces/' . $folder . '/' . $name;
}

function extract_embedding_server_side(string $imageRelativePath): ?array {
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'face_embedding_server.py';
    if (!is_file($script)) {
        return null;
    }

    $imageAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imageRelativePath);
    $commands = [
        'python ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs),
        'py -3 ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs),
        'py ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs)
    ];

    foreach ($commands as $cmd) {
        $output = @shell_exec($cmd . ' 2>&1');
        if (!is_string($output) || trim($output) === '') {
            continue;
        }
        $json = json_decode(trim($output), true);
        if (!is_array($json) || ($json['status'] ?? '') !== 'success' || !is_array($json['embedding'] ?? null)) {
            continue;
        }
        $embedding = array_map('floatval', $json['embedding']);
        $dim = count($embedding);
        if ($dim < FACE_MIN_EMBED_DIM || $dim > FACE_MAX_EMBED_DIM) {
            continue;
        }
        return $embedding;
    }

    return null;
}

function euclidean_distance(array $a, array $b): float {
    $len = min(count($a), count($b));
    if ($len <= 0) {
        return PHP_FLOAT_MAX;
    }

    $sum = 0.0;
    for ($i = 0; $i < $len; $i++) {
        $d = ((float)$a[$i]) - ((float)$b[$i]);
        $sum += $d * $d;
    }
    return sqrt($sum);
}

$input = json_decode(file_get_contents('php://input'), true);
$imageDataUrl = trim((string)($input['image_data_url'] ?? $input['snapshot_data_url'] ?? ''));

if ($imageDataUrl === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu ảnh khuôn mặt. Vui lòng gửi image_data_url để nhận diện server-side.'
    ]);
    exit;
}

try {
    $savedPath = save_watermarked_image($imageDataUrl, 'checkin', 'chk');
    if ($savedPath === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ảnh khuôn mặt không hợp lệ.']);
        exit;
    }

    $capturedEmbedding = extract_embedding_server_side($savedPath);
    if ($capturedEmbedding === null) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Không thể trích xuất embedding trên server.']);
        exit;
    }

    $stmt = $conn->query(
        "SELECT u.id AS user_id, u.email, u.is_approved, u.is_blocked,
                s.full_name, s.photo_path, f.face_encodings
         FROM user_face_data f
         JOIN users u ON u.id = f.user_id
         LEFT JOIN students s ON s.user_id = u.id
         WHERE u.role = 'employee' AND u.is_approved = 1 AND u.is_blocked = 0"
    );

    $matches = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storedList = json_decode((string)($row['face_encodings'] ?? '[]'), true);
        if (!is_array($storedList) || empty($storedList)) {
            continue;
        }

        $best = PHP_FLOAT_MAX;
        foreach ($storedList as $stored) {
            if (!is_array($stored)) {
                continue;
            }
            $distance = euclidean_distance($capturedEmbedding, $stored);
            if ($distance < $best) {
                $best = $distance;
            }
        }

        if ($best < RECOGNITION_DISTANCE_THRESHOLD) {
            $confidence = max(0, min(100, (1 - ($best / RECOGNITION_DISTANCE_THRESHOLD)) * 100));
            $matches[] = [
                'user_id' => (int)$row['user_id'],
                'name' => (string)($row['full_name'] ?? 'Unknown'),
                'email' => (string)($row['email'] ?? ''),
                'avatar' => (string)($row['photo_path'] ?? ''),
                'distance' => round($best, 4),
                'confidence' => round($confidence, 2)
            ];
        }
    }

    usort($matches, static function ($a, $b) {
        return ($b['confidence'] <=> $a['confidence']);
    });

    if (empty($matches)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'not_found',
            'message' => 'No matching face found',
            'captured_image' => $savedPath
        ]);
        exit;
    }

    $top = $matches[0];
    echo json_encode([
        'status' => 'success',
        'message' => 'Face recognized (server-side embedding)',
        'matched_user' => $top,
        'alternative_matches' => array_slice($matches, 1, 2),
        'captured_image' => $savedPath
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Recognition error: ' . $e->getMessage()]);
}
?><?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'auth.php';

// Kitchen staff/admin only
require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['face_descriptor']) || !is_array($input['face_descriptor'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid face descriptor']);
    exit;
}

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Convert input descriptor to array of floats
    $capturedDescriptor = array_map('floatval', $input['face_descriptor']);

    // Get all registered faces
    $stmt = $db->query("
        SELECT u.id, u.name, u.email, u.avatar, u.status, f.face_encodings
        FROM user_face_data f
        JOIN users u ON f.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY f.updated_date DESC
    ");

    $matches = [];
    $DISTANCE_THRESHOLD = 0.6; // Euclidean distance threshold for face match

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storedEncodings = json_decode($row['face_encodings'], true);
        
        if (!is_array($storedEncodings) || empty($storedEncodings)) {
            continue;
        }

        // Calculate distance to each stored encoding
        $minDistance = PHP_FLOAT_MAX;
        
        foreach ($storedEncodings as $stored) {
            $stored = array_map('floatval', $stored);
            
            // Euclidean distance
            $distance = 0;
            for ($i = 0; $i < min(count($capturedDescriptor), count($stored)); $i++) {
                $diff = $capturedDescriptor[$i] - $stored[$i];
                $distance += $diff * $diff;
            }
            
            $distance = sqrt($distance);
            $minDistance = min($minDistance, $distance);
        }

        // If distance is below threshold, it's a match
        if ($minDistance < $DISTANCE_THRESHOLD) {
            $matches[] = [
                'user_id' => (int)$row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'avatar' => $row['avatar'],
                'distance' => round($minDistance, 4),
                'confidence' => round((1 - ($minDistance / $DISTANCE_THRESHOLD)) * 100, 2)
            ];
        }
    }

    // Sort by confidence (best match first)
    usort($matches, function($a, $b) {
        return $b['confidence'] - $a['confidence'];
    });

    if (empty($matches)) {
        http_response_code(404);
        echo json_encode([
            'status' => 'not_found',
            'message' => 'No matching face found'
        ]);
    } else {
        // Return top match
        $topMatch = $matches[0];
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Face recognized',
            'matched_user' => $topMatch,
            'alternative_matches' => array_slice($matches, 1, 2) // Show alternatives
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Recognition error: ' . $e->getMessage()
    ]);
}
?>
