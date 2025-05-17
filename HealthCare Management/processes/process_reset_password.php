<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "docnow_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validation
    $errors = [];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: ../auth/forgot_password.php");
        exit();
    }

    // Check if email exists
    $check_email_sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email_sql);
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Database error: " . $conn->error;
        header("Location: ../auth/forgot_password.php");
        exit();
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "No account found with this email address.";
        header("Location: ../auth/forgot_password.php");
        exit();
    }
    $stmt->close();

    // Update password directly
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $update_password_sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($update_password_sql);
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Database error: " . $conn->error;
        header("Location: ../auth/forgot_password.php");
        exit();
    }
    
    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Your password has been successfully updated. You can now login with your new password.";
        header("Location: ../auth/Login.php");
        exit();
    } else {
        $_SESSION['error_message'] = "An error occurred while updating your password. Please try again.";
        header("Location: ../auth/forgot_password.php");
        exit();
    }

    $stmt->close();
} else {
    header("HTTP/1.1 405 Method Not Allowed");
    die("Method Not Allowed");
}

$conn->close();
?>