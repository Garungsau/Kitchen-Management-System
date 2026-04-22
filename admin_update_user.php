<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'];
$role = $input['role'];
$email = $input['email'];

// Fields
$full_name = $input['full_name'];
$phone = $input['phone'];
$hall_name = $input['hall_name'];
$dob = $input['dob'];
$blood_group = $input['blood_group'];
$gender = $input['gender'];
$father_name = $input['father_name'];
$mother_name = $input['mother_name'];
$present_address = $input['present_address'];
$permanent_address = $input['permanent_address'];
$nid = $input['nid'];

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $user_id]);

    if ($role === 'student' || $role === 'employee') {
        $student_id_no = $input['student_id_no'];
        $registration_no = $input['registration_no'];
        $department = $input['department'];

        $sql = "UPDATE students SET 
            full_name=?, phone=?, hall_name=?, dob=?, blood_group=?, gender=?, 
            father_name=?, mother_name=?, present_address=?, permanent_address=?, nid_no=?,
            student_id_no=?, registration_no=?, department=?
            WHERE user_id=?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $full_name, $phone, $hall_name, $dob, $blood_group, $gender, 
            $father_name, $mother_name, $present_address, $permanent_address, $nid,
            $student_id_no, $registration_no, $department, 
            $user_id
        ]);

    } else {
        $sql = "UPDATE kitchen_staff SET 
            full_name=?, phone=?, hall_name=?, dob=?, blood_group=?, gender=?, 
            father_name=?, mother_name=?, present_address=?, permanent_address=?, nid_number=?
            WHERE user_id=?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $full_name, $phone, $hall_name, $dob, $blood_group, $gender, 
            $father_name, $mother_name, $present_address, $permanent_address, $nid,
            $user_id
        ]);
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "User updated successfully!"]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["status" => "error", "message" => "Update failed: " . $e->getMessage()]);
}
?>