<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function logError($message) {
    error_log('Message System Error: ' . $message);
    return json_encode(['success' => false, 'error' => $message]);
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;

    if( $time_difference < 1 ) { return 'just now'; }
    $condition = [
        12 * 30 * 24 * 60 * 60  =>  'year',
        30 * 24 * 60 * 60       =>  'month',
        24 * 60 * 60            =>  'day',
        60 * 60                 =>  'hour',
        60                      =>  'minute',
        1                       =>  'second'
    ];

    foreach( $condition as $secs => $str ) {
        $d = $time_difference / $secs;
        if( $d >= 1 ) {
            $t = round( $d );
            return $t . ' ' . $str . ( $t > 1 ? 's' : '' ) . ' ago';
        }
    }
    return 'just now';
}

// Check if user is logged in and has a valid role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['patient', 'doctor'])) {
    error_log('Unauthorized access - User ID: ' . ($_SESSION['user_id'] ?? 'not set') . ', Role: ' . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

// Database connection
try {
    $conn = new mysqli("localhost", "root", "", "docnow_db");
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    http_response_code(500);
    die(logError($e->getMessage()));
}

// First, check if tables exist
$tables = $conn->query("SHOW TABLES LIKE 'messages'")->num_rows > 0 && 
          $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0;

if (!$tables) {
    http_response_code(500);
    die(logError('Required database tables are missing'));
}

// Get messages where user is either sender or receiver
$query = "SELECT 
            m.*,
            sender.user_id as sender_id,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            sender.role as sender_role,
            receiver.user_id as receiver_id,
            receiver.first_name as receiver_first_name,
            receiver.last_name as receiver_last_name
          FROM messages m
          JOIN users sender ON m.sender_id = sender.user_id
          JOIN users receiver ON m.receiver_id = receiver.user_id
          WHERE (m.receiver_id = ?)
          ORDER BY m.created_at DESC";

try {
    // Prepare the statement
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // Bind parameters (only user_id for receiver)
    $stmt->bind_param('i', $user_id);
    
    // Execute the query
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    // Get the result
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['message_id'],
            'is_read' => (bool)$row['is_read'],
            'content' => $row['content'],
            'subject' => $row['subject'] ?: 'No subject',
            'created_at' => $row['created_at'],
            'time_ago' => getTimeAgo($row['created_at']),
            'sender_id' => $row['sender_id'],
            'sender_name' => trim($row['sender_first_name'] . ' ' . $row['sender_last_name']),
            'sender_role' => $row['sender_role'],
            'receiver_id' => $row['receiver_id'],
            'receiver_name' => trim($row['receiver_first_name'] . ' ' . $row['receiver_last_name'])
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // Return success with messages
    echo json_encode([
        'success' => true, 
        'messages' => $messages,
        'debug' => [
            'user_id' => $user_id,
            'message_count' => count($messages)
        ]
    ]);
    
} catch (Exception $e) {
    // Clean up resources
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
    
    // Return error
    http_response_code(500);
    die(logError($e->getMessage()));
}
?>
