<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to change your password.';
    header("Location: ../auth/Login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    $_SESSION['error_message'] = 'Database connection failed';
    header("Location: ../change_password.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['currentPassword'];
$new_password = $_POST['newPassword'];
$confirm_password = $_POST['confirmPassword'];

// Validate passwords match
if ($new_password !== $confirm_password) {
    $_SESSION['error_message'] = 'New passwords do not match.';
    header("Location: ../change_password.php");
    exit();
}

// Validate password length
if (strlen($new_password) < 8) {
    $_SESSION['error_message'] = 'Password must be at least 8 characters long.';
    header("Location: ../change_password.php");
    exit();
}

// Get current user's password
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    $_SESSION['error_message'] = 'Current password is incorrect.';
    header("Location: ../change_password.php");
    exit();
}

// Hash new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'Password changed successfully!';
    header("Location: ../" . ($_SESSION['role'] === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'));
} else {
    $_SESSION['error_message'] = 'Error changing password. Please try again.';
    header("Location: ../change_password.php");
}

$stmt->close();
$conn->close();
?> 