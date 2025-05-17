<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = "SELECT 
                id,
                name,
                specialty,
                qualification,
                experience,
                availability,
                image_url
              FROM doctors 
              WHERE status = 'active'
              ORDER BY name ASC";
              
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'doctors' => $doctors
    ]);

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
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 