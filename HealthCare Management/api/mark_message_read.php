<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';
global $pdo;

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $messageId = $input['message_id'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if (!$messageId) {
        throw new Exception('Message ID is required');
    }
    
    // Update the message as read
    $query = "
        UPDATE messages 
        SET is_read = 1 
        WHERE message_id = :message_id 
        AND receiver_id = :user_id
        AND is_read = 0
    ";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':message_id' => $messageId,
        ':user_id' => $userId
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found or already read']);
    }
    
} catch (Exception $e) {
    error_log('Error marking message as read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark message as read. Please try again.'
    ]);
}
