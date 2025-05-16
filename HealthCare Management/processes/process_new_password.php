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
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validate passwords match
    if ($password !== $confirmPassword) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: ../auth/reset_password.php?token=" . urlencode($token));
        exit();
    }

    // Validate password length
    if (strlen($password) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters long.";
        header("Location: ../auth/reset_password.php?token=" . urlencode($token));
        exit();
    }

    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid or expired reset link. Please request a new password reset.";
        header("Location: ../auth/forgot_password.php");
        exit();
    }

    // Update password and clear reset token
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
    $stmt->bind_param("ss", $hashedPassword, $token);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Your password has been successfully updated. You can now login with your new password.";
        header("Location: ../auth/Login.php");
        exit();
    } else {
        $_SESSION['error_message'] = "An error occurred while updating your password. Please try again.";
        header("Location: ../auth/reset_password.php?token=" . urlencode($token));
        exit();
    }

    $stmt->close();
} else {
    header("HTTP/1.1 405 Method Not Allowed");
    die("Method Not Allowed");
}

$conn->close();
?> 