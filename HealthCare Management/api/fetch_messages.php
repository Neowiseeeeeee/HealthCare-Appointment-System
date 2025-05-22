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
    $user_id = $_SESSION['user_id'];
    
    // Get both sent and received messages
    $query = "
        SELECT 
            m.message_id,
            m.sender_id,
            m.receiver_id,
            m.subject,
            m.content,
            m.created_at,
            m.is_read,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            receiver.first_name as receiver_first_name,
            receiver.last_name as receiver_last_name,
            'message' as type
        FROM messages m
        JOIN users sender ON m.sender_id = sender.user_id
        JOIN users receiver ON m.receiver_id = receiver.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formattedMessages = array_map(function($msg) use ($user_id) {
        $isSent = $msg['sender_id'] == $user_id;
        $otherUser = $isSent 
            ? ['id' => $msg['receiver_id'], 'name' => $msg['receiver_first_name'] . ' ' . $msg['receiver_last_name']]
            : ['id' => $msg['sender_id'], 'name' => $msg['sender_first_name'] . ' ' . $msg['sender_last_name']];
        
        return [
            'id' => 'msg_' . $msg['message_id'],
            'type' => 'message',
            'title' => $isSent ? 'To: ' . $otherUser['name'] : 'From: ' . $otherUser['name'],
            'message' => strlen($msg['content']) > 50 ? substr($msg['content'], 0, 50) . '...' : $msg['content'],
            'full_message' => $msg['content'],
            'timestamp' => $msg['created_at'],
            'is_read' => (bool)$msg['is_read'],
            'is_sent' => $isSent,
            'other_user' => $otherUser,
            'subject' => $msg['subject']
        ];
    }, $messages);
    
    // Count unread messages
    $unreadCount = count(array_filter($formattedMessages, function($msg) use ($user_id) {
        return !$msg['is_read'] && $msg['sender_id'] != $user_id;
    }));
    
    echo json_encode([
        'success' => true,
        'data' => $formattedMessages,
        'unread_count' => $unreadCount
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching messages: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch messages. Please try again.'
    ]);
}
