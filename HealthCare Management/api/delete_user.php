<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering
ob_start();

// Log function for debugging
function log_message($message) {
    $log_file = __DIR__ . '/delete_user.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    session_start();
    
    // Log the start of the request
    log_message("Starting delete user process");
    
    // Include required files
    require_once __DIR__ . '/../includes/db_connect.php';
    require_once __DIR__ . '/../includes/auth.php';
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $user_id = $input['user_id'] ?? null;
    $user_role = $input['user_role'] ?? null;

    log_message("Attempting to delete user ID: $user_id with role: $user_role");

    if (!$user_id || !$user_role) {
        throw new Exception('Missing user ID or role');
    }

    // Log database structure for debugging
    $tables = ['users', 'patients', 'doctors'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
        if ($result) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'] . ' (' . $row['Type'] . ')' . ($row['Key'] === 'PRI' ? ' PRIMARY KEY' : '');
            }
            log_message("Table {$table} structure: " . implode(', ', $columns));
        } else {
            log_message("Failed to get structure for table {$table}: " . $conn->error);
        }
    }

    // Start transaction
    $conn->begin_transaction();
    log_message("Transaction started");

    // Disable foreign key checks temporarily
    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    log_message("Disabled foreign key checks");

    try {
        // Define tables and their respective columns that reference users
        $tablesToClean = [
            'appointments' => [
                'columns' => $user_role === 'doctor' ? ['doctor_id'] : ['patient_id']
            ],
            'messages' => [
                'columns' => ['sender_id', 'receiver_id']
            ],
            'notifications' => [
                'columns' => ['sender_id']
            ],
            'user_notifications' => [
                'columns' => ['user_id']
            ]
        ];


        // Delete from referencing tables first
        foreach ($tablesToClean as $table => $config) {
            foreach ($config['columns'] as $column) {
                $sql = "DELETE FROM `{$table}` WHERE `{$column}` = ?";
                log_message("Executing: $sql with user_id: $user_id");
                
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    throw new Exception("Failed to prepare DELETE statement for {$table}.{$column}: " . $conn->error);
                }
                
                $stmt->bind_param('i', $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to delete from {$table}.{$column}: " . $stmt->error);
                }
                
                $affected = $stmt->affected_rows;
                log_message("Deleted $affected rows from $table.$column");
                $stmt->close();
            }
        }

        // Handle role-specific deletion based on role table structure
        if ($user_role === 'doctor') {
            // For doctors table
            $roleTable = 'doctors';
            $idColumn = 'doctor_id';
            
            // In doctors table, doctor_id is the same as user_id
            $roleId = $user_id;
            
            // Delete from doctors table using user_id (which is the same as doctor_id)
            $sql = "DELETE FROM `{$roleTable}` WHERE `{$idColumn}` = ?";
            log_message("Deleting from {$roleTable} with {$idColumn} = {$roleId}");
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare DELETE statement for {$roleTable}: " . $conn->error);
            }
            
            $stmt->bind_param('i', $roleId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete from {$roleTable}: " . $stmt->error);
            }
            
            $affected = $stmt->affected_rows;
            log_message("Deleted $affected rows from {$roleTable}");
            $stmt->close();
        } else {
            // For patients table
            $roleTable = 'patients';
            $idColumn = 'patient_id';
            
            // In patients table, patient_id is the same as user_id
            $roleId = $user_id;
            
            // Delete from patients table using user_id (which is the same as patient_id)
            $sql = "DELETE FROM `{$roleTable}` WHERE `{$idColumn}` = ?";
            log_message("Deleting from {$roleTable} with {$idColumn} = {$roleId}");
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare DELETE statement for {$roleTable}: " . $conn->error);
            }
            
            $stmt->bind_param('i', $roleId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete from {$roleTable}: " . $stmt->error);
            }
            
            $affected = $stmt->affected_rows;
            log_message("Deleted $affected rows from {$roleTable}");
            $stmt->close();
        }

        // Finally, delete from users table
        $sql = "DELETE FROM `users` WHERE `user_id` = ?";
        log_message("Deleting from users with user_id: $user_id");
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Failed to prepare DELETE statement for users: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete from users: ' . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        log_message("Deleted $affected rows from users");
        $stmt->close();

        // Re-enable foreign key checks
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        log_message("Re-enabled foreign key checks");

        // Commit transaction
        $conn->commit();
        log_message("Transaction committed successfully");

        // Clear output buffer
        ob_clean();

        $response = [
            'success' => true,
            'message' => 'User deleted successfully',
            'deleted_user_id' => $user_id
        ];
        
        log_message("Success response: " . json_encode($response));
        echo json_encode($response);

    } catch (Exception $e) {
        // Re-enable foreign key checks even if there's an error
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        log_message("Error: " . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
        log_message("Transaction rolled back due to error");
    }

    // Log the error
    $error_message = $e->getMessage();
    $error_trace = $e->getTraceAsString();
    log_message("Error: $error_message\nTrace: $error_trace");
    
    // Clear output buffer
    ob_clean();
    
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Error: ' . $error_message,
        'trace' => $error_trace
    ];
    
    log_message("Error response: " . json_encode($response));
    echo json_encode($response);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}

// Flush output buffer
exit(ob_get_clean());
