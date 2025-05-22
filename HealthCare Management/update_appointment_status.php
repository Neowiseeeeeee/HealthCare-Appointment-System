<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['appointment_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$appointment_id = $_POST['appointment_id'];
$status = $_POST['status'];

// Validate status
$valid_statuses = ['pending', 'approved', 'rejected', 'completed'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Prepare and execute the update query
$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
$stmt->bind_param("si", $status, $appointment_id);

$response = [];
if ($stmt->execute()) {
    $response = ['success' => true, 'message' => 'Appointment status updated successfully'];
} else {
    $response = ['success' => false, 'message' => 'Failed to update appointment status: ' . $conn->error];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>
