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
    $messageId = $_GET['id'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if (!$messageId) {
        throw new Exception('Message ID is required');
    }
    
    // Get message details
    $query = "
        SELECT m.*, 
            sender.first_name as sender_first_name, 
            sender.last_name as sender_last_name,
            receiver.first_name as receiver_first_name,
            receiver.last_name as receiver_last_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.user_id
        JOIN users receiver ON m.receiver_id = receiver.user_id
        WHERE m.message_id = ? 
        AND (m.sender_id = ? OR m.receiver_id = ?)
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$messageId, $userId, $userId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        echo json_encode([
            'success' => true,
            'data' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Message not found or access denied'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error getting message: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get message. Please try again.'
    ]);
}
