<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get message ID from request
$message_id = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit();
}

try {
    // Get the main message
    $sql = "SELECT m.*, 
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.email as sender_email
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.message_id = :message_id 
            AND (m.receiver_id = :user_id OR m.sender_id = :user_id)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':message_id' => $message_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit();
    }

    // Get thread messages (replies)
    $sql = "SELECT m.*,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.email as sender_email
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.parent_message_id = :message_id
            ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':message_id' => $message_id]);
    $thread = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark message as read if recipient is viewing
    if ($message['receiver_id'] == $_SESSION['user_id'] && !$message['is_read']) {
        $sql = "UPDATE messages SET is_read = 1 WHERE message_id = :message_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':message_id' => $message_id]);
    }

    // Format response
    $response = [
        'success' => true,
        'message' => [
            'message_id' => $message['message_id'],
            'subject' => $message['subject'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
            'sender_name' => $message['sender_name'],
            'sender_email' => $message['sender_email'],
            'is_read' => (bool)$message['is_read'],
            'thread' => array_map(function($reply) {
                return [
                    'message_id' => $reply['message_id'],
                    'content' => $reply['content'],
                    'created_at' => $reply['created_at'],
                    'sender_name' => $reply['sender_name'],
                    'sender_email' => $reply['sender_email'],
                    'is_read' => (bool)$reply['is_read']
                ];
            }, $thread)
        ]
    ];

    echo json_encode($response);

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 