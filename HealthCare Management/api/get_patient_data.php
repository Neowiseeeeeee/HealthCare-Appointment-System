<?php
// Set CORS headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configure session parameters
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

// Start the session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug.log');

// Debug session and request
error_log("\n\n=== New Request Start ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Session Data: " . print_r($_SESSION, true));
error_log("Request Headers: " . print_r(getallheaders(), true));

// Function to send JSON response and exit
function sendJsonResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id'])) {
    error_log("Error: User not logged in");
    sendJsonResponse(false, 'Please log in to access this resource', null, 401);
}

if ($_SESSION['role'] !== 'patient') {
    error_log("Error: User is not a patient. Role: " . ($_SESSION['role'] ?? 'not set'));
    sendJsonResponse(false, 'Access denied. Patient role required.', null, 403);
}

try {
    // Database connection
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");

    $patient_id = (int)$_SESSION['user_id'];
    error_log("Fetching data for patient ID: " . $patient_id);

    // First, check if user exists in users table
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed (users): " . $conn->error);
    }
    $stmt->bind_param("i", $patient_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (users): " . $stmt->error);
    }
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        throw new Exception("User not found in users table");
    }

    // Get patient data
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email, u.role
        FROM users u
        LEFT JOIN patients p ON u.user_id = p.patient_id
        WHERE u.user_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed (patients): " . $conn->error);
    }

    $stmt->bind_param("i", $patient_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (patients): " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();
    
    error_log("Patient data from database: " . print_r($patient_data, true));

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
                'picture_path' => $patient_data['picture_path'] ?? 'assets/images/logo.jpg'
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
    sendJsonResponse(false, 'An error occurred while fetching patient data: ' . $e->getMessage(), null, 500);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?> 