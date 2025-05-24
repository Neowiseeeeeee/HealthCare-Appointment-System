<?php
header('Content-Type: application/json');

// Start session and check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Set charset to handle special characters
$conn->set_charset('utf8mb4');

// Function to get the last 6 months
function getLastSixMonths() {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('M Y', strtotime("-$i months"));
    }
    return $months;
}

// Function to get monthly counts with prepared statements to prevent SQL injection
function getMonthlyCounts($conn, $baseSql, $dateField, $status = null, $params = []) {
    $result = [];
    $months = [];
    $types = '';
    $bindParams = [];
    
    // Generate month starts for the last 6 months
    for ($i = 5; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $months[] = date('M Y', strtotime($monthStart));
        
        // Prepare the SQL with placeholders
        $sql = str_replace(['{date_start}', '{date_end}'], ['?', '?'], $baseSql);
        $types .= 'ss'; // For date_start and date_end
        $bindParams = array_merge($bindParams, [$monthStart, $monthEnd]);
        
        if ($status !== null) {
            $sql = str_replace('{status}', '?', $sql);
            $types .= 's'; // For status
            $bindParams[] = $status;
        }
        
        // Add any additional parameters
        if (!empty($params)) {
            foreach ($params as $param) {
                $sql = str_replace('?', '?', $sql); // Just to maintain the same number of placeholders
                $types .= 's'; // Assume all additional params are strings
                $bindParams[] = $param;
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error: " . $conn->error);
            $result[] = 0;
            continue;
        }
        
        // Bind parameters dynamically
        if (!empty($types) && !empty($bindParams)) {
            $stmt->bind_param($types, ...$bindParams);
        }
        
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res->fetch_row();
            $result[] = (int)($row[0] ?? 0);
        } else {
            error_log("Query execution failed: " . $stmt->error);
            $result[] = 0;
        }
        
        $stmt->close();
        
        // Reset for next iteration
        $types = '';
        $bindParams = [];
    }
    
    return $result;
}

try {
    // Get the last 6 months
    $months = getLastSixMonths();
    
    // Get appointment statistics with proper error handling
    $appointmentSql = "SELECT COUNT(*) FROM appointments 
                      WHERE DATE(appointment_datetime) BETWEEN ? AND ?";
    
    // Get completed appointments
    $completedAppointments = getMonthlyCounts(
        $conn, 
        $appointmentSql . " AND status = ?",
        'appointment_datetime',
        'completed'
    );
    
    // Get pending appointments
    $pendingAppointments = getMonthlyCounts(
        $conn, 
        $appointmentSql . " AND status = ?",
        'appointment_datetime',
        'pending'
    );
    
    // Get cancelled appointments
    $cancelledAppointments = getMonthlyCounts(
        $conn, 
        $appointmentSql . " AND status = ?",
        'appointment_datetime',
        'cancelled'
    );
    
    // Get user registration statistics
    $doctorRegistrations = getMonthlyCounts(
        $conn,
        "SELECT COUNT(*) FROM users WHERE role = 'doctor' AND DATE(created_at) BETWEEN ? AND ?",
        'created_at'
    );
    
    $patientRegistrations = getMonthlyCounts(
        $conn,
        "SELECT COUNT(*) FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?",
        'created_at'
    );
    
    // Get revenue data (assuming there's a payments table)
    $revenue = [0, 0, 0, 0, 0, 0]; // Initialize with zeros
    $revenueSql = "SELECT 
                    DATE_FORMAT(payment_date, '%b %Y') as month_year,
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM payments
                  WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                    AND payment_status = 'completed'
                  GROUP BY YEAR(payment_date), MONTH(payment_date)
                  ORDER BY YEAR(payment_date), MONTH(payment_date)";
    
    $revenueResult = $conn->query($revenueSql);
    if ($revenueResult) {
        $revenueData = [];
        while ($row = $revenueResult->fetch_assoc()) {
            $revenueData[$row['month_year']] = (float)$row['total_amount'];
        }
        
        // Map the revenue data to match our 6-month structure
        $revenue = [];
        foreach ($months as $month) {
            $revenue[] = $revenueData[$month] ?? 0;
        }
    }
    
    // Get today's statistics
    $today = date('Y-m-d');
    $todayStats = [
        'appointments' => 0,
        'new_patients' => 0,
        'revenue' => 0
    ];
    
    // Get today's appointments
    $todayAppointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_datetime) = '$today'");
    if ($todayAppointments) {
        $todayStats['appointments'] = (int)$todayAppointments->fetch_assoc()['count'];
    }
    
    // Get today's new patients
    $todayPatients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient' AND DATE(created_at) = '$today'");
    if ($todayPatients) {
        $todayStats['new_patients'] = (int)$todayPatients->fetch_assoc()['count'];
    }
    
    // Get today's revenue
    $todayRevenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = '$today' AND payment_status = 'completed'");
    if ($todayRevenue) {
        $todayStats['revenue'] = (float)$todayRevenue->fetch_assoc()['total'];
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'months' => $months,
            'appointments' => [
                'completed' => $completedAppointments,
                'pending' => $pendingAppointments,
                'cancelled' => $cancelledAppointments
            ],
            'registrations' => [
                'doctors' => $doctorRegistrations,
                'patients' => $patientRegistrations
            ],
            'revenue' => $revenue,
            'today' => $todayStats,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Cache the response for 1 hour
    header('Cache-Control: max-age=3600, public');
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching analytics data',
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
