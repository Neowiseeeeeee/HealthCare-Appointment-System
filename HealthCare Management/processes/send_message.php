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

// Check if user is logged in and has a valid session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    sendResponse(false, ['error' => 'Unauthorized: Please log in to send messages.'], 401);
}

// Log the start of the script
error_log('send_message.php: Script started for user ID: ' . $_SESSION['user_id']);

// Function to send standardized JSON responses
function sendResponse($success, $data = null, $statusCode = 200) {
    // Clear any previous output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (isset($data)) {
        $response['data'] = is_array($data) ? $data : ['message' => $data];
    }
    
    // Add debug info in development
    if (isset($_SERVER['HTTP_REFERER'])) {
        $response['debug'] = [
            'referer' => $_SERVER['HTTP_REFERER'],
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    // Ensure we're sending JSON
    header_remove();
    header('Content-Type: application/json; charset=utf-8');
    
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
    
    error_log('send_message.php: Sending response - ' . $json);
    echo $json;
    exit();
}

try {
    error_log('send_message.php: Session data: ' . print_r($_SESSION, true));
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        error_log('send_message.php: User not authenticated');
        sendResponse(false, ['error' => 'Authentication required. Please log in again.'], 401);
    }
    
    error_log('send_message.php: User authenticated - ID: ' . $_SESSION['user_id'] . ', Role: ' . $_SESSION['role']);

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, ['error' => 'Only POST method is allowed'], 405);
    }

    // Determine content type and parse input data
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
    $inputData = [];
    
    // Handle JSON input
    if (strpos($contentType, 'application/json') !== false) {
        $json_data = file_get_contents('php://input');
        error_log('send_message.php: Raw JSON input - ' . $json_data);
        
        $inputData = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data: ' . json_last_error_msg(), 400);
        }
    } else {
        // Handle form data
        error_log('send_message.php: Form data received');
        $inputData = $_POST;
    }
    
    // Get and sanitize input data
    $sender_id = intval($_SESSION['user_id']);
    
    // Log the actual received data for debugging
    error_log('send_message.php: Raw POST data: ' . print_r($_POST, true));
    
    // Check if using recipient_id (from doctor dashboard) or receiver_id (from patient dashboard)
    if (isset($_POST['recipient_id'])) {
        $receiver_id = intval($_POST['recipient_id']);
        $subject = trim(htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $content = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));
    } 
    else if (isset($_POST['receiver_id'])) {
        $receiver_id = intval($_POST['receiver_id']);
        $subject = trim(htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $content = trim(htmlspecialchars($_POST['content'] ?? '', ENT_QUOTES, 'UTF-8'));
        
        // Check for message_content parameter as well (used in reply form)
        if (empty($content) && isset($_POST['message_content'])) {
            $content = trim(htmlspecialchars($_POST['message_content'], ENT_QUOTES, 'UTF-8'));
        }
    }
    // If not in POST, check input data from JSON
    else if (isset($inputData['receiver_id'])) {
        $receiver_id = intval($inputData['receiver_id']);
        $subject = trim(htmlspecialchars($inputData['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $content = trim(htmlspecialchars($inputData['content'] ?? '', ENT_QUOTES, 'UTF-8'));
        
        // Check for message_content parameter as well
        if (empty($content) && isset($inputData['message_content'])) {
            $content = trim(htmlspecialchars($inputData['message_content'], ENT_QUOTES, 'UTF-8'));
        }
    }
    else if (isset($inputData['recipient_id'])) {
        $receiver_id = intval($inputData['recipient_id']);
        $subject = trim(htmlspecialchars($inputData['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
        $content = trim(htmlspecialchars($inputData['message'] ?? $inputData['content'] ?? $inputData['message_content'] ?? '', ENT_QUOTES, 'UTF-8'));
    }
    else {
        $receiver_id = 0;
        $subject = '';
        $content = '';
    }
    
    // Log the actual received data for debugging
    error_log('send_message.php: Raw POST data: ' . print_r($_POST, true));
    error_log('send_message.php: Parsed data - receiver_id: ' . $receiver_id . ', subject: ' . $subject);
    error_log('send_message.php: Content length: ' . strlen($content));
    
    // Log received data
    error_log('send_message.php: Received data - receiver_id: ' . $receiver_id . ', subject: ' . $subject . ', content length: ' . strlen($content));

    // Validate input
    if ($receiver_id <= 0) {
        sendResponse(false, ['error' => 'Invalid recipient'], 400);
    }
    
    if (empty($subject)) {
        sendResponse(false, ['error' => 'Subject is required', 'field' => 'subject'], 400);
    }
    
    if (empty($content)) {
        sendResponse(false, ['error' => 'Message content is required', 'field' => 'content'], 400);
    }

    // Include database connection
    $dbPath = __DIR__ . '/../includes/db_connect.php';
    error_log('send_message.php: Looking for database config at: ' . $dbPath);
    
    if (!file_exists($dbPath)) {
        $error = 'Database configuration not found at: ' . $dbPath;
        error_log('send_message.php: ' . $error);
        throw new Exception($error);
    }
    
    require_once $dbPath;
    
    // Check if database connection is available
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $error = 'Database connection failed. $conn is ' . (isset($conn) ? get_class($conn) : 'not set');
        error_log('send_message.php: ' . $error);
        throw new Exception('Database connection failed. Please try again later.');
    }
    
    // Verify connection is successful
    if ($conn->connect_error) {
        $error = 'Database connection error: ' . $conn->connect_error;
        error_log('send_message.php: ' . $error);
        throw new Exception('Database connection error. Please try again later.');
    }
    
    error_log('send_message.php: Database connection established successfully');

    // Start transaction with error handling
    if (!$conn->begin_transaction()) {
        $error = 'Failed to start transaction: ' . $conn->error;
        error_log('send_message.php: ' . $error);
        throw new Exception('Failed to start transaction. Please try again.');
    }
    
    error_log('send_message.php: Transaction started');
    
    try {
        // Check if receiver exists (removed status check since it might not exist in your users table)
        $query = "SELECT user_id, first_name, last_name FROM users WHERE user_id = ?";
        error_log('send_message.php: Preparing query: ' . $query);
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            $error = 'Database prepare failed: ' . $conn->error;
            error_log('send_message.php: ' . $error);
            throw new Exception('Failed to prepare database query. Please try again.');
        }
        
        error_log('send_message.php: Query prepared successfully');
        
        error_log('send_message.php: Binding parameters - receiver_id: ' . $receiver_id);
        $stmt->bind_param('i', $receiver_id);
        
        error_log('send_message.php: Executing query');
        if (!$stmt->execute()) {
            $error = 'Database query failed: ' . $stmt->error;
            error_log('send_message.php: ' . $error);
            throw new Exception('Failed to verify recipient. Please try again.');
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $error = 'Recipient not found or inactive - ID: ' . $receiver_id;
            error_log('send_message.php: ' . $error);
            throw new Exception('Recipient not found or account is not active.');
        }
        
        $receiver = $result->fetch_assoc();
        $stmt->close();
        
        // Log recipient info
        error_log('send_message.php: Recipient found - ID: ' . $receiver['user_id'] . ', Name: ' . $receiver['first_name'] . ' ' . $receiver['last_name']);
        
        // Prevent sending message to self
        if ($sender_id === $receiver_id) {
            $error = 'User attempted to send message to themselves - User ID: ' . $sender_id;
            error_log('send_message.php: ' . $error);
            throw new Exception('You cannot send a message to yourself.');
        }
        
        // Insert message into database with prepared statement
        $insertQuery = "
            INSERT INTO messages 
            (sender_id, receiver_id, subject, content, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        error_log('send_message.php: Preparing insert query: ' . $insertQuery);
        $stmt = $conn->prepare($insertQuery);
        
        if (!$stmt) {
            $error = 'Database prepare failed: ' . $conn->error;
            error_log('send_message.php: ' . $error);
            throw new Exception('Failed to prepare message for sending. Please try again.');
        }
        
        error_log('send_message.php: Binding parameters - sender_id: ' . $sender_id . ', receiver_id: ' . $receiver_id . ', subject: ' . $subject);
        $stmt->bind_param('iiss', $sender_id, $receiver_id, $subject, $content);
        
        error_log('send_message.php: Executing insert query');
        if (!$stmt->execute()) {
            $error = 'Failed to send message: ' . $stmt->error;
            error_log('send_message.php: ' . $error);
            throw new Exception('Failed to send message. Please try again.');
        }
        
        $message_id = $stmt->insert_id;
        $stmt->close();
        error_log('send_message.php: Message inserted successfully - ID: ' . $message_id);
        
        // Create notification for the recipient
        $notification_title = "New Message: " . (strlen($subject) > 30 ? substr($subject, 0, 27) . '...' : $subject);
        $notification_content = substr(strip_tags($content), 0, 100) . (strlen($content) > 100 ? '...' : '');
        
        $notificationQuery = "
            INSERT INTO notifications 
            (user_id, title, content, type, related_id, created_at) 
            VALUES (?, ?, ?, 'message', ?, NOW())
        ";
        
        error_log('send_message.php: Preparing notification query: ' . $notificationQuery);
        $stmt = $conn->prepare($notificationQuery);
        
        if (!$stmt) {
            $error = 'Database prepare failed for notification: ' . $conn->error;
            error_log('send_message.php: ' . $error);
            // Non-critical error, log but don't fail the message send
        } else {
            error_log('send_message.php: Binding notification parameters - user_id: ' . $receiver_id . 
                     ', title: ' . $notification_title . ', content: ' . $notification_content . 
                     ', message_id: ' . $message_id);
            
            $stmt->bind_param('issi', $receiver_id, $notification_title, $notification_content, $message_id);
            
            error_log('send_message.php: Executing notification query');
            if (!$stmt->execute()) {
                // Non-critical error, log but don't fail the message send
                $error = 'Failed to create notification: ' . $stmt->error;
                error_log('send_message.php: ' . $error);
            } else {
                error_log('send_message.php: Notification created successfully');
            }
            $stmt->close();
        }
        
        // Commit transaction
        error_log('send_message.php: Committing transaction');
        if (!$conn->commit()) {
            $error = 'Failed to commit transaction: ' . $conn->error;
            error_log('send_message.php: ' . $error);
            throw new Exception('Failed to complete message sending. Please try again.');
        }
        
        error_log('send_message.php: Transaction committed successfully');
        
        // Prepare response data
        $responseData = [
            'message_id' => $message_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'recipient' => [
                'id' => $receiver['user_id'],
                'name' => trim($receiver['first_name'] . ' ' . $receiver['last_name'])
            ]
        ];
        
        error_log('send_message.php: Sending success response');
        sendResponse(true, [
            'message' => 'Message sent successfully',
            'data' => $responseData
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            error_log('send_message.php: Rolling back transaction due to error');
            if (!$conn->rollback()) {
                $rollbackError = 'Failed to rollback transaction: ' . $conn->error;
                error_log('send_message.php: ' . $rollbackError);
                // Continue with original error
            }
        }
        
        // Log the error before re-throwing
        $error = 'Error in transaction: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        error_log('send_message.php: ' . $error);
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the detailed error
    $errorMessage = 'Error in send_message.php: ' . $e->getMessage() . 
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
    
    error_log('Request data: ' . print_r($requestData, true));
    
    // Close any open database connections
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        // Log any pending database errors
        if ($conn->error) {
            error_log('MySQL error: ' . $conn->error);
        }
        $conn->close();
    }
    
    // Determine appropriate status code
    $errorCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    
    // Prepare error response
    $errorResponse = [
        'error' => 'An error occurred while processing your request.',
        'code' => $errorCode,
        'details' => $e->getMessage()
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
    
    // Send error response
    sendResponse(false, $errorResponse, $errorCode);
}
?>
