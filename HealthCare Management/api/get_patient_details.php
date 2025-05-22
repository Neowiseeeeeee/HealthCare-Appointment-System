<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if patient_id is provided
if (!isset($_GET['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit();
}

try {
    // Database connection
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $patient_id = $_GET['patient_id'];
    $doctor_id = $_SESSION['user_id'];

    // Verify that this patient has an appointment with the doctor
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM appointments
        WHERE doctor_id = ? AND patient_id = ?
    ");
    $check_stmt->bind_param("ii", $doctor_id, $patient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] == 0) {
        throw new Exception("Unauthorized to view this patient's details");
    }

    // Get patient details
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            p.age,
            p.gender,
            p.marital_status,
            p.bio,
            p.address,
            p.blood_type,
            p.allergies,
            p.current_medications,
            p.medical_history,
            p.emergency_contact_name,
            p.emergency_contact_phone,
            p.emergency_contact_relationship,
            p.picture_path,
            (SELECT COUNT(*) FROM appointments 
             WHERE patient_id = u.user_id 
             AND doctor_id = ?) as appointment_count,
            (SELECT COUNT(*) FROM appointments 
             WHERE patient_id = u.user_id 
             AND doctor_id = ? 
             AND status = 'completed') as completed_appointments
        FROM users u
        LEFT JOIN patients p ON u.user_id = p.patient_id
        WHERE u.user_id = ?
    ");

    $stmt->bind_param("iii", $doctor_id, $doctor_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();

    if (!$patient_data) {
        throw new Exception("Patient not found");
    }

    // Format the response data
    $response = [
        'success' => true,
        'data' => [
            'user_id' => $patient_data['user_id'],
            'name' => $patient_data['first_name'] . ' ' . $patient_data['last_name'],
            'email' => $patient_data['email'],
            'age' => $patient_data['age'],
            'gender' => $patient_data['gender'],
            'marital_status' => $patient_data['marital_status'],
            'bio' => $patient_data['bio'],
            'address' => $patient_data['address'],
            'blood_type' => $patient_data['blood_type'],
            'allergies' => $patient_data['allergies'],
            'current_medications' => $patient_data['current_medications'],
            'medical_history' => $patient_data['medical_history'],
            'emergency_contact' => [
                'name' => $patient_data['emergency_contact_name'],
                'phone' => $patient_data['emergency_contact_phone'],
                'relationship' => $patient_data['emergency_contact_relationship']
            ],
            'picture_path' => $patient_data['picture_path'] ?? 'assets/images/logo.jpg',
            'stats' => [
                'total_appointments' => $patient_data['appointment_count'],
                'completed_appointments' => $patient_data['completed_appointments']
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_patient_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($check_stmt)) $check_stmt->close();
    if (isset($conn)) $conn->close();
}
?> 