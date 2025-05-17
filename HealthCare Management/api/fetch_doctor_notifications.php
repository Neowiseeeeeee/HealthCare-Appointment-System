<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../config/db.php';

// Get doctor ID
$doctor_id = $_SESSION['user_id'];

// Set header to JSON
header('Content-Type: application/json');

try {
    // Creating a fallback method to ensure we always show at least the notifications from the database
    $combined_data = [];
    
    // First, get the notifications from the notifications table that are assigned to this doctor
    // Using both the notifications table and user_notifications table which contains the user-notification relationship
    $notifications_query = "
        SELECT n.notification_id, n.type, n.title, n.content, n.created_at, n.is_system, 
               un.is_read, u.first_name as sender_first_name, u.last_name as sender_last_name
        FROM notifications n
        JOIN user_notifications un ON n.notification_id = un.notification_id
        LEFT JOIN users u ON n.sender_id = u.user_id
        WHERE un.user_id = :doctor_id
        ORDER BY n.created_at DESC
    ";
    
    $notifications_stmt = $pdo->prepare($notifications_query);
    $notifications_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process notifications
    foreach ($notifications as $notification) {
        $sender_name = $notification['is_system'] ? 'System' : 
                     ($notification['sender_first_name'] ? $notification['sender_first_name'] . ' ' . $notification['sender_last_name'] : 'Admin');
        
        $combined_data[] = [
            'id' => 'n_' . $notification['notification_id'],
            'type' => 'notification',
            'subtype' => $notification['type'],
            'title' => $notification['title'],
            'content' => $notification['content'],
            'sender' => $sender_name,
            'is_read' => (bool)$notification['is_read'],
            'date' => $notification['created_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($notification['created_at']))
        ];
    }
    
    // Try to get messages as well (if table exists)
    try {
        $messages_query = "
            SELECT m.message_id, m.subject, m.content, m.is_read, m.created_at, 
                   u.first_name as sender_first_name, u.last_name as sender_last_name, 
                   u.user_id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.receiver_id = :doctor_id
            ORDER BY m.created_at DESC
            LIMIT 10
        ";
        
        $messages_stmt = $pdo->prepare($messages_query);
        $messages_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $messages_stmt->execute();
        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add messages to combined data
        foreach ($messages as $message) {
            $sender_name = $message['sender_first_name'] . ' ' . $message['sender_last_name'];
            
            $combined_data[] = [
                'id' => 'm_' . $message['message_id'],
                'type' => 'message',
                'title' => $message['subject'] ?: 'No Subject',
                'content' => $message['content'],
                'sender' => $sender_name,
                'sender_id' => $message['sender_id'],
                'is_read' => (bool)$message['is_read'],
                'date' => $message['created_at'],
                'formatted_date' => date('M j, Y g:i A', strtotime($message['created_at']))
            ];
        }
    } catch (PDOException $messageErr) {
        // Just log the error but continue
        error_log("Messages table error: " . $messageErr->getMessage());
    }
    
    // Sort by date (newest first)
    usort($combined_data, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // If we have no data from the user-specific queries, try to get notifications that are relevant to all users
    if (empty($combined_data)) {
        try {
            // Get system-wide notifications
            $global_query = "SELECT n.* FROM notifications n ORDER BY n.created_at DESC LIMIT 5";
            $global_stmt = $pdo->query($global_query);
            $global_notifications = $global_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($global_notifications as $notif) {
                $combined_data[] = [
                    'id' => 'n_' . $notif['notification_id'],
                    'type' => 'notification',
                    'subtype' => $notif['type'],
                    'title' => $notif['title'],
                    'content' => $notif['content'],
                    'sender' => 'System',
                    'is_read' => false,
                    'date' => $notif['created_at'],
                    'formatted_date' => date('M j, Y g:i A', strtotime($notif['created_at']))
                ];
            }
        } catch (PDOException $globalErr) {
            // Just log the error but continue
            error_log("Global notifications error: " . $globalErr->getMessage());
        }
    }
    
    // Calculate counts
    $message_count = count(array_filter($combined_data, function($item) { return $item['type'] === 'message'; }));
    $notification_count = count(array_filter($combined_data, function($item) { return $item['type'] === 'notification'; }));
    $unread_count = count(array_filter($combined_data, function($item) { return !$item['is_read']; }));
    
    // Return results
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
    
} catch(PDOException $e) {
    // Log the error
    error_log('Database error in fetch_doctor_notifications.php: ' . $e->getMessage());
    
    // Provide a useful response
    echo json_encode([
        'success' => false, 
        'message' => 'Could not load notifications. Please try again.',
        'error_details' => $e->getMessage()
    ]);
}
?>