<?php
session_start();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['patient_id']) || isset($_SESSION['admin_id']);
}

// Handle AJAX session check
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['logged_in' => isLoggedIn()]);
    exit();
}

// Check if user is logged in (for regular page loads)
function checkLogin() {
    if (!isLoggedIn()) {
        header('Location: Login.php');
        exit();
    }
    return isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
}

// Get current patient ID
function getCurrentPatientId() {
    return isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
}

// Function to set user session
function setUserSession($userData) {
    // Clear existing session data
    $_SESSION = [];
    
    // Set common session data
    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['first_name'] = $userData['first_name'];
    $_SESSION['last_name'] = $userData['last_name'];
    $_SESSION['role'] = $userData['role'];
    
    // Set role-specific session data
    if ($userData['role'] === 'patient') {
        $_SESSION['patient_id'] = $userData['user_id'];
    } elseif ($userData['role'] === 'doctor') {
        $_SESSION['doctor_id'] = $userData['user_id'];
    } elseif ($userData['role'] === 'admin') {
        $_SESSION['admin_id'] = $userData['user_id'];
    }
    
    // Set session expiration time (30 minutes of inactivity)
    $_SESSION['last_activity'] = time();
}

// Function to clear session (logout)
function clearSession() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Check session expiration
function checkSessionExpiration() {
    $inactive = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
        clearSession();
        header('Location: auth/Login.php?expired=1');
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Call this function at the beginning of protected pages
checkSessionExpiration();
?>