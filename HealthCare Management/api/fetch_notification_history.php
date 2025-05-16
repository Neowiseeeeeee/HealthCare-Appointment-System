<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

try {
    // Get notification history
    $sql = "SELECT n.*, 
            CASE 
                WHEN n.sender_id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                ELSE 'System'
            END as sender_name,
            COUNT(un.user_id) as recipient_count
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.user_id
            LEFT JOIN user_notifications un ON n.notification_id = un.notification_id
            WHERE n.is_system = 1
            GROUP BY n.notification_id
            ORDER BY n.created_at DESC
            LIMIT 100";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Failed to retrieve notification history: " . $conn->error);
    }
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notifications
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>