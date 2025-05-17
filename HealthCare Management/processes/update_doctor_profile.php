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

// Update the doctors table
$stmt = $conn->prepare("
    UPDATE doctors 
    SET specialty = ?, contact_number = ?, availability_info = ?
    WHERE doctor_id = ?
");

$stmt->bind_param("sssi", $specialty, $contact_number, $availability_info, $doctor_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 