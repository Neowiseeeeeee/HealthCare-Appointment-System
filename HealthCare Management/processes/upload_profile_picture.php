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

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['profile_picture'];
$patient_id = $_SESSION['user_id'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed']);
    exit();
}

// Validate file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB']);
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/profile_pictures';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $patient_id . '_' . time() . '.' . $extension;
$filepath = $upload_dir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

try {
    // Database connection
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Store the relative path in the database
    $db_path = 'uploads/profile_pictures/' . $filename;
    
    // First check if patient record exists
    $check_stmt = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Insert new patient record
        $insert_stmt = $conn->prepare("INSERT INTO patients (patient_id, picture_path) VALUES (?, ?)");
        $insert_stmt->bind_param("is", $patient_id, $db_path);
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create patient record: " . $insert_stmt->error);
        }
    } else {
        // Update existing patient record
        $update_stmt = $conn->prepare("UPDATE patients SET picture_path = ? WHERE patient_id = ?");
        $update_stmt->bind_param("si", $db_path, $patient_id);
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update patient record: " . $update_stmt->error);
        }
    }

    // Return success response with the web-accessible path
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'picture_path' => $db_path
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Profile picture upload error: " . $e->getMessage());
    
    // Delete the uploaded file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile picture: ' . $e->getMessage()
    ]);
} finally {
    // Close database connections
    if (isset($check_stmt)) $check_stmt->close();
    if (isset($insert_stmt)) $insert_stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    if (isset($conn)) $conn->close();
}
?> 