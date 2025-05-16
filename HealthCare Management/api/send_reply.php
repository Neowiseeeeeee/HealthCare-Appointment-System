<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message_id']) || !isset($data['reply_text']) || empty(trim($data['reply_text']))) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$message_id = intval($data['message_id']);
$reply_text = trim($data['reply_text']);

try {
    // First get the original message to verify access and get recipient
    $sql = "SELECT m.*, u.first_name, u.last_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.message_id = :message_id 
            AND (m.receiver_id = :user_id OR m.sender_id = :user_id)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':message_id' => $message_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    $original_message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_message) {
        echo json_encode(['success' => false, 'message' => 'Original message not found or access denied']);
        exit();
    }

    // Determine the recipient (if current user was receiver, send to original sender and vice versa)
    $recipient_id = ($original_message['receiver_id'] == $_SESSION['user_id']) 
        ? $original_message['sender_id'] 
        : $original_message['receiver_id'];

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Insert the reply
        $sql = "INSERT INTO messages (sender_id, receiver_id, subject, content, parent_message_id, created_at) 
                VALUES (:sender_id, :receiver_id, :subject, :content, :parent_message_id, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sender_id' => $_SESSION['user_id'],
            ':receiver_id' => $recipient_id,
            ':subject' => 'Re: ' . ($original_message['subject'] ?? 'No Subject'),
            ':content' => $reply_text,
            ':parent_message_id' => $message_id
        ]);

        // Create notification for recipient
        $sql = "INSERT INTO notifications (recipient_id, type, title, content, created_at) 
                VALUES (:recipient_id, 'message', 'New Message Reply', :content, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':recipient_id' => $recipient_id,
            ':content' => "You have received a reply from " . $_SESSION['name']
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Reply sent successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 