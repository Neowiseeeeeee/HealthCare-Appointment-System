<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $patient_id = $_SESSION['user_id'];

    // Fetch messages and notifications
    $sql = "SELECT n.*, 
            CASE 
                WHEN n.sender_id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                ELSE 'System'
            END as sender_name,
            u.role as sender_role
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.user_id
            WHERE n.recipient_id = :patient_id
            ORDER BY n.created_at DESC
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':patient_id' => $patient_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'data' => array_map(function($notif) {
            return [
                'id' => $notif['notification_id'],
                'type' => $notif['type'],
                'title' => $notif['title'] ?? 'Notification',
                'content' => $notif['content'],
                'datetime' => $notif['created_at'],
                'sender' => $notif['sender_name'],
                'sender_role' => $notif['sender_role'] ?? 'system',
                'is_read' => (bool)$notif['is_read'],
                'requires_reply' => (bool)($notif['requires_reply'] ?? false)
            ];
        }, $notifications)
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