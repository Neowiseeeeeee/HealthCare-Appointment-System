<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the user ID
$user_id = $_SESSION['user_id'];

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Set header to JSON
header('Content-Type: application/json');

try {
    // Get all messages (both sent and received) in a simpler query
    $query = "
        SELECT 
            m.message_id, 
            m.sender_id, 
            m.receiver_id, 
            m.subject, 
            m.content, 
            m.created_at, 
            m.is_read,
            m.parent_message_id,
            sender.first_name AS sender_first_name,
            sender.last_name AS sender_last_name,
            receiver.first_name AS receiver_first_name,
            receiver.last_name AS receiver_last_name
        FROM 
            messages m
        JOIN 
            users sender ON m.sender_id = sender.user_id
        JOIN 
            users receiver ON m.receiver_id = receiver.user_id
        WHERE 
            m.sender_id = ? OR m.receiver_id = ?
        ORDER BY 
            m.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $all_messages = [];
    
    // Process all messages
    while ($message = $result->fetch_assoc()) {
        // Determine if this is a sent or received message
        $direction = ($message['sender_id'] == $user_id) ? 'sent' : 'received';
        
        // Format the message for display
        $all_messages[] = [
            'id' => $message['message_id'],
            'title' => $message['subject'] ?? 'No Subject',
            'content' => $message['content'],
            'sender_id' => $message['sender_id'],
            'sender_name' => $message['sender_first_name'] . ' ' . $message['sender_last_name'],
            'receiver_id' => $message['receiver_id'],
            'receiver_name' => $message['receiver_first_name'] . ' ' . $message['receiver_last_name'],
            'is_read' => (bool)$message['is_read'],
            'created_at' => $message['created_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($message['created_at'])),
            'direction' => $direction,
            'parent_message_id' => $message['parent_message_id']
        ];
    }
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'messages' => $all_messages,
        'counts' => [
            'total' => count($all_messages),
            'received' => count(array_filter($all_messages, function($msg) { 
                return $msg['direction'] === 'received'; 
            })),
            'sent' => count(array_filter($all_messages, function($msg) { 
                return $msg['direction'] === 'sent'; 
            })),
            'unread' => count(array_filter($all_messages, function($msg) { 
                return $msg['direction'] === 'received' && !$msg['is_read']; 
            }))
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Database error in fetch_all_messages.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching messages: ' . $e->getMessage()
    ]);
} finally {
    // Close statements and connection
    if (isset($received_stmt)) $received_stmt->close();
    if (isset($sent_stmt)) $sent_stmt->close();
    if (isset($conn)) $conn->close();
}
?>