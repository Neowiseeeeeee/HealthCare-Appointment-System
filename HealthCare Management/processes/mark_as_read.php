<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? null;

// Validate input
if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Mark message as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND receiver_id = ?");
$stmt->bind_param("ii", $message_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark message as read']);
}

$stmt->close();
$conn->close();
?>
