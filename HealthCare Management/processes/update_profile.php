<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Database connection
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $patient_id = $_SESSION['user_id'];

    // Debug log
    error_log("Updating profile for user: " . $patient_id);
    error_log("POST data: " . print_r($_POST, true));

    // First check if patient record exists
    $check_stmt = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    if (!$check_stmt) {
        throw new Exception("Prepare check statement failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        // Patient record doesn't exist, create it
        $insert_stmt = $conn->prepare("INSERT INTO patients (patient_id) VALUES (?)");
        if (!$insert_stmt) {
            throw new Exception("Prepare insert statement failed: " . $conn->error);
        }
        
        $insert_stmt->bind_param("i", $patient_id);
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create patient record: " . $insert_stmt->error);
        }
        $insert_stmt->close();
    }
    $check_stmt->close();

    // Get all the form data with proper type casting
    $age = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
    $gender = $_POST['gender'] ?? null;
    $marital_status = $_POST['maritalStatus'] ?? null;
    $bio = $_POST['bio'] ?? null;
    $address = $_POST['address'] ?? null;
    $blood_type = $_POST['blood_type'] ?? null;
    $allergies = $_POST['allergies'] ?? null;
    $current_medications = $_POST['current_medications'] ?? null;
    $medical_history = $_POST['medical_history'] ?? null;
    $emergency_contact_name = $_POST['emergencyContactName'] ?? null;
    $emergency_contact_phone = $_POST['emergencyContactPhone'] ?? null;
    $emergency_contact_relationship = $_POST['emergencyContactRelationship'] ?? null;

    // Build the update query dynamically based on provided fields
    $updateFields = [];
    $params = [];
    $types = '';

    if (isset($_POST['age'])) {
        $updateFields[] = "age = ?";
        $params[] = $age;
        $types .= "i";
    }
    if (isset($_POST['gender'])) {
        $updateFields[] = "gender = ?";
        $params[] = $gender;
        $types .= "s";
    }
    if (isset($_POST['maritalStatus'])) {
        $updateFields[] = "marital_status = ?";
        $params[] = $marital_status;
        $types .= "s";
    }
    if (isset($_POST['bio'])) {
        $updateFields[] = "bio = ?";
        $params[] = $bio;
        $types .= "s";
    }
    if (isset($_POST['address'])) {
        $updateFields[] = "address = ?";
        $params[] = $address;
        $types .= "s";
    }
    if (isset($_POST['blood_type'])) {
        $updateFields[] = "blood_type = ?";
        $params[] = $blood_type;
        $types .= "s";
    }
    if (isset($_POST['allergies'])) {
        $updateFields[] = "allergies = ?";
        $params[] = $allergies;
        $types .= "s";
    }
    if (isset($_POST['current_medications'])) {
        $updateFields[] = "current_medications = ?";
        $params[] = $current_medications;
        $types .= "s";
    }
    if (isset($_POST['medical_history'])) {
        $updateFields[] = "medical_history = ?";
        $params[] = $medical_history;
        $types .= "s";
    }
    if (isset($_POST['emergencyContactName'])) {
        $updateFields[] = "emergency_contact_name = ?";
        $params[] = $emergency_contact_name;
        $types .= "s";
    }
    if (isset($_POST['emergencyContactPhone'])) {
        $updateFields[] = "emergency_contact_phone = ?";
        $params[] = $emergency_contact_phone;
        $types .= "s";
    }
    if (isset($_POST['emergencyContactRelationship'])) {
        $updateFields[] = "emergency_contact_relationship = ?";
        $params[] = $emergency_contact_relationship;
        $types .= "s";
    }

    // Add patient_id to params array
    $params[] = $patient_id;
    $types .= "i";

    if (!empty($updateFields)) {
        $sql = "UPDATE patients SET " . implode(", ", $updateFields) . " WHERE patient_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare update statement failed: " . $conn->error);
        }

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update profile: " . $stmt->error);
        }
        
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    error_log("Error in update_profile.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) $conn->close();
}
?> 