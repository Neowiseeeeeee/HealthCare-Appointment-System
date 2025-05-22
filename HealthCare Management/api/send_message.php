<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['receiver_id'], $input['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = intval($input['receiver_id']);
$subject = isset($input['subject']) ? trim($input['subject']) : '';
$content = trim($input['content']);
$in_reply_to = isset($input['in_reply_to']) ? intval($input['in_reply_to']) : null;

// Basic validation
if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Message content cannot be empty']);
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Insert the message
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, content, in_reply_to) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $sender_id, $receiver_id, $subject, $content, $in_reply_to);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to send message: " . $stmt->error);
    }
    
    $message_id = $conn->insert_id;
    
    // Create a notification for the receiver
    $notification_content = "You have a new message" . (!empty($subject) ? " regarding: $subject" : "");
    $stmt = $conn->prepare("INSERT INTO notifications (sender_id, title, content, is_system) VALUES (?, ?, ?, 0)");
    $notification_title = "New Message" . (!empty($subject) ? ": " . $subject : "");
    $stmt->bind_param("iss", $sender_id, $notification_title, $notification_content);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create notification: " . $stmt->error);
    }
    
    $notification_id = $conn->insert_id;
    
    // Link notification to receiver
    $stmt = $conn->prepare("INSERT INTO user_notifications (user_id, notification_id, is_read) VALUES (?, ?, 0)");
    $stmt->bind_param("ii", $receiver_id, $notification_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to link notification to user: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get the created message with sender/receiver info
    $stmt = $conn->prepare("
        SELECT m.*, 
               CONCAT(s.first_name, ' ', s.last_name) as sender_name,
               CONCAT(r.first_name, ' ', r.last_name) as receiver_name
        FROM messages m
        JOIN users s ON m.sender_id = s.user_id
        JOIN users r ON m.receiver_id = r.user_id
        WHERE m.message_id = ?
    ");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $message
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error sending message: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
