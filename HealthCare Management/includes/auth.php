<?php
/**
 * Authentication and Authorization Helper Functions
 */

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is an admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin access
 * Redirects to login if not logged in, or to home page if not admin
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
    
    if (!isAdmin()) {
        header('Location: /index.php');
        exit();
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}
