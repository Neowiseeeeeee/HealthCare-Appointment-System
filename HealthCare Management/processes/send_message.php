<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/Login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Direct database connection for local environment
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Get form data
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['recipient_id'];
    $subject = $_POST['subject'];
    $content = $_POST['message'];
    
    // Insert message into database
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, content, is_read) 
            VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("iiss", $sender_id, $receiver_id, $subject, $content);
    
    if ($stmt->execute()) {
        
        // Redirect back with success message
        if ($_SESSION['role'] === 'doctor') {
            $redirect = "../doctor_dashboard.php";
        } else if ($_SESSION['role'] === 'patient') {
            $redirect = "../patient_dashboard.php";
        } else {
            $redirect = "../admin_dashboard.php";
        }
        
        $_SESSION['message'] = "Message sent successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: $redirect");
        exit();
        
    } else {
        // Log error and show error message
        error_log("Error sending message: " . $stmt->error);
        $_SESSION['message'] = "Error sending message. Please try again.";
        $_SESSION['message_type'] = "danger";
        
        if ($_SESSION['role'] === 'doctor') {
            header("Location: ../doctor_dashboard.php");
        } else if ($_SESSION['role'] === 'patient') {
            header("Location: ../patient_dashboard.php");
        } else {
            header("Location: ../admin_dashboard.php");
        }
        exit();
    }
    
    $stmt->close();
    $conn->close();
} else {
    // If accessed directly without form submission, redirect
    if ($_SESSION['role'] === 'doctor') {
        header("Location: ../doctor_dashboard.php");
    } else if ($_SESSION['role'] === 'patient') {
        header("Location: ../patient_dashboard.php");
    } else {
        header("Location: ../admin_dashboard.php");
    }
    exit();
}
?>