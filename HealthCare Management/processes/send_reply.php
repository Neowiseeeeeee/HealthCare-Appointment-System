<?php
// Enable error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../includes/db_connect.php';

// Function to send standardized JSON responses
function sendResponse($success, $data = null, $statusCode = 200) {
    // Set the HTTP response code
    http_response_code($statusCode);
    
    // Prepare the response array
    $response = ['success' => $success];
    
    // Add data or error message
    if ($success) {
        if ($data !== null) {
            $response['data'] = $data;
        }
    } else {
        $response['error'] = is_array($data) && isset($data['error']) ? $data['error'] : 'An error occurred';
        if (is_array($data) && isset($data['details'])) {
            $response['details'] = $data['details'];
        }
    }
    
    // Encode the response
    $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        // Fallback for JSON encoding error
        $response = [
            'success' => false,
            'error' => 'JSON encoding error: ' . json_last_error_msg(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $json = json_encode($response);
    }
    
    error_log('send_reply.php: Sending response - ' . $json);
    echo $json;
    exit();
}

try {
    error_log('send_reply.php: Script started for user ID: ' . ($_SESSION['user_id'] ?? 'unknown'));
    
    // Check if user is logged in and is a patient
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
        throw new Exception('Unauthorized: Please log in as a patient to send replies.', 401);
    }

    // Get POST data
    $parent_id = $_POST['parent_id'] ?? null;
    $content = trim($_POST['content'] ?? '');
    
    // Log received data
    error_log('send_reply.php: Received data - ' . print_r($_POST, true));
    
    // Log received data
    error_log('send_reply.php: Received data - parent_id: ' . $parent_id . ', content length: ' . strlen($content));
    
    // Validate input
    if (!$parent_id) {
        throw new Exception('Parent message ID is required', 400);
    }
    
    if (empty($content)) {
        throw new Exception('Message content cannot be empty', 400);
    }

    // Start transaction
    $conn->begin_transaction();
    error_log('send_reply.php: Transaction started');
    
    try {

        // Get the original message
        $stmt = $conn->prepare("SELECT sender_id, subject FROM messages WHERE message_id = ?");
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        error_log('send_reply.php: Getting original message with ID: ' . $parent_id);
        $stmt->bind_param("i", $parent_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database query failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Original message not found with ID: ' . $parent_id, 404);
        }
        
        $original = $result->fetch_assoc();
        $doctor_id = $original['sender_id'];
        $subject = $original['subject'] ? 'Re: ' . $original['subject'] : 'Re: Message';
        $patient_id = $_SESSION['user_id'];
        
        $stmt->close();
        
        // Insert the reply
        $insertQuery = "
            INSERT INTO messages 
            (sender_id, receiver_id, subject, content, parent_message_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        error_log('send_reply.php: Preparing to insert reply');
        $stmt = $conn->prepare($insertQuery);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("iissi", $patient_id, $doctor_id, $subject, $content, $parent_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to send reply: ' . $stmt->error);
        }
        
        $message_id = $stmt->insert_id;
        $stmt->close();
        error_log('send_reply.php: Reply inserted with ID: ' . $message_id);
        
        // Mark the conversation as unread for the doctor
        $updateStmt = $conn->prepare("UPDATE messages SET is_read = 0 WHERE message_id = ?");
        if (!$updateStmt) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }
        
        $updateStmt->bind_param("i", $parent_id);
        if (!$updateStmt->execute()) {
            // Non-critical error, log but don't fail
            error_log('send_reply.php: Failed to update read status: ' . $updateStmt->error);
        } else {
            error_log('send_reply.php: Conversation marked as unread for doctor');
        }
        $updateStmt->close();
        
        // Create notification for the doctor
        $notification_title = "New Reply: " . (strlen($subject) > 30 ? substr($subject, 0, 27) . '...' : $subject);
        $notification_content = substr(strip_tags($content), 0, 100) . (strlen($content) > 100 ? '...' : '');
        
        $notificationQuery = "
            INSERT INTO notifications 
            (user_id, title, content, type, related_id, created_at) 
            VALUES (?, ?, ?, 'message', ?, NOW())
        ";
        
        $notificationStmt = $conn->prepare($notificationQuery);
        if ($notificationStmt) {
            $notificationStmt->bind_param('issi', $doctor_id, $notification_title, $notification_content, $message_id);
            if (!$notificationStmt->execute()) {
                // Non-critical error, log but don't fail
                error_log('send_reply.php: Failed to create notification: ' . $notificationStmt->error);
            }
            $notificationStmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        error_log('send_reply.php: Transaction committed successfully');
        
        // Prepare response data
        $responseData = [
            'message_id' => $message_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'recipient_id' => $doctor_id,
            'subject' => $subject
        ];
        
        sendResponse(true, [
            'message' => 'Reply sent successfully',
            'data' => $responseData
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            error_log('send_reply.php: Rolling back transaction due to error');
            if (!$conn->rollback()) {
                $rollbackError = 'Failed to rollback transaction: ' . $conn->error;
                error_log('send_reply.php: ' . $rollbackError);
            }
        }
        
        // Re-throw the exception to be caught by the outer try-catch
        throw $e;
    }
} catch (Exception $e) {
    // Log the detailed error
    $errorMessage = 'Error in send_reply.php: ' . $e->getMessage() . 
                  ' in ' . $e->getFile() . ':' . $e->getLine() . 
                  '\nStack trace:\n' . $e->getTraceAsString();
    
    error_log($errorMessage);
    
    // Log the current request data for debugging
    $requestData = [
        'POST' => $_POST,
        'SESSION' => isset($_SESSION) ? ['user_id' => $_SESSION['user_id'] ?? null, 'role' => $_SESSION['role'] ?? null] : 'No session',
        'SERVER' => [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? null
        ]
    ];
    
    error_log('send_reply.php: Request data - ' . print_r($requestData, true));
    
    // Close any open database connections
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        // Log any pending database errors
        if ($conn->error) {
            error_log('send_reply.php: MySQL error - ' . $conn->error);
        }
    }
    
    // Send error response
    $errorCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    
    // Prepare error response
    $errorResponse = [
        'error' => $e->getMessage() ?: 'An error occurred while processing your request.',
        'code' => $errorCode
    ];
    
    // Only include detailed error in development
    if (ini_get('display_errors')) {
        $errorResponse['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }
    
    sendResponse(false, $errorResponse, $errorCode);
}
?>
