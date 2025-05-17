<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}

// Validate inputs
$title = isset($_POST['notificationTitle']) ? trim($_POST['notificationTitle']) : '';
$content = isset($_POST['notificationContent']) ? trim($_POST['notificationContent']) : '';
$type = isset($_POST['notificationType']) ? trim($_POST['notificationType']) : 'general';

// For debugging
error_log("Title: " . $title);
error_log("Content: " . $content);
error_log("Type: " . $type);

if (empty($title)) {
    echo json_encode(["status" => "error", "message" => "Notification title is required"]);
    exit();
}

if (empty($content)) {
    echo json_encode(["status" => "error", "message" => "Notification content is required"]);
    exit();
}

if (strlen($title) > 255) {
    echo json_encode(["status" => "error", "message" => "Notification title is too long (max 255 characters)"]);
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Create new notification record
    $stmt = $conn->prepare("INSERT INTO notifications (title, content, type, sender_id, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("sssi", $title, $content, $type, $admin_id);
    $stmt->execute();
    $notification_id = $stmt->insert_id;
    $stmt->close();
    
    if (!$notification_id) {
        throw new Exception("Failed to create notification");
    }
    
    // Get all users
    $result = $conn->query("SELECT user_id FROM users");
    if (!$result) {
        throw new Exception("Failed to retrieve users");
    }
    
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    // Insert notification for each user
    $stmt = $conn->prepare("INSERT INTO user_notifications (notification_id, user_id, is_read, created_at) VALUES (?, ?, 0, NOW())");
    foreach ($users as $user) {
        $stmt->bind_param("ii", $notification_id, $user['user_id']);
        $stmt->execute();
    }
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "status" => "success", 
        "message" => "System notification sent to " . count($users) . " users",
        "notification_id" => $notification_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>