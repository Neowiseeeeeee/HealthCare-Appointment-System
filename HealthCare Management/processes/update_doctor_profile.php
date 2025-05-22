<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$doctor_id = $_SESSION['user_id'];
$specialty = $_POST['specialty'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';
$availability_info = $_POST['availability_info'] ?? '';
$experience = $_POST['experience'] ?? '';

// Handle profile picture upload
$profile_picture_path = null;
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/profile_pictures';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed']);
        exit();
    }
    
    // Validate file size (max 5MB)
    if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB']);
        exit();
    }
    
    // Generate a unique filename
    $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
    $filename = $doctor_id . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
        $profile_picture_path = 'uploads/profile_pictures/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
        exit();
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // First, check if the experience column exists
    $result = $conn->query("SHOW COLUMNS FROM doctors LIKE 'experience'");
    if ($result->num_rows === 0) {
        // Add experience column if it doesn't exist
        $conn->query("ALTER TABLE doctors ADD COLUMN experience VARCHAR(255) NULL");
    }

    // Update the doctors table
    $stmt = $conn->prepare("
        UPDATE doctors 
        SET specialty = ?, contact_number = ?, availability_info = ?, experience = ?
        WHERE doctor_id = ?
    ");
    
    $stmt->bind_param("ssssi", $specialty, $contact_number, $availability_info, $experience, $doctor_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to the profile');
    }
    
    $stmt->close();
    
    // Update profile picture if uploaded
    if ($profile_picture_path) {
        // Check if profile_picture column exists in doctors table
        $result = $conn->query("SHOW COLUMNS FROM doctors LIKE 'profile_picture'");
        
        if ($result->num_rows === 0) {
            // Column doesn't exist, add it
            $conn->query("ALTER TABLE doctors ADD COLUMN profile_picture VARCHAR(255) NULL");
        }
        
        // Update the profile picture
        $stmt = $conn->prepare("UPDATE doctors SET profile_picture = ? WHERE doctor_id = ?");
        $stmt->bind_param("si", $profile_picture_path, $doctor_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get the updated profile data
    $result = $conn->query("SELECT profile_picture FROM doctors WHERE doctor_id = $doctor_id");
    $profile_data = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile updated successfully',
        'profile_picture' => $profile_data['profile_picture'] ?? null
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating profile: ' . $e->getMessage()
    ]);
}

$conn->close();
?>