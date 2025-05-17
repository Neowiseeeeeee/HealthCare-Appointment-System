<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug.log');

header('Content-Type: application/json');

// Debug session and request
error_log("\n\n=== New Request Start ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Session Data: " . print_r($_SESSION, true));

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

    // Get patient data
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email
        FROM patients p
        JOIN users u ON p.patient_id = u.user_id
        WHERE p.patient_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $patient_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();

    if ($patient_data) {
        // Format the response data
        $response = [
            'success' => true,
            'data' => [
                'user_id' => $patient_id,
                'name' => $patient_data['first_name'] . ' ' . $patient_data['last_name'],
                'email' => $patient_data['email'],
                'role' => 'patient',
                'age' => $patient_data['age'],
                'gender' => $patient_data['gender'],
                'marital_status' => $patient_data['marital_status'],
                'bio' => $patient_data['bio'],
                'address' => $patient_data['address'],
                'blood_type' => $patient_data['blood_type'],
                'allergies' => $patient_data['allergies'],
                'current_medications' => $patient_data['current_medications'],
                'medical_history' => $patient_data['medical_history'],
                'emergency_contact_name' => $patient_data['emergency_contact_name'],
                'emergency_contact_phone' => $patient_data['emergency_contact_phone'],
                'emergency_contact_relationship' => $patient_data['emergency_contact_relationship'],
                'picture_path' => $patient_data['picture_path'] ?? 'Pictures/Logo.jpg'
            ]
        ];
    } else {
        // If no patient record exists yet, return basic user data
        $response = [
            'success' => true,
            'data' => [
                'user_id' => $patient_id,
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'role' => 'patient',
                'picture_path' => 'Pictures/Logo.jpg'
            ]
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_patient_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch patient data: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?> 