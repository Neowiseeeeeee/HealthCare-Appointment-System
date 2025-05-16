<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $patient_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d H:i:s');

    // Fetch upcoming appointments
    $upcoming_sql = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                    FROM appointments a 
                    JOIN users d ON a.doctor_id = d.user_id 
                    WHERE a.patient_id = :patient_id 
                    AND a.appointment_datetime > :current_date 
                    ORDER BY a.appointment_datetime ASC";
    
    $upcoming_stmt = $pdo->prepare($upcoming_sql);
    $upcoming_stmt->execute([
        ':patient_id' => $patient_id,
        ':current_date' => $current_date
    ]);
    $upcoming_appointments = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch past appointments
    $past_sql = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                 FROM appointments a 
                 JOIN users d ON a.doctor_id = d.user_id 
                 WHERE a.patient_id = :patient_id 
                 AND a.appointment_datetime <= :current_date 
                 ORDER BY a.appointment_datetime DESC";
    
    $past_stmt = $pdo->prepare($past_sql);
    $past_stmt->execute([
        ':patient_id' => $patient_id,
        ':current_date' => $current_date
    ]);
    $past_appointments = $past_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'upcoming' => array_map(function($apt) {
                return [
                    'id' => $apt['appointment_id'],
                    'datetime' => $apt['appointment_datetime'],
                    'doctor' => $apt['doctor_first_name'] . ' ' . $apt['doctor_last_name'],
                    'type' => $apt['appointment_type'] ?? 'Regular Checkup',
                    'status' => $apt['status'],
                    'location' => $apt['location'] ?? 'Main Clinic',
                    'notes' => $apt['notes']
                ];
            }, $upcoming_appointments),
            'past' => array_map(function($apt) {
                return [
                    'id' => $apt['appointment_id'],
                    'datetime' => $apt['appointment_datetime'],
                    'doctor' => $apt['doctor_first_name'] . ' ' . $apt['doctor_last_name'],
                    'type' => $apt['appointment_type'] ?? 'Regular Checkup',
                    'status' => $apt['status'],
                    'location' => $apt['location'] ?? 'Main Clinic',
                    'notes' => $apt['notes']
                ];
            }, $past_appointments)
        ]
    ];

    echo json_encode($response);

} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 