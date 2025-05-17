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

// Get the user ID and required parameters
$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';

// Set header to JSON
header('Content-Type: application/json');

if (empty($type) || empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Determine the ID value (strip the prefix)
    $item_id = (int)substr($id, 2); // Remove prefix like n_ or m_
    
    if ($type === 'notification') {
        // Check if the record exists in user_notifications
        $check_query = "
            SELECT id FROM user_notifications 
            WHERE user_id = :user_id AND notification_id = :notification_id
        ";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':notification_id', $item_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing record
            $update_query = "
                UPDATE user_notifications 
                SET is_read = 1 
                WHERE user_id = :user_id AND notification_id = :notification_id
            ";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':notification_id', $item_id, PDO::PARAM_INT);
            $update_stmt->execute();
        } else {
            // Insert new record
            $insert_query = "
                INSERT INTO user_notifications (notification_id, user_id, is_read)
                VALUES (:notification_id, :user_id, 1)
            ";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->bindParam(':notification_id', $item_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $insert_stmt->execute();
        }
    } else if ($type === 'message') {
        // Mark message as read
        $update_query = "
            UPDATE messages 
            SET is_read = 1 
            WHERE message_id = :message_id AND receiver_id = :user_id
        ";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->bindParam(':message_id', $item_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();
    }
    
    // Return success
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in mark_as_read.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'error_details' => $e->getMessage()
    ]);
}
?>