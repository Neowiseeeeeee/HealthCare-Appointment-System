<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if appointment_id is provided
if (!isset($_POST['appointment_id']) || empty($_POST['appointment_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

$appointment_id = intval($_POST['appointment_id']);
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Verify the appointment belongs to the logged-in patient
$verifyQuery = "SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param("ii", $appointment_id, $user_id);
$verifyStmt->execute();
$result = $verifyStmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment not found or does not belong to you']);
    $verifyStmt->close();
    $conn->close();
    exit();
}

// Update appointment status to 'cancelled'
$updateQuery = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("i", $appointment_id);
$success = $updateStmt->execute();

if ($success) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment: ' . $conn->error]);
}

$verifyStmt->close();
$updateStmt->close();
$conn->close();
?>