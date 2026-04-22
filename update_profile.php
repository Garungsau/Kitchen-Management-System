<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'kitchen_staff', 'admin']);

$user_id = current_user_id();
$role = current_role();

$input_data = json_decode(file_get_contents('php://input'), true);
$phone = isset($_POST['phone']) ? $_POST['phone'] : ($input_data['phone'] ?? '');
$address = isset($_POST['present_address']) ? $_POST['present_address'] : (isset($_POST['address']) ? $_POST['address'] : ($input_data['address'] ?? ''));

if (empty($phone) && empty($address) && empty($_FILES['photo'])) {
    echo json_encode(["status" => "error", "message" => "No changes detected"]);
    exit();
}

try {
    $conn->beginTransaction();

    if ($role === 'student' || $role === 'employee') {
        if (!empty($phone)) {
            $stmt = $conn->prepare("UPDATE students SET phone = ? WHERE user_id = ?");
            $stmt->execute([$phone, $user_id]);
        }
        if (!empty($address)) {
            $stmt = $conn->prepare("UPDATE students SET present_address = ? WHERE user_id = ?");
            $stmt->execute([$address, $user_id]);
        }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                throw new Exception("Invalid file format. Only JPG, PNG, GIF allowed.");
            }

            $upload_dir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $new_filename = uniqid() . '.' . $ext;
            $target_path = $upload_dir . $new_filename;
            $db_path = 'uploads/' . $new_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE students SET photo_path = ? WHERE user_id = ?");
                $stmt->execute([$db_path, $user_id]);
            }
        }

    } elseif ($role === 'kitchen_staff') {
        if (!empty($phone) || !empty($address)) {
            $stmt = $conn->prepare("UPDATE kitchen_staff SET phone = ?, present_address = ? WHERE user_id = ?");
            $stmt->execute([$phone, $address, $user_id]);
        }
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Profile updated successfully!"]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>