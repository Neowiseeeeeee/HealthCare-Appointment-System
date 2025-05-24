<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../auth/Login.php");
    exit();
}

// Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once "../config/db.php";
    
    // Get form data
    $appointment_id = isset($_POST['appointment_id']) ? $_POST['appointment_id'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;
    
    // Validate data
    if (!$appointment_id || !in_array($status, ['confirmed', 'completed', 'cancelled'])) {
        $_SESSION['message'] = "Invalid appointment data.";
        $_SESSION['message_type'] = "danger";
        header("Location: ../doctor_dashboard.php");
        exit();
    }
    
    try {
        // Update appointment status
        $sql = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $appointment_id]);
        
        // Redirect back to doctor dashboard with success message
        $_SESSION['message'] = "Appointment status updated to " . ucfirst($status) . "!";
        $_SESSION['message_type'] = "success";
        header("Location: ../doctor_dashboard.php");
        exit();
        
    } catch (PDOException $e) {
        // Log error and show error message
        error_log("Error updating appointment status: " . $e->getMessage());
        $_SESSION['message'] = "Error updating appointment status. Please try again.";
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