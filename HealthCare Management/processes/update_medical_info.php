<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$blood_type = $_POST['blood_type'] ?? null;
$allergies = $_POST['allergies'] ?? null;
$current_medications = $_POST['current_medications'] ?? null;
$medical_history = $_POST['medical_history'] ?? null;

// Prepare and execute update query
$stmt = $conn->prepare("UPDATE users SET blood_type = ?, allergies = ?, current_medications = ?, medical_history = ? WHERE user_id = ?");
$stmt->bind_param("ssssi", $blood_type, $allergies, $current_medications, $medical_history, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Medical information updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update medical information']);
}

$stmt->close();
$conn->close();
?> 