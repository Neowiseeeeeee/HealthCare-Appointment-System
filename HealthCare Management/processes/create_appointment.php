<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../auth/Login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once "../config/db.php";
    
    // Get form data
    $doctor_id = $_SESSION['user_id'];
    $patient_id = $_POST['patient_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $appointment_type = $_POST['appointment_type'];
    $reason = $_POST['reason'];
    
    // Combine date and time into datetime format
    $appointment_datetime = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time"));
    
    // Set default status to 'pending'
    $status = 'pending';
    
    try {
        // Insert appointment into database
        $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, reason, appointment_type, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient_id, $doctor_id, $appointment_datetime, $reason, $appointment_type, $status]);
        
        // Redirect back to doctor dashboard with success message
        $_SESSION['message'] = "Appointment scheduled successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: ../doctor_dashboard.php");
        exit();
        
    } catch (PDOException $e) {
        // Log error and show error message
        error_log("Error creating appointment: " . $e->getMessage());
        $_SESSION['message'] = "Error scheduling appointment. Please try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: ../doctor_dashboard.php");
        exit();
    }
} else {
    // If accessed directly without form submission, redirect to dashboard
    header("Location: ../doctor_dashboard.php");
    exit();
}
?>