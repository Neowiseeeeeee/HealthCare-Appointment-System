<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get the user ID
$user_id = $_SESSION['user_id'];

// Set header to JSON
header('Content-Type: application/json');

try {
    // Get notifications from the database with joined user notification info
    $notifications_query = "
        SELECT n.*, un.is_read
        FROM notifications n
        LEFT JOIN user_notifications un ON n.notification_id = un.notification_id AND un.user_id = :user_id
        ORDER BY n.created_at DESC 
        LIMIT 10
    ";
    
    $notifications_stmt = $pdo->prepare($notifications_query);
    $notifications_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get messages from database
    $messages_query = "
        SELECT m.*, 
               sender.first_name as sender_first_name, 
               sender.last_name as sender_last_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.user_id
        WHERE m.receiver_id = :user_id
        ORDER BY m.created_at DESC
        LIMIT 5
    ";
    
    $messages_stmt = $pdo->prepare($messages_query);
    $messages_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $messages_stmt->execute();
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format notifications
    $combined_data = [];
    
    foreach ($notifications as $notif) {
        // Get sender name
        $sender_name = 'System';
        if (!empty($notif['sender_id'])) {
            // Get sender information from users table
            $sender_query = "SELECT first_name, last_name FROM users WHERE user_id = :sender_id";
            $sender_stmt = $pdo->prepare($sender_query);
            $sender_stmt->bindParam(':sender_id', $notif['sender_id'], PDO::PARAM_INT);
            $sender_stmt->execute();
            $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sender) {
                $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
            }
        }
        
        $combined_data[] = [
            'id' => 'n_' . $notif['notification_id'],
            'type' => 'notification',
            'subtype' => $notif['type'],
            'title' => $notif['title'] ?? 'Notification',
            'content' => $notif['content'],
            'sender' => $sender_name,
            'is_read' => (bool)($notif['is_read'] ?? false),
            'date' => $notif['created_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($notif['created_at']))
        ];
    }
    
    // Format messages
    foreach ($messages as $message) {
        $sender_name = $message['sender_first_name'] . ' ' . $message['sender_last_name'];
        
        $combined_data[] = [
            'id' => 'm_' . $message['message_id'],
            'type' => 'message',
            'title' => $message['subject'] ?? 'No Subject',
            'content' => $message['content'],
            'sender' => $sender_name,
            'sender_id' => $message['sender_id'],
            'is_read' => (bool)($message['is_read'] ?? false),
            'date' => $message['created_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($message['created_at']))
        ];
    }
    
    // Sort by date (newest first)
    usort($combined_data, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Count different types
    $message_count = count(array_filter($combined_data, function($item) { 
        return $item['type'] === 'message'; 
    }));
    
    $notification_count = count(array_filter($combined_data, function($item) { 
        return $item['type'] === 'notification'; 
    }));
    
    $unread_count = count(array_filter($combined_data, function($item) { 
        return !$item['is_read']; 
    }));
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'data' => $combined_data,
        'counts' => [
            'total' => count($combined_data),
            'messages' => $message_count,
            'notifications' => $notification_count,
            'unread' => $unread_count
        ]
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in fetch_all_notifications.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_details' => $e->getMessage()
    ]);
}
?>