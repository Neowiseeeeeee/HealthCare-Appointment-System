<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if message ID and reply are provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['message_id']) || !is_numeric($_POST['message_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit();
}

if (!isset($_POST['reply']) || trim($_POST['reply']) === '') {
    echo json_encode(['success' => false, 'message' => 'Reply text is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$message_id = $_POST['message_id'];
$reply_text = trim($_POST['reply']);

try {
    // First, check if the message belongs to the current user
    $check_sql = "SELECT * FROM messages WHERE message_id = :message_id AND receiver_id = :user_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':message_id' => $message_id,
        ':user_id' => $user_id
    ]);
    
    $message = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message not found or access denied']);
        exit();
    }
    
    // Check if a reply already exists
    $check_reply_sql = "SELECT * FROM message_replies WHERE message_id = :message_id";
    $check_reply_stmt = $pdo->prepare($check_reply_sql);
    $check_reply_stmt->execute([':message_id' => $message_id]);
    
    $existing_reply = $check_reply_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_reply) {
        // Update existing reply
        $update_sql = "UPDATE message_replies SET content = :content, created_at = NOW() WHERE message_id = :message_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':content' => $reply_text,
            ':message_id' => $message_id
        ]);
    } else {
        // Insert new reply
        $insert_sql = "INSERT INTO message_replies (message_id, content, created_at) VALUES (:message_id, :content, NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            ':message_id' => $message_id,
            ':content' => $reply_text
        ]);
    }
    
    // Mark the original message as read
    $mark_read_sql = "UPDATE messages SET is_read = 1 WHERE message_id = :message_id";
    $mark_read_stmt = $pdo->prepare($mark_read_sql);
    $mark_read_stmt->execute([':message_id' => $message_id]);
    
    // Create notification for the doctor (sender of the original message)
    $notification_sql = "INSERT INTO notifications (title, content, type, sender_id, created_at) 
                         VALUES (:title, :content, 'message', :sender_id, NOW())";
    $notification_stmt = $pdo->prepare($notification_sql);
    $notification_stmt->execute([
        ':title' => 'New Message Reply',
        ':content' => 'You have received a reply to your message',
        ':sender_id' => $user_id
    ]);
    
    $notification_id = $pdo->lastInsertId();
    
    // Link notification to the doctor
    $user_notification_sql = "INSERT INTO user_notifications (notification_id, user_id, is_read, created_at) 
                             VALUES (:notification_id, :user_id, 0, NOW())";
    $user_notification_stmt = $pdo->prepare($user_notification_sql);
    $user_notification_stmt->execute([
        ':notification_id' => $notification_id,
        ':user_id' => $message['sender_id']
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully'
    ]);
    
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>