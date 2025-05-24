<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in or not a patient']);
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get the user ID
$user_id = $_SESSION['user_id'];

// Set header to JSON
header('Content-Type: application/json');

try {
    // Get system notifications for this patient
    $notifications_query = "
        SELECT n.*, 
               CASE WHEN n.sender_id IS NOT NULL THEN 
                   (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = n.sender_id) 
               ELSE 'System' END AS sender_name
        FROM notifications n
        WHERE (n.recipient_id = :user_id OR n.recipient_id IS NULL)
        ORDER BY n.created_at DESC 
        LIMIT 30
    ";
    
    $notifications_stmt = $pdo->prepare($notifications_query);
    $notifications_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also check for upcoming appointments within 24 hours to add as notifications
    $appointments_query = "
        SELECT a.*, 
               u.first_name as doctor_first_name, 
               u.last_name as doctor_last_name, 
               d.specialty
        FROM appointments a
        JOIN users u ON a.doctor_id = u.user_id
        LEFT JOIN doctors d ON u.user_id = d.doctor_id
        WHERE a.patient_id = :user_id 
          AND a.appointment_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND a.status NOT IN ('cancelled', 'completed')
        ORDER BY a.appointment_datetime ASC
    ";
    
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $appointments_stmt->execute();
    $upcoming_appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process notifications
    $result_notifications = [];
    $unread_count = 0;
    
    // Process system notifications
    foreach ($notifications as $notif) {
        $is_read = (bool)($notif['is_read'] ?? false);
        if (!$is_read) {
            $unread_count++;
        }
        
        $result_notifications[] = [
            'id' => 'n_' . $notif['notification_id'],
            'type' => 'notification',
            'subtype' => $notif['type'],
            'title' => $notif['title'] ?? 'Notification',
            'content' => $notif['content'],
            'sender' => $notif['sender_name'],
            'is_read' => $is_read,
            'date' => $notif['created_at'],
            'formatted_date' => date('M j, Y g:i A', strtotime($notif['created_at']))
        ];
    }
    
    // Add upcoming appointment notifications
    foreach ($upcoming_appointments as $appt) {
        $doctor_name = $appt['doctor_first_name'] . ' ' . $appt['doctor_last_name'];
        $appt_time = date('g:i A', strtotime($appt['appointment_datetime']));
        $appt_date = date('l, F j, Y', strtotime($appt['appointment_datetime']));
        
        $time_diff = strtotime($appt['appointment_datetime']) - time();
        $hours_remaining = round($time_diff / 3600);
        
        $content = "You have an upcoming appointment with Dr. {$doctor_name} ({$appt['specialty']}) ";
        $content .= "scheduled for {$appt_time} on {$appt_date}. ";
        
        if ($hours_remaining <= 1) {
            $content .= "This appointment is in less than an hour.";
        } else {
            $content .= "This appointment is in approximately {$hours_remaining} hours.";
        }
        
        $result_notifications[] = [
            'id' => 'a_' . $appt['appointment_id'],
            'type' => 'appointment_reminder',
            'subtype' => 'reminder',
            'title' => 'Upcoming Appointment Reminder',
            'content' => $content,
            'sender' => 'System',
            'is_read' => false,
            'date' => date('Y-m-d H:i:s'), // Current time
            'formatted_date' => date('M j, Y g:i A'),
            'appointment_data' => $appt
        ];
        
        $unread_count++; // Appointment reminders are always considered unread
    }
    
    // Sort by date (newest first)
    usort($result_notifications, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'notifications' => $result_notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in patient_notifications.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_details' => $e->getMessage()
    ]);
}
?>