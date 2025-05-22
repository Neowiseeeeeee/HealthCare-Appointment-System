<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/db.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    // 1. Get recent appointments (last 7 days)
    $appointments_query = "
        SELECT 
            a.appointment_id,
            a.status,
            a.updated_at as date,
            DATE_FORMAT(a.updated_at, '%b %d, %Y %h:%i %p') as formatted_date,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            d.first_name as doctor_first_name,
            d.last_name as doctor_last_name
        FROM appointments a
        JOIN users p ON a.patient_id = p.user_id
        JOIN users d ON a.doctor_id = d.user_id
        WHERE a.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY a.updated_at DESC
        LIMIT 20
    ";
    
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Get recent user registrations (last 7 days)
    $users_query = "
        SELECT 
            user_id, 
            first_name, 
            last_name, 
            email, 
            role, 
            created_at,
            DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_date
        FROM users 
        WHERE role IN ('patient', 'doctor')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    
    $users_stmt = $pdo->query($users_query);
    $recent_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Get system broadcasts (last 30 days)
    $broadcasts_query = "
        SELECT 
            *,
            DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_date
        FROM notifications 
        WHERE is_system = 1 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ";
    
    $broadcasts_stmt = $pdo->query($broadcasts_query);
    $broadcasts = $broadcasts_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'appointments' => array_map(function($appt) {
            // Determine badge color based on status
            $status_colors = [
                'scheduled' => 'info',
                'confirmed' => 'primary',
                'completed' => 'success',
                'cancelled' => 'danger'
            ];
            
            return [
                'id' => 'appt_' . $appt['appointment_id'],
                'type' => 'appointment',
                'subtype' => $appt['status'],
                'title' => 'Appointment ' . ucfirst($appt['status']),
                'content' => "Appointment with Dr. {$appt['doctor_first_name']} for {$appt['patient_first_name']} has been {$appt['status']}",
                'date' => $appt['date'],
                'formatted_date' => $appt['formatted_date'],
                'badge_color' => $status_colors[strtolower($appt['status'])] ?? 'secondary'
            ];
        }, $appointments),
        'user_registrations' => array_map(function($user) {
            return [
                'id' => 'user_' . $user['user_id'],
                'type' => 'user_registration',
                'title' => 'New ' . ucfirst($user['role']) . ' Registration',
                'content' => "{$user['first_name']} {$user['last_name']} ({$user['email']}) has registered as a {$user['role']}.",
                'date' => $user['created_at'],
                'formatted_date' => $user['formatted_date'],
                'user_role' => $user['role'],
                'badge_color' => $user['role'] === 'doctor' ? 'primary' : 'info'
            ];
        }, $recent_users),
        'system_broadcasts' => array_map(function($broadcast) {
            return [
                'id' => 'broadcast_' . $broadcast['notification_id'],
                'type' => 'broadcast',
                'title' => $broadcast['title'],
                'content' => $broadcast['content'],
                'date' => $broadcast['created_at'],
                'formatted_date' => $broadcast['formatted_date'],
                'badge_color' => 'warning'
            ];
        }, $broadcasts)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
