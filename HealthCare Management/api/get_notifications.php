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

    // Query to get notifications for the current user
    $stmt = $conn->prepare(
        "SELECT n.notification_id as id, n.title, n.content, n.type, n.created_at as date,
                un.is_read, n.sender_id, u.first_name, u.last_name
         FROM user_notifications un
         JOIN notifications n ON un.notification_id = n.notification_id
         LEFT JOIN users u ON n.sender_id = u.user_id
         WHERE un.user_id = :userId
         ORDER BY n.created_at DESC
         LIMIT 50"
    );
    
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the date for better readability
    foreach ($notifications as &$notification) {
        $date = new DateTime($notification['date']);
        $now = new DateTime();
        $interval = $now->diff($date);
        
        if ($interval->y > 0) {
            $notification['date'] = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        } elseif ($interval->m > 0) {
            $notification['date'] = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            $notification['date'] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $notification['date'] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $notification['date'] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $notification['date'] = 'Just now';
        }
        
        // Add sender name if available
        if (!empty($notification['first_name']) && !empty($notification['last_name'])) {
            $notification['sender_name'] = $notification['first_name'] . ' ' . $notification['last_name'];
        } else {
            $notification['sender_name'] = 'System';
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch(PDOException $e) {
    // Log the error
    error_log("Database error in get_notifications.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
