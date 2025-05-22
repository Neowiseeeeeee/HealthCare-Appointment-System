<?php
// Database connection details
$host = 'localhost';
$dbname = 'docnow_db';
$username = 'root';
$password = '';

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get tomorrow's date
    $tomorrow = new DateTime('tomorrow');
    $tomorrowDate = $tomorrow->format('Y-m-d');
    
    // Find appointments scheduled for tomorrow
    $stmt = $pdo->prepare(
        "SELECT DISTINCT a.appointment_id, a.patient_id, a.doctor_id, a.appointment_datetime,
                u.first_name, u.last_name, u.email,
                d.specialty, du.first_name as doc_first_name, du.last_name as doc_last_name
         FROM appointments a
         JOIN users u ON a.patient_id = u.user_id
         JOIN doctors d ON a.doctor_id = d.doctor_id
         JOIN users du ON d.doctor_id = du.user_id
         WHERE DATE(a.appointment_datetime) = :tomorrow
         AND a.status IN ('confirmed', 'pending')
         AND NOT EXISTS (
             SELECT 1 FROM notifications n
             JOIN user_notifications un ON n.notification_id = un.notification_id
             WHERE n.type = 'appointment'
             AND n.related_id = a.appointment_id
             AND un.user_id = a.patient_id
             AND DATE(n.created_at) = CURDATE()
         )"
    );
    
    error_log("Looking for appointments for date: " . $tomorrowDate);
    
    $stmt->execute(['tomorrow' => $tomorrowDate]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($appointments) . " appointments for tomorrow");
    error_log("Appointments: " . print_r($appointments, true));
    
    $notificationsSent = 0;
    
    foreach ($appointments as $appointment) {
        // Format appointment time
        $appointmentTime = new DateTime($appointment['appointment_datetime']);
        $formattedTime = $appointmentTime->format('h:i A');
        
        // Create notification message
        $message = "Reminder: You have an appointment tomorrow at " . $formattedTime . " with Dr. " . 
                  $appointment['doc_first_name'] . ' ' . $appointment['doc_last_name'] . 
                  " (" . $appointment['specialty'] . ")";
        
        // Insert notification
        $pdo->beginTransaction();
        
        try {
            // Create notification
            $notificationStmt = $pdo->prepare(
                "INSERT INTO notifications (sender_id, type, title, content, is_system, related_id, created_at)
                 VALUES (NULL, 'appointment', 'Appointment Reminder', :content, 1, :appointment_id, NOW())"
            );
            
            $notificationStmt->execute([
                'content' => $message,
                'appointment_id' => $appointment['appointment_id']
            ]);
            
            $notificationId = $pdo->lastInsertId();
            
            // Link notification to user
            $userNotificationStmt = $pdo->prepare(
                "INSERT INTO user_notifications (notification_id, user_id, is_read, created_at)
                 VALUES (:notification_id, :user_id, 0, NOW())"
            );
            
            $userNotificationStmt->execute([
                'notification_id' => $notificationId,
                'user_id' => $appointment['patient_id']
            ]);
            
            error_log("Created notification for appointment " . $appointment['appointment_id'] . " for user " . $appointment['patient_id']);
            
            $pdo->commit();
            $notificationsSent++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error sending appointment reminder: " . $e->getMessage());
        }
    }
    
    // Log the result
    $logMessage = date('Y-m-d H:i:s') . " - Sent $notificationsSent appointment reminders\n";
    file_put_contents(__DIR__ . '/appointment_reminders.log', $logMessage, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully sent $notificationsSent appointment reminders"
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
