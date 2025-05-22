<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../auth/Login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $appointment_type = $_POST['appointment_type'];
    $reason = $_POST['reason'];
    
    // Combine date and time
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    
    // Insert appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, appointment_type, reason, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    $stmt->bind_param("iisss", $patient_id, $doctor_id, $appointment_datetime, $appointment_type, $reason);
    
    if ($stmt->execute()) {
        // Create notification for patient
        $notification_text = "Dr. " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . " has scheduled an appointment with you on " . date('F j, Y \a\t g:i A', strtotime($appointment_datetime));
        $notification_title = "New Appointment Scheduled";
        $is_system = 0; // This is a user-generated notification, not a system one
        
        $notification_stmt = $conn->prepare("
            INSERT INTO notifications (sender_id, title, content, is_system, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $notification_stmt->bind_param("issi", $doctor_id, $notification_title, $notification_text, $is_system);
        $notification_stmt->execute();
        
        $notification_id = $conn->insert_id;
        
        // Link notification to patient
        $user_notification_stmt = $conn->prepare("
            INSERT INTO user_notifications (user_id, notification_id, is_read)
            VALUES (?, ?, 0)
        ");
        $user_notification_stmt->bind_param("ii", $patient_id, $notification_id);
        $user_notification_stmt->execute();
        
        // Redirect back to dashboard with success message
        header("Location: ../doctor_dashboard.php?success=Appointment scheduled successfully");
        exit();
    } else {
        // Redirect back with error
        header("Location: ../doctor_dashboard.php?error=Failed to schedule appointment");
        exit();
    }
}

// Redirect if form not submitted
header("Location: ../doctor_dashboard.php");
exit();
?>