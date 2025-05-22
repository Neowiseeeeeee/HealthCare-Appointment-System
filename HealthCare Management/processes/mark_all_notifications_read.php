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

    // Mark all notifications as read for this user
    $stmt = $conn->prepare(
        "UPDATE user_notifications 
         SET is_read = 1 
         WHERE user_id = :userId AND is_read = 0"
    );
    
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $success = $stmt->execute();
    
    if ($success) {
        $count = $stmt->rowCount();
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $count
        ]);
    } else {
        throw new Exception('Failed to update notifications');
    }
    
} catch(PDOException $e) {
    // Log the error for debugging
    error_log("Database error in mark_all_notifications_read.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    // Log the error for debugging
    error_log("Error in mark_all_notifications_read.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
