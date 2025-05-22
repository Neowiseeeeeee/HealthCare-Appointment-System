<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';     // Default XAMPP username
$db_pass = '';       // Default XAMPP password is empty
$db_name = 'docnow_db'; // Database name from the error logs

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => 'Unable to connect to the database. Please try again later.'
    ]));
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset('utf8mb4');

// Set timezone
// date_default_timezone_set('Asia/Manila'); // Uncomment and set your timezone if needed
?>
