<?php
// Test notifications endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent any unwanted output
ob_start();

// Include the session file to simulate being logged in
@include_once 'auth/session.php';

// Set test session data if not already set
if (!isset($_SESSION)) {
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
}

// Include the notifications endpoint
ob_clean(); // Clear any output before including
include 'api/fetch_admin_notifications.php';

// Get the output
$output = ob_get_clean();

// Decode the JSON to make it more readable
$decoded = json_decode($output, true);

// Output the results
echo "<h2>Notifications Test</h2>";

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<h3>Decoded Response:</h3>";
echo "<pre>";
print_r($decoded);
echo "</pre>";

// Check for JSON errors
$jsonError = json_last_error();
if ($jsonError !== JSON_ERROR_NONE) {
    echo "<h3>JSON Error:</h3>";
    echo "Error code: " . $jsonError . "<br>";
    echo "Error message: " . json_last_error_msg() . "<br>";
}

// Check for PHP errors
$errors = error_get_last();
if ($errors !== null) {
    echo "<h3>PHP Error:</h3>";
    echo "<pre>";
    print_r($errors);
    echo "</pre>";
}

// Show session data for debugging
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Show server data for debugging
echo "<h3>Server Data:</h3>";
echo "<pre>";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'Not set') . "<br>";
echo "HTTP_ACCEPT: " . ($_SERVER['HTTP_ACCEPT'] ?? 'Not set') . "<br>";
echo "Content Type: " . (headers_sent() ? 'Headers already sent' : 'Headers not sent') . "<br>";
echo "</pre>";
?>
