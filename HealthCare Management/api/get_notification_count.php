<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Database connection details
$host = 'localhost';
$dbname = 'docnow_db';
$username = 'root';
$password = '';

try {
    // Create connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to count unread notifications for the current user
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count 
         FROM user_notifications un
         JOIN notifications n ON un.notification_id = n.notification_id
         WHERE un.user_id = :userId AND un.is_read = 0"
    );
    
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
    
} catch(PDOException $e) {
    // Log the error for debugging
    error_log("Database error in get_notification_count.php: " . $e->getMessage());
    
    // Return a generic error message
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
