<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users

// Function to send JSON response and exit
function sendResponse($success, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['success' => $success];
    if (isset($data)) {
        $response = array_merge($response, is_array($data) ? $data : ['message' => $data]);
    }
    echo json_encode($response);
    exit();
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        sendResponse(false, ['error' => 'Not authenticated'], 401);
    }

    // Get the message ID from POST data
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $user_id = intval($_SESSION['user_id']);

    // Validate message ID
    if ($message_id <= 0) {
        sendResponse(false, ['error' => 'Invalid message ID'], 400);
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");

    // First check if the message exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM messages WHERE id = ? AND receiver_id = ?");
    if (!$checkStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param("ii", $message_id, $user_id);
    if (!$checkStmt->execute()) {
        throw new Exception('Database query failed: ' . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        sendResponse(false, ['error' => 'Message not found or access denied'], 404);
    }
    $checkStmt->close();

    // Update the message as read
    $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?");
    if (!$updateStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $updateStmt->bind_param("ii", $message_id, $user_id);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update message status: ' . $updateStmt->error);
    }
    
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();
    $conn->close();
    
    if ($affectedRows > 0) {
        sendResponse(true, ['message' => 'Message marked as read']);
    } else {
        // Message might already be marked as read
        sendResponse(true, ['message' => 'No changes made - message may already be read']);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in mark_message_read.php: ' . $e->getMessage());
    
    // Close any open database connections
    if (isset($conn)) {
        $conn->close();
    }
    
    // Send error response
    sendResponse(false, [
        'error' => 'An error occurred while processing your request',
        'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], 500);
}
