<?php
session_start();

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['patient_id'])) {
        header('Location: auth/Login.php');
        exit();
    }
    return $_SESSION['patient_id'];
}

// Get current patient ID
function getCurrentPatientId() {
    return isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
}

// Function to set user session
function setUserSession($patient_id, $patient_data) {
    $_SESSION['patient_id'] = $patient_id;
    $_SESSION['patient_name'] = $patient_data['name'];
    $_SESSION['patient_email'] = $patient_data['email'];
}

// Function to clear session (logout)
function clearSession() {
    session_unset();
    session_destroy();
}
?> 