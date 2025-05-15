<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "docnow_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
    $doctor_id = $_SESSION['user_id'];

    // Verify the appointment belongs to this doctor
    $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $appointment_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Appointment not found or unauthorized']);
        exit();
    }

    // Update appointment status to cancelled
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $appointment_id, $doctor_id);

    if ($stmt->execute()) {
        // Create notification for the patient
        $notification_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, content, related_id)
            SELECT patient_id, 'appointment', 'Appointment Cancelled', 
                   CONCAT('Your appointment on ', DATE_FORMAT(appointment_datetime, '%M %d, %Y at %h:%i %p'), ' has been cancelled by the doctor.'),
                   appointment_id
            FROM appointments
            WHERE appointment_id = ?
        ");
        $notification_stmt->bind_param("i", $appointment_id);
        $notification_stmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error cancelling appointment: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?> 