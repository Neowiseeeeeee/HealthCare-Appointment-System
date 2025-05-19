<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required fields are provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get form data
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['recipient_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['message'] ?? '');

    // Validate input
    if (empty($receiver_id) || empty($subject) || empty($content)) {
        throw new Exception('All fields are required');
    }

    // Include database connection
    require_once __DIR__ . '/../config/db.php';
    global $pdo;

    // Check if receiver exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->execute([$receiver_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Recipient not found');
    }

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert message into database
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sender_id, $receiver_id, $subject, $content]);
        $message_id = $pdo->lastInsertId();
        
        // Get sender's name for notification
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$sender_id]);
        $sender = $stmt->fetch();
        
        // Create a notification for the recipient
        $notification_type = 'message';
        $title = 'New Message: ' . $subject;
        $notification_content = substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (sender_id, type, title, content, is_system, related_id) 
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([
            $sender_id, 
            $notification_type, 
            $title, 
            $notification_content, 
            $message_id
        ]);
        
        // Link notification to recipient
        $notification_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO user_notifications (notification_id, user_id) VALUES (?, ?)");
        $stmt->execute([$notification_id, $receiver_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'data' => [
                'message_id' => $message_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log('Error sending message: ' . $e->getMessage());
        throw new Exception('An error occurred while sending the message. Please try again.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
