<?php
session_start();

// Prevent caching of restricted pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/Login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Get total number of doctors
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'");
$row = $result->fetch_assoc();
$total_doctors = $row['count'];

// Get total number of patients
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'");
$row = $result->fetch_assoc();
$total_patients = $row['count'];

// Get total number of appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments");
$row = $result->fetch_assoc();
$total_appointments = $row['count'];

// Get all appointments with patient and doctor details
$all_appointments = [];
$pending_appointments_list = [];
$pending_appointments = 0;
$total_appointments = 0;

// Debug mode disabled
try {
    // First, check if the appointments table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($table_check->num_rows === 0) {
        throw new Exception("The 'appointments' table does not exist in the database.");
    }

    // Check if the users table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($table_check->num_rows === 0) {
        throw new Exception("The 'users' table does not exist in the database.");
    }

    // Get all appointments query
    $appointments_query = "
        SELECT a.*, 
               COALESCE(p.first_name, 'Unknown') as patient_first_name, 
               COALESCE(p.last_name, 'User') as patient_last_name, 
               COALESCE(p.email, 'N/A') as patient_email,
               COALESCE(d.first_name, 'Doctor') as doctor_first_name, 
               COALESCE(d.last_name, '') as doctor_last_name, 
               COALESCE(d.email, 'N/A') as doctor_email,
               DATE(a.appointment_datetime) as appointment_date,
               TIME(a.appointment_datetime) as appointment_time
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.user_id
        LEFT JOIN users d ON a.doctor_id = d.user_id
        ORDER BY a.appointment_datetime DESC
    ";
    $result = $conn->query($appointments_query);
    
    if ($result === false) {
        throw new Exception("Error fetching appointments: " . $conn->error);
    }
    
    $all_appointments = $result->fetch_all(MYSQLI_ASSOC);

    // Get pending appointments query
    $pending_query = "
        SELECT a.*, 
               COALESCE(p.first_name, 'Unknown') as patient_first_name, 
               COALESCE(p.last_name, 'User') as patient_last_name, 
               COALESCE(p.email, 'N/A') as patient_email,
               COALESCE(d.first_name, 'Doctor') as doctor_first_name, 
               COALESCE(d.last_name, '') as doctor_last_name, 
               COALESCE(d.email, 'N/A') as doctor_email,
               DATE(a.appointment_datetime) as appointment_date,
               TIME(a.appointment_datetime) as appointment_time
        FROM appointments a
        LEFT JOIN users p ON a.patient_id = p.user_id
        LEFT JOIN users d ON a.doctor_id = d.user_id
        WHERE a.status = 'pending'
        ORDER BY a.appointment_datetime
    ";
    $result = $conn->query($pending_query);
    
    if ($result === false) {
        throw new Exception("Error fetching pending appointments: " . $conn->error);
    }
    
    $pending_appointments_list = $result->fetch_all(MYSQLI_ASSOC);

    // Get counts for the stats
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
    if ($result === false) {
        throw new Exception("Error counting pending appointments: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $pending_appointments = $row['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    if ($result === false) {
        throw new Exception("Error counting total appointments: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_appointments = $row['count'];
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Database Error: " . $e->getMessage());
    
    // Display a user-friendly error message
    die("<div style='padding: 20px; margin: 20px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>
            <h3>Database Error</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please check your database configuration and ensure all required tables exist.</p>
            <p>For more details, check the server error log.</p>
        </div>");
}

// Get recent users (last 8 registrations)
$result = $conn->query("
    SELECT user_id, first_name, last_name, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 8
");
if ($result) {
    $recent_users = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $recent_users = [];
    // Uncomment the line below for debugging
    // echo "Error in recent users query: " . $conn->error;
}

// Check if a profile_image column exists in the users table
$has_profile_image = false;
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($check_column && $check_column->num_rows > 0) {
    $has_profile_image = true;
}

// Check if a profile_picture column exists in the users table
$has_profile_picture = false;
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($check_column && $check_column->num_rows > 0) {
    $has_profile_picture = true;
}

// Check if an image column exists in the users table
$has_image = false;
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'image'");
if ($check_column && $check_column->num_rows > 0) {
    $has_image = true;
}

// Get all doctors
$sql = "SELECT * FROM users WHERE role = 'doctor' ORDER BY last_name, first_name";
$result = $conn->query($sql);
if ($result) {
    $doctors = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $doctors = [];
    echo "Error in doctor query: " . $conn->error;
}

// Check if doctors table exists and get details
try {
    $doctor_details = [];
    $tables_to_check = ["doctors", "doctor_profiles", "doctor_profile"];
    $doctor_table_exists = false;
    $doctor_table_name = "";
    
    foreach ($tables_to_check as $table) {
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check_table && $check_table->num_rows > 0) {
            $doctor_table_exists = true;
            $doctor_table_name = $table;
            break;
        }
    }
    
    if ($doctor_table_exists) {
        // Check which columns exist in the doctor table
        $columns = [];
        $column_result = $conn->query("SHOW COLUMNS FROM $doctor_table_name");
        if ($column_result) {
            while ($column = $column_result->fetch_assoc()) {
                $columns[] = $column['Field'];
            }
        }
        
        // Find ID field name (could be doctor_id, user_id, id)
        $id_field = "doctor_id"; // default
        if (in_array("user_id", $columns)) {
            $id_field = "user_id";
        } elseif (in_array("id", $columns)) {
            $id_field = "id";
        }
        
        // For each doctor, get their details
        foreach ($doctors as $doctor) {
            $detail_sql = "SELECT * FROM $doctor_table_name WHERE $id_field = " . $doctor['user_id'];
            $detail_result = $conn->query($detail_sql);
            if ($detail_result && $detail_result->num_rows > 0) {
                $doctor_details[$doctor['user_id']] = $detail_result->fetch_assoc();
            }
        }
    }
    
    // Add profile pictures to doctor data if they exist in the main users table
    if ($has_profile_image || $has_profile_picture || $has_image) {
        foreach ($doctors as $idx => $doctor) {
            if ($has_profile_image && !empty($doctor['profile_image'])) {
                if (!isset($doctor_details[$doctor['user_id']])) {
                    $doctor_details[$doctor['user_id']] = [];
                }
                $doctor_details[$doctor['user_id']]['profile_picture'] = $doctor['profile_image'];
            }
            elseif ($has_profile_picture && !empty($doctor['profile_picture'])) {
                if (!isset($doctor_details[$doctor['user_id']])) {
                    $doctor_details[$doctor['user_id']] = [];
                }
                $doctor_details[$doctor['user_id']]['profile_picture'] = $doctor['profile_picture'];
            }
            elseif ($has_image && !empty($doctor['image'])) {
                if (!isset($doctor_details[$doctor['user_id']])) {
                    $doctor_details[$doctor['user_id']] = [];
                }
                $doctor_details[$doctor['user_id']]['profile_picture'] = $doctor['image'];
            }
        }
    }
} catch (Exception $e) {
    // Just proceed without doctor details
}

// Get all patients
$sql = "SELECT * FROM users WHERE role = 'patient' ORDER BY last_name, first_name";
$result = $conn->query($sql);
if ($result) {
    $patients = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $patients = [];
    echo "Error in patient query: " . $conn->error;
}

// Check if patients table exists and get details
try {
    $patient_details = [];
    $tables_to_check = ["patients", "patient_profiles", "patient_profile"];
    $patient_table_exists = false;
    $patient_table_name = "";
    
    foreach ($tables_to_check as $table) {
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check_table && $check_table->num_rows > 0) {
            $patient_table_exists = true;
            $patient_table_name = $table;
            break;
        }
    }
    
    if ($patient_table_exists) {
        // Check which columns exist in the patient table
        $columns = [];
        $column_result = $conn->query("SHOW COLUMNS FROM $patient_table_name");
        if ($column_result) {
            while ($column = $column_result->fetch_assoc()) {
                $columns[] = $column['Field'];
            }
        }
        
        // Find ID field name (could be patient_id, user_id, id)
        $id_field = "patient_id"; // default
        if (in_array("user_id", $columns)) {
            $id_field = "user_id";
        } elseif (in_array("id", $columns)) {
            $id_field = "id";
        }
        
        // For each patient, get their details
        foreach ($patients as $patient) {
            $detail_sql = "SELECT * FROM $patient_table_name WHERE $id_field = " . $patient['user_id'];
            $detail_result = $conn->query($detail_sql);
            if ($detail_result && $detail_result->num_rows > 0) {
                $patient_details[$patient['user_id']] = $detail_result->fetch_assoc();
            }
        }
    }
    
    // Add profile pictures to patient data if they exist in the main users table
    if ($has_profile_image || $has_profile_picture || $has_image) {
        foreach ($patients as $idx => $patient) {
            if ($has_profile_image && !empty($patient['profile_image'])) {
                if (!isset($patient_details[$patient['user_id']])) {
                    $patient_details[$patient['user_id']] = [];
                }
                $patient_details[$patient['user_id']]['profile_picture'] = $patient['profile_image'];
            }
            elseif ($has_profile_picture && !empty($patient['profile_picture'])) {
                if (!isset($patient_details[$patient['user_id']])) {
                    $patient_details[$patient['user_id']] = [];
                }
                $patient_details[$patient['user_id']]['profile_picture'] = $patient['profile_picture'];
            }
            elseif ($has_image && !empty($patient['image'])) {
                if (!isset($patient_details[$patient['user_id']])) {
                    $patient_details[$patient['user_id']] = [];
                }
                $patient_details[$patient['user_id']]['profile_picture'] = $patient['image'];
            }
        }
    }
} catch (Exception $e) {
    // Just proceed without patient details
}

// Get recent system logs (for notifications)
$result = $conn->query("
    SELECT log_id, user_id, action, description, created_at
    FROM system_logs
    ORDER BY created_at DESC
    LIMIT 10
");
if ($result) {
    $system_logs = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $system_logs = [];
}

// Get appointment data for analytics (last 6 months)
$result = $conn->query("
    SELECT 
        DATE_FORMAT(appointment_datetime, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM appointments
    WHERE appointment_datetime >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_datetime, '%Y-%m')
    ORDER BY month ASC
");
if ($result) {
    $appointment_analytics = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $appointment_analytics = [];
}

// Get user registration data for analytics (last 6 months)
$result = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as doctors,
        SUM(CASE WHEN role = 'patient' THEN 1 ELSE 0 END) as patients
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($result) {
    $registration_analytics = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $registration_analytics = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS and Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/prevent-back-navigation.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #2563eb;
            padding: 0.5rem 1rem;
            color: white;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 50;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-section img {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            background-color: white;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            z-index: 9999;
        }

        .dropdown {
            position: relative;
            display: inline-block;
            z-index: 9999;
        }

        .dropdown-toggle {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .dropdown-toggle i {
            transition: transform 0.2s;
        }

        .dropdown-toggle.active i {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            background: #FFFFFF;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            z-index: 9999;
            display: none;
            border: 1px solid #e5e7eb;
            opacity: 1;
            visibility: visible;
        }

        .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #000000;
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s;
            cursor: pointer;
            width: 100%;
            background-color: #FFFFFF;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
            color: #000000;
        }

        .dropdown-item i {
            color: #000000;
            width: 1rem;
            text-align: center;
        }

        .dropdown-item span {
            color: #000000;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0.5rem 0;
        }

        .main-content {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 100%;
            padding: 0 1rem;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .dashboard-header {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #3b82f6;
        }

        .stat-number {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            min-height: 500px;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            background: white;
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header .title {
            font-size: 1rem;
            color: #1f2937;
        }

        .search-box {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 100%;
            max-width: 200px;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .card-body {
            padding: 0.75rem;
            overflow-y: auto;
            flex: 1;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        /* Custom scrollbar styles for webkit browsers */
        .card-body::-webkit-scrollbar {
            width: 6px;
        }

        .card-body::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .card-body::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 3px;
        }

        /* Add hover effect to scrollable items */
        .card-body > div:hover {
            background: #f3f4f6;
            transition: background-color 0.2s;
        }

        .user-card {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .user-card:last-child {
            border-bottom: none;
        }

        .user-card:hover {
            background-color: #ffffff;
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
        }

        .user-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .user-name {
            font-weight: 600;
            color: #1f2937;
            transition: color 0.2s ease;
        }

        .user-card:hover .user-name {
            color: #2563eb;
        }

        .user-email {
            font-size: 0.75rem;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        .user-card:hover .user-email {
            color: #4b5563;
        }

        .user-role {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .role-admin {
            background-color: #ef4444;
            color: white;
        }

        .role-doctor {
            background-color: #10b981;
            color: white;
        }

        .role-patient {
            background-color: #3b82f6;
            color: white;
        }

        .footer {
            background-color: #1f2937;
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
        }
        
        /* Analytics Card Styles */
        .analytics-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            display: flex;
            flex-direction: column;
        }
        
        .analytics-header {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .analytics-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .analytics-tab {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 500;
        }
        
        .analytics-tab:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            transform: translateY(-1px);
        }
        
        .analytics-tab.active {
            background-color: #2563eb;
            color: white;
            border-color: #2563eb;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .analytics-tab.active:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        .chart-container {
            flex: 1;
            min-height: 300px;
            position: relative;
        }
        
        /* Notification Items */
        .notification-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }
        
        .notification-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .notification-title {
            font-weight: 500;
            color: #1f2937;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .notification-content {
            font-size: 0.875rem;
            color: #4b5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Notification Modal Styles */
        #notificationModal .modal-content {
            max-width: 600px;
            margin: 2rem auto;
        }
        
        #notificationModal .notification-header {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        #notificationModal .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            text-transform: capitalize;
        }
        
        #notificationModalSubtitle {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-top: 0.5rem;
        }
        
        #notificationContent {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #4b5563;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        #notificationModal .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        
        .notification-badge {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
            display: inline-block;
            margin-top: 0.25rem;
        }
        
        .badge-info {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #047857;
        }
        
        .badge-error {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }

        .modal-search {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 60px;
            z-index: 1;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .user-profile-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }

        .user-profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6b7280;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 3px solid #e5e7eb;
        }

        .user-avatar.doctor {
            border-color: #10b981;
        }

        .user-avatar.patient {
            border-color: #3b82f6;
        }

        .user-avatar.admin {
            border-color: #ef4444;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-profile-name {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }

        .user-profile-role {
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .user-profile-detail {
            width: 100%;
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-profile-detail i {
            width: 16px;
        }

        /* User Details Modal */
        .user-details-container {
            display: flex;
            gap: 2rem;
        }

        .user-details-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6b7280;
            border: 4px solid #e5e7eb;
            flex-shrink: 0;
            overflow: hidden;
        }

        .user-details-avatar.doctor {
            border-color: #10b981;
        }

        .user-details-avatar.patient {
            border-color: #3b82f6;
        }

        .user-details-avatar.admin {
            border-color: #ef4444;
        }

        .user-details-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details-info {
            flex: 1;
        }

        .user-details-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .user-details-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-group-value {
            padding: 0.625rem;
            background-color: #f9fafb;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
            font-size: 0.875rem;
            color: #1f2937;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-secondary {
            background-color: #ffffff;
            color: #1f2937;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background-color: #f3f4f6;
        }

        /* Appointment Cards */
        .appointments-list {
            display: flex;
            flex-direction: column;
        }

        .appointments-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 8px;
            padding-bottom: 15px;
        }

        .appointment-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
            min-height: 180px;
            display: flex;
            flex-direction: column;
        }

        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .appointment-id {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
        }

        .appointment-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #8a6d3b;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .appointment-body {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 140px;
        }

        .appointment-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
            flex: 1;
        }

        .appointment-patient,
        .appointment-doctor,
        .appointment-date,
        .appointment-time {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #4a4a4a;
        }

        .appointment-patient i,
        .appointment-doctor i,
        .appointment-date i,
        .appointment-time i {
            margin-right: 8px;
            color: #6c757d;
            width: 16px;
            text-align: center;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
            margin-right: 4px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .appointment-notes {
            display: flex;
            align-items: flex-start;
            margin-top: 12px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: #6c757d;
            border-left: 3px solid #4e73df;
            max-height: 100px;
            overflow-y: auto;
        }

        .appointment-notes i {
            margin-right: 8px;
            color: #4e73df;
            margin-top: 2px;
        }

        .notes-label {
            font-weight: 600;
            color: #4a4a4a;
            margin-right: 4px;
        }

        .notes-text {
            color: #4a4a4a;
            line-height: 1.5;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
            border: none;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr 2fr;
            }
            
            .user-details-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .user-details-info {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .modal-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .appointment-info {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-section">
            <img src="assets/images/Logo.jpg" alt="DocNow Logo">
            <span>DocNow Admin</span>
        </div>
        <div class="nav-links">
            <div class="dropdown">
                <div class="dropdown-toggle" id="userDropdown">
                    <span><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <a href="change_password.php" class="dropdown-item">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="auth/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="dashboard-header">
                <div>
                    <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                    <p class="text-gray-600 text-sm">Welcome back, <?php echo htmlspecialchars($admin['first_name']); ?>!</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="admin/notification_broadcast.php" class="btn btn-primary">
                        <i class="fas fa-bullhorn"></i> System Notifications
                    </a>
                    <span class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>

            <!-- Stats -->
            <div class="dashboard-stats">
                <div class="stat-card" onclick="showDoctorsModal()">
                    <i class="fas fa-user-md"></i>
                    <div class="stat-number"><?php echo $total_doctors; ?></div>
                    <div class="stat-label">Total Doctors</div>
                </div>
                <div class="stat-card" onclick="showPatientsModal()">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $total_patients; ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-card" onclick="showAppointmentsModal()">
                    <i class="fas fa-calendar-check"></i>
                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card" onclick="showPendingAppointmentsModal()">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $pending_appointments; ?></div>
                    <div class="stat-label">Pending Appointments</div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="dashboard-grid">
                <!-- Recent Users -->
                <div class="card">
                    <div class="card-header">
                        <div class="title">Recent User Registrations</div>
                        <input type="text" class="search-box" placeholder="Search users..." id="userSearchBox">
                    </div>
                    <div class="card-body" id="recentUsersList">
                        <?php if (empty($recent_users)): ?>
                            <div class="text-center py-4 text-gray-500">No recent registrations</div>
                        <?php else: ?>
                            <?php foreach ($recent_users as $user): ?>
                                <div class="user-card" onclick="showUserDetails('<?php echo strtolower($user['role']); ?>', <?php echo $user['user_id']; ?>)">
                                    <div class="user-card-content">
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                        <div class="user-role role-<?php echo strtolower($user['role']); ?>"><?php echo ucfirst($user['role']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="analytics-card">
                    <div class="analytics-header">
                        <span>Activity Analytics</span>
                        <div class="analytics-tabs">
                            <div class="analytics-tab active" id="appointmentsTab" data-tab="appointments" onclick="switchAnalyticsTab('appointments')">Appointments</div>
                            <div class="analytics-tab" id="registrationsTab" data-tab="registrations" onclick="switchAnalyticsTab('registrations')">Registrations</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="analyticsChart"></canvas>
                    </div>
                </div>

                <!-- System Notifications -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="title">System Notifications</div>
                        <button type="button" class="btn-refresh" onclick="loadAdminNotifications()" style="background: none; border: none; cursor: pointer; color: #4f46e5;">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body" id="adminNotificationsList" style="max-height: 600px; overflow-y: auto;">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p>Loading notifications...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Doctors Modal -->
    <div id="doctorsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">All Doctors</h3>
                <button class="btn-secondary" onclick="closeModal('doctorsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-search">
                <i class="fas fa-search" style="color: #6b7280;"></i>
                <input type="text" class="search-box" placeholder="Search doctors..." id="doctorSearchBox" style="border: none; background: transparent; max-width: none; width: 100%;">
            </div>
            <div class="modal-body">
                <div class="modal-grid" id="doctorsGrid">
                    <?php if (empty($doctors)): ?>
                        <div class="text-center py-4 text-gray-500">No doctors found</div>
                    <?php else: ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <div class="user-profile-card" onclick="showUserDetails('doctor', <?php echo $doctor['user_id']; ?>)">
                                <div class="user-avatar doctor">
                                    <?php 
                                    $profile_picture = null;
                                    
                                    // Check for profile picture in doctor details
                                    if (isset($doctor_details[$doctor['user_id']]['profile_picture'])) {
                                        $profile_picture = $doctor_details[$doctor['user_id']]['profile_picture'];
                                    }
                                    
                                    // No need for this check as it's redundant with the previous check
                                    
                                    // Check in user record if we have any of these fields
                                    if (empty($profile_picture)) {
                                        if (isset($doctor['profile_picture']) && !empty($doctor['profile_picture'])) {
                                            $profile_picture = $doctor['profile_picture'];
                                        } elseif (isset($doctor['profile_image']) && !empty($doctor['profile_image'])) {
                                            $profile_picture = $doctor['profile_image'];
                                        } elseif (isset($doctor['image']) && !empty($doctor['image'])) {
                                            $profile_picture = $doctor['image'];
                                        }
                                    }
                                    
                                    // If we have a value and it's a relative path, make sure it's accessible
                                    if (!empty($profile_picture) && !filter_var($profile_picture, FILTER_VALIDATE_URL)) {
                                        // Handle cases where path might be stored in different formats
                                        if (substr($profile_picture, 0, 1) !== '/' && substr($profile_picture, 0, 4) !== 'http') {
                                            $possible_paths = [
                                                $profile_picture,
                                                "uploads/" . $profile_picture,
                                                "assets/images/" . $profile_picture,
                                                "assets/uploads/" . $profile_picture,
                                                "images/" . $profile_picture,
                                                "profiles/" . $profile_picture
                                            ];
                                            
                                            $profile_picture = null;
                                            foreach ($possible_paths as $path) {
                                                if (file_exists($path)) {
                                                    $profile_picture = $path;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (!empty($profile_picture)): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Doctor Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user-md"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="user-profile-name"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></div>
                                <div class="user-profile-role role-doctor">Doctor</div>
                                <?php 
                                $specialty = null;
                                if (isset($doctor_details[$doctor['user_id']]['specialty'])) {
                                    $specialty = $doctor_details[$doctor['user_id']]['specialty'];
                                }
                                ?>
                                <div class="user-profile-detail">
                                    <i class="fas fa-stethoscope"></i>
                                    <?php echo !empty($specialty) ? htmlspecialchars($specialty) : 'Specialty not specified'; ?>
                                </div>
                                <div class="user-profile-detail">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($doctor['email']); ?>
                                </div>
                                <?php 
                                $years_experience = null;
                                if (isset($doctor_details[$doctor['user_id']]['years_experience'])) {
                                    $years_experience = $doctor_details[$doctor['user_id']]['years_experience'];
                                }
                                if (!empty($years_experience)): 
                                ?>
                                <div class="user-profile-detail">
                                    <i class="fas fa-briefcase"></i>
                                    <?php echo htmlspecialchars($years_experience); ?> years experience
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Patients Modal -->
    <div id="patientsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">All Patients</h3>
                <button class="btn-secondary" onclick="closeModal('patientsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-search">
                <i class="fas fa-search" style="color: #6b7280;"></i>
                <input type="text" class="search-box" placeholder="Search patients..." id="patientSearchBox" style="border: none; background: transparent; max-width: none; width: 100%;">
            </div>
            <div class="modal-body">
                <div class="modal-grid" id="patientsGrid">
                    <?php if (empty($patients)): ?>
                        <div class="text-center py-4 text-gray-500">No patients found</div>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <div class="user-profile-card" onclick="showUserDetails('patient', <?php echo $patient['user_id']; ?>)">
                                <div class="user-avatar patient">
                                    <?php 
                                    $profile_picture = null;
                                    
                                    // Check for profile picture in patient details
                                    if (isset($patient_details[$patient['user_id']]['profile_picture'])) {
                                        $profile_picture = $patient_details[$patient['user_id']]['profile_picture'];
                                    }
                                    
                                    // Check for picture_path in patient details (the field used in the patients table)
                                    if (empty($profile_picture) && isset($patient_details[$patient['user_id']]['picture_path'])) {
                                        $profile_picture = $patient_details[$patient['user_id']]['picture_path'];
                                    }
                                    
                                    // Check in user record if we have any of these fields
                                    if (empty($profile_picture)) {
                                        if (isset($patient['profile_picture']) && !empty($patient['profile_picture'])) {
                                            $profile_picture = $patient['profile_picture'];
                                        } elseif (isset($patient['profile_image']) && !empty($patient['profile_image'])) {
                                            $profile_picture = $patient['profile_image'];
                                        } elseif (isset($patient['image']) && !empty($patient['image'])) {
                                            $profile_picture = $patient['image'];
                                        }
                                    }
                                    
                                    // If we have a value and it's a relative path, make sure it's accessible
                                    if (!empty($profile_picture) && !filter_var($profile_picture, FILTER_VALIDATE_URL)) {
                                        // Handle cases where path might be stored in different formats
                                        if (substr($profile_picture, 0, 1) !== '/' && substr($profile_picture, 0, 4) !== 'http') {
                                            $possible_paths = [
                                                $profile_picture,
                                                "uploads/" . $profile_picture,
                                                "assets/images/" . $profile_picture,
                                                "assets/uploads/" . $profile_picture,
                                                "images/" . $profile_picture,
                                                "profiles/" . $profile_picture
                                            ];
                                            
                                            $profile_picture = null;
                                            foreach ($possible_paths as $path) {
                                                if (file_exists($path)) {
                                                    $profile_picture = $path;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (!empty($profile_picture)): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Patient Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="user-profile-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                <div class="user-profile-role role-patient">Patient</div>
                                <div class="user-profile-detail">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($patient['email']); ?>
                                </div>
                                <div class="user-profile-detail">
                                    <i class="fas fa-calendar-alt"></i>
                                    Joined: <?php echo date('M j, Y', strtotime($patient['created_at'])); ?>
                                </div>
                                <?php 
                                $medical_history = null;
                                if (isset($patient_details[$patient['user_id']]['medical_history'])) {
                                    $medical_history = $patient_details[$patient['user_id']]['medical_history'];
                                }
                                if (!empty($medical_history)): 
                                ?>
                                <div class="user-profile-detail">
                                    <i class="fas fa-notes-medical"></i>
                                    Has medical history
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Modal -->
    <div id="appointmentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">All Appointments</h3>
                <button class="btn-secondary" onclick="closeModal('appointmentsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-search">
                <i class="fas fa-search"></i>
                <input type="text" class="search-box" placeholder="Search appointments..." oninput="searchAppointments(this.value, 'appointmentsModal')">
            </div>
            <div class="modal-body">
                <div class="appointments-list">
                    <?php if (empty($all_appointments)): ?>
                        <div class="text-center py-4 text-gray-500">No appointments found</div>
                    <?php else: ?>
                        <?php foreach ($all_appointments as $appt): 
                            // Parse the appointment datetime
                            $appointment_datetime = new DateTime($appt['appointment_datetime']);
                            $formatted_date = $appointment_datetime->format('M j, Y');
                            $formatted_time = $appointment_datetime->format('g:i A');
                        ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <span class="appointment-id">#<?php echo htmlspecialchars($appt['appointment_id']); ?></span>
                                    <span class="appointment-status status-<?php echo strtolower($appt['status']); ?>">
                                        <?php echo ucfirst($appt['status']); ?>
                                    </span>
                                </div>
                                <div class="appointment-body">
                                    <div class="appointment-info">
                                        <div class="appointment-patient">
                                            <i class="fas fa-user"></i>
                                            <span class="info-value">
                                                <?php echo htmlspecialchars($appt['patient_first_name'] . ' ' . $appt['patient_last_name']); ?>
                                            </span>
                                        </div>
                                        <div class="appointment-doctor">
                                            <i class="fas fa-user-md"></i>
                                            <span class="info-value">
                                                Dr. <?php echo htmlspecialchars($appt['doctor_first_name'] . ' ' . $appt['doctor_last_name']); ?>
                                            </span>
                                        </div>
                                        <div class="appointment-date">
                                            <i class="far fa-calendar"></i>
                                            <span class="info-value"><?php echo $formatted_date; ?></span>
                                        </div>
                                        <div class="appointment-time">
                                            <i class="far fa-clock"></i>
                                            <span class="info-value"><?php echo $formatted_time; ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($appt['notes'])): ?>
                                        <div class="appointment-notes">
                                            <i class="far fa-note-sticky"></i>
                                            <span class="notes-text"><?php echo htmlspecialchars($appt['notes']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Appointments Modal -->
    <div id="pendingAppointmentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pending Appointments</h3>
                <button class="btn-secondary" onclick="closeModal('pendingAppointmentsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-search">
                <i class="fas fa-search" style="color: #6b7280;"></i>
                <input type="text" class="search-box" placeholder="Search pending appointments..." id="pendingAppointmentSearchBox" style="border: none; background: transparent; max-width: none; width: 100%;">
            </div>
            <div class="modal-body">
                <div class="appointments-list">
                    <?php if (empty($pending_appointments_list)): ?>
                        <div class="text-center py-4 text-gray-500">No pending appointments found</div>
                    <?php else: ?>
                        <?php foreach ($pending_appointments_list as $appt): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <span class="appointment-id">#<?php echo htmlspecialchars($appt['appointment_id']); ?></span>
                                    <span class="appointment-status status-pending">
                                        <?php echo ucfirst($appt['status']); ?>
                                    </span>
                                </div>
                                <div class="appointment-body">
                                    <div class="appointment-info">
                                        <div class="appointment-patient">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($appt['patient_first_name'] . ' ' . $appt['patient_last_name']); ?>
                                        </div>
                                        <div class="appointment-doctor">
                                            <i class="fas fa-user-md"></i>
                                            <?php echo htmlspecialchars($appt['doctor_first_name'] . ' ' . $appt['doctor_last_name']); ?>
                                        </div>
                                        <div class="appointment-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($appt['appointment_date'])); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($appt['appointment_time'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($appt['notes'])): ?>
                                        <div class="appointment-notes">
                                            <i class="far fa-note-sticky"></i>
                                            <span class="notes-text"><?php echo htmlspecialchars($appt['notes']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalUserTitle">User Details</h3>
                <button class="btn-secondary" onclick="closeModal('userDetailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-actions">
                <button id="deleteUserBtn" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash-alt"></i> Delete User
                </button>
                <button class="btn btn-secondary" onclick="closeModal('userDetailsModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="btn-secondary" onclick="closeModal('confirmDeleteModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <p>All user data, including appointments and messages, will be permanently deleted.</p>
            </div>
            <div class="modal-actions">
                <button id="cancelDeleteBtn" class="btn btn-secondary" onclick="closeModal('confirmDeleteModal')">Cancel</button>
                <button id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteUser()">Delete User</button>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title" id="notificationModalTitle">Notification Details</h3>
                <button class="btn-secondary" onclick="closeModal('notificationModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="notification-header mb-4">
                    <div class="flex items-center justify-between">
                        <span id="notificationBadge" class="badge">System</span>
                        <span id="notificationDate" class="text-sm text-gray-500">May 20, 2025 10:30 AM</span>
                    </div>
                    <h2 id="notificationModalSubtitle" class="text-xl font-bold mt-2">Notification Title</h2>
                </div>
                <div id="notificationContent" class="notification-content">
                    <!-- Content will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('notificationModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> DocNow. All rights reserved.</p>
    </footer>

    <script>
        // Global variables
        let currentUserId = null;
        let currentUserRole = null;
        let analyticsChart = null;
        
        // Load admin notifications
        function loadAdminNotifications() {
            const container = document.getElementById('adminNotificationsList');
            if (!container) return;
            
            // Show loading state
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading notifications...</p>
                </div>`;
            
            fetch('api/fetch_admin_notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                ${data.message || 'Failed to load notifications'}
                            </div>`;
                        return;
                    }
                    
                    // Clear container
                    container.innerHTML = '';
                    
                    // Combine all notification types and sort by date (newest first)
                    const allItems = [
                        ...(data.appointments || []).map(item => ({ ...item, itemType: 'appointment' })),
                        ...(data.user_registrations || []).map(item => ({ ...item, itemType: 'registration' })),
                        ...(data.system_broadcasts || []).map(item => ({ ...item, itemType: 'broadcast' }))
                    ].sort((a, b) => new Date(b.date) - new Date(a.date));
                    
                    if (allItems.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-4 text-gray-500">
                                No notifications found
                            </div>`;
                        return;
                    }
                    
                    // Render each item
                    allItems.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'notification-item';
                        itemDiv.dataset.id = item.id;
                        
                        // Determine icon and badge based on item type
                        let icon = 'fa-bell';
                        let badgeText = '';
                        let badgeClass = `badge-${item.badge_color || 'info'}`;
                        
                        switch(item.itemType) {
                            case 'appointment':
                                icon = 'fa-calendar-check';
                                badgeText = 'Appointment';
                                break;
                            case 'registration':
                                icon = item.user_role === 'doctor' ? 'fa-user-md' : 'fa-user';
                                badgeText = 'New ' + item.user_role;
                                break;
                            case 'broadcast':
                                icon = 'fa-bullhorn';
                                badgeText = 'System';
                                break;
                        }
                        
                        // Format the content with proper escaping
                        const title = escapeHtml(item.title || 'Notification');
                        const content = escapeHtml(item.content || '');
                        const date = item.formatted_date || new Date(item.date).toLocaleString();
                        
                        itemDiv.innerHTML = `
                            <div class="flex items-start p-3 border-b border-gray-100 hover:bg-gray-50 notification-item-content">
                                <div class="flex-shrink-0 mt-1">
                                    <span class="badge ${badgeClass} text-xs">${badgeText}</span>
                                </div>
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900">
                                            <i class="fas ${icon} mr-1"></i>
                                            ${title}
                                        </p>
                                        <p class="text-xs text-gray-500">${date}</p>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">${content}</p>
                                </div>
                            </div>`;
                            
                        // Add click event to show full notification
                        itemDiv.addEventListener('click', () => {
                            showNotificationModal({
                                title: title,
                                content: item.content || content, // Use full content from item if available
                                date: date,
                                badgeClass: badgeClass,
                                badgeText: badgeText,
                                icon: icon
                            });
                        });
                        
                        container.appendChild(itemDiv);
                    });
                    
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            Failed to load notifications. Please try again.
                            <button class="btn btn-sm btn-link" onclick="loadAdminNotifications()">Retry</button>
                        </div>`;
                });
        }
        
        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadAdminNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadAdminNotifications, 30000);
        });
        
        // Global variables
        let currentAnalyticsTab = 'appointments';

        // Function to fetch analytics data
        function fetchAnalyticsData() {
            return new Promise((resolve, reject) => {
                fetch('api/fetch_analytics.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            resolve(data.data);
                        } else {
                            throw new Error(data.message || 'Failed to load analytics data');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching analytics data:', error);
                        reject(error);
                    });
            });
        }

        // Initialize analytics chart
        async function initializeAnalyticsChart() {
            const ctx = document.getElementById('analyticsChart').getContext('2d');
            
            try {
                const analyticsData = await fetchAnalyticsData();
                
                // Process appointment data
                const appointmentsData = {
                    labels: analyticsData.months,
                    datasets: [
                        {
                            label: 'Completed',
                            data: analyticsData.appointments.completed,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Pending',
                            data: analyticsData.appointments.pending,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Cancelled',
                            data: analyticsData.appointments.cancelled,
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 2
                        }
                    ]
                };
                
                // Process registration data
                const registrationsData = {
                    labels: analyticsData.months,
                    datasets: [
                        {
                            label: 'Doctors',
                            data: analyticsData.registrations.doctors,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Patients',
                            data: analyticsData.registrations.patients,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2
                        }
                    ]
                };
                
                // Process revenue data (if available)
                const revenueData = {
                    labels: analyticsData.months,
                    datasets: [
                        {
                            label: 'Appointments',
                            data: analyticsData.revenue,
                            borderColor: 'rgba(124, 58, 237, 1)',
                            backgroundColor: 'rgba(124, 58, 237, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                };
                
                analyticsChart = new Chart(ctx, {
                    type: 'line',
                    data: appointmentsData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error initializing analytics chart:', error);
            }
        }

        // Switch analytics tab
        function switchAnalyticsTab(tab) {
            if (tab === currentAnalyticsTab) return;
            
            currentAnalyticsTab = tab;
            
            // Update active tab styles
            document.querySelectorAll('.analytics-tab').forEach(t => {
                if (t.getAttribute('data-tab') === tab) {
                    t.classList.add('active');
                } else {
                    t.classList.remove('active');
                }
            });
            
            // Update chart data based on selected tab
            if (analyticsChart) {
                // Destroy existing chart
                analyticsChart.destroy();
                
                // Create new chart with updated data
                const ctx = document.getElementById('analyticsChart').getContext('2d');
                
                // Fetch fresh data
                fetchAnalyticsData()
                    .then(analyticsData => {
                        let chartData;
                        
                        switch(tab) {
                            case 'appointments':
                                chartData = {
                                    labels: analyticsData.months,
                                    datasets: [
                                        {
                                            label: 'Completed',
                                            data: analyticsData.appointments.completed,
                                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                            borderColor: 'rgba(16, 185, 129, 1)',
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Pending',
                                            data: analyticsData.appointments.pending,
                                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                            borderColor: 'rgba(59, 130, 246, 1)',
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Cancelled',
                                            data: analyticsData.appointments.cancelled,
                                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                                            borderColor: 'rgba(239, 68, 68, 1)',
                                            borderWidth: 2
                                        }
                                    ]
                                };
                                break;
                                
                            case 'registrations':
                                chartData = {
                                    labels: analyticsData.months,
                                    datasets: [
                                        {
                                            label: 'Doctors',
                                            data: analyticsData.registrations.doctors,
                                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                            borderColor: 'rgba(16, 185, 129, 1)',
                                            borderWidth: 2
                                        },
                                        {
                                            label: 'Patients',
                                            data: analyticsData.registrations.patients,
                                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                            borderColor: 'rgba(59, 130, 246, 1)',
                                            borderWidth: 2
                                        }
                                    ]
                                };
                                break;
                                
                            case 'revenue':
                                chartData = {
                                    labels: analyticsData.months,
                                    datasets: [
                                        {
                                            label: 'Appointments',
                                            data: analyticsData.revenue,
                                            borderColor: 'rgba(124, 58, 237, 1)',
                                            backgroundColor: 'rgba(124, 58, 237, 0.1)',
                                            borderWidth: 2,
                                            fill: true,
                                            tension: 0.4
                                        }
                                    ]
                                };
                                break;
                        }
                        
                        // Create new chart with updated data
                        analyticsChart = new Chart(ctx, {
                            type: 'line',
                            data: chartData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error updating analytics chart:', error);
                    });
            } else {
                analyticsChart.data = {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Doctors',
                            data: [3, 5, 2, 4, 3, 6],
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Patients',
                            data: [25, 32, 28, 35, 42, 38],
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2
                        }
                    ]
                };
            }
            
            analyticsChart.update();
        }

        // Show doctors modal
        function showDoctorsModal() {
            document.getElementById('doctorsModal').style.display = 'block';
        }

        // Show patients modal
        function showPatientsModal() {
            document.getElementById('patientsModal').style.display = 'block';
        }

        // Show appointments modal
        function showAppointmentsModal() {
            document.getElementById('appointmentsModal').style.display = 'block';
        }

        // Show pending appointments modal
        function showPendingAppointmentsModal() {
            document.getElementById('pendingAppointmentsModal').style.display = 'block';
        }

        // Show/hide dropdown menu
        document.getElementById('userDropdown').addEventListener('click', function() {
            this.classList.toggle('active');
            const dropdownMenu = document.getElementById('userDropdownMenu');
            dropdownMenu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                const dropdownMenus = document.querySelectorAll('.dropdown-menu');
                const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
                
                dropdownMenus.forEach(menu => {
                    menu.classList.remove('show');
                });
                
                dropdownToggles.forEach(toggle => {
                    toggle.classList.remove('active');
                });
            }
            
            // Close modals when clicking outside
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        });

        // User search functionality
        document.getElementById('userSearchBox').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const userCards = document.querySelectorAll('#recentUsersList .user-card');
            
            userCards.forEach(card => {
                const userName = card.querySelector('.user-name').textContent.toLowerCase();
                const userEmail = card.querySelector('.user-email').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Doctor search functionality
        document.getElementById('doctorSearchBox').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const doctorCards = document.querySelectorAll('#doctorsGrid .user-profile-card');
            
            doctorCards.forEach(card => {
                const doctorName = card.querySelector('.user-profile-name').textContent.toLowerCase();
                const doctorDetails = Array.from(card.querySelectorAll('.user-profile-detail')).map(el => el.textContent.toLowerCase());
                
                if (doctorName.includes(searchTerm) || doctorDetails.some(detail => detail.includes(searchTerm))) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Patient search functionality
        document.getElementById('patientSearchBox').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const patientCards = document.querySelectorAll('#patientsGrid .user-profile-card');
            
            patientCards.forEach(card => {
                const patientName = card.querySelector('.user-profile-name').textContent.toLowerCase();
                const patientDetails = Array.from(card.querySelectorAll('.user-profile-detail')).map(el => el.textContent.toLowerCase());
                
                if (patientName.includes(searchTerm) || patientDetails.some(detail => detail.includes(searchTerm))) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Close modal function
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Re-enable scrolling
            }
        }
        
        // Close modal when clicking outside the modal content
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        };
        
        // Close modal with Escape key
        document.onkeydown = function(event) {
            event = event || window.event;
            if (event.key === 'Escape') {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    if (modals[i].style.display === 'block') {
                        closeModal(modals[i].id);
                        break;
                    }
                }
            }
        };
        
        // Show notification modal with full content
        function showNotificationModal(notification) {
            const modal = document.getElementById('notificationModal');
            if (!modal) return;
            
            // Set modal content
            document.getElementById('notificationModalTitle').textContent = 'Notification Details';
            document.getElementById('notificationModalSubtitle').textContent = notification.title;
            document.getElementById('notificationContent').innerHTML = notification.content.replace(/\n/g, '<br>');
            document.getElementById('notificationDate').textContent = notification.date;
            
            // Update badge
            const badge = document.getElementById('notificationBadge');
            badge.className = 'badge ' + notification.badgeClass;
            badge.textContent = notification.badgeText;
            
            // Show modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
        }

        // Show user details
        function showUserDetails(role, userId) {
            if (!userId) {
                console.error('User ID is required');
                return;
            }

            currentUserId = userId;
            currentUserRole = role;

            const modal = document.getElementById('userDetailsModal');
            const modalTitle = document.getElementById('modalUserTitle');
            const userDetailsContent = document.getElementById('userDetailsContent');

            // Set modal title based on role
            modalTitle.textContent = role === 'doctor' ? 'Doctor Details' : 
                                     role === 'patient' ? 'Patient Details' : 
                                     'User Details';

            // Show loading state
            userDetailsContent.innerHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 200px;">
                    <div style="border: 4px solid #f3f3f3; border-top: 4px solid #2563eb; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;"></div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;

            // Show the modal
            modal.style.display = 'block';

            // In a real application, fetch user details from server using AJAX
            // For demo purposes, use setTimeout to simulate server delay
            setTimeout(() => {
                if (role === 'doctor') {
                    fetchDoctorDetails(userId);
                } else if (role === 'patient') {
                    fetchPatientDetails(userId);
                } else {
                    fetchUserDetails(userId);
                }
            }, 500);
        }

        function fetchDoctorDetails(doctorId) {
            // Get the doctor data from the page instead of using mock data
            const doctorsList = <?php echo json_encode($doctors); ?>;
            const doctorDetailsList = <?php echo json_encode($doctor_details); ?>;
            
            // Find the doctor in our list
            const doctor = doctorsList.find(d => d.user_id == doctorId);
            
            if (!doctor) {
                console.error('Doctor not found with ID:', doctorId);
                displayErrorMessage('Doctor information could not be found.');
                return;
            }
            
            // Get additional details if available
            const doctorDetails = doctorDetailsList[doctorId] || {};
            
            // Create doctor object from real data
            const doctorData = {
                id: doctor.user_id,
                firstName: doctor.first_name,
                lastName: doctor.last_name,
                email: doctor.email,
                specialty: doctorDetails.specialty || 'Not specified',
                contact: doctor.phone || doctorDetails.contact_number || 'Not provided',
                availability: doctorDetails.availability_info || 'Not specified',
                yearsExperience: doctorDetails.years_experience || 'Not specified',
                bio: doctorDetails.bio || 'No bio available',
                profilePicture: doctorDetails.profile_picture || doctor.profile_picture || doctor.profile_image || doctor.image || null
            };

            displayDoctorDetails(doctorData);
        }

        function fetchPatientDetails(patientId) {
            // Get the patient data from the page instead of using mock data
            const patientsList = <?php echo json_encode($patients); ?>;
            const patientDetailsList = <?php echo json_encode($patient_details); ?>;
            
            // Find the patient in our list
            const patient = patientsList.find(p => p.user_id == patientId);
            
            if (!patient) {
                console.error('Patient not found with ID:', patientId);
                displayErrorMessage('Patient information could not be found.');
                return;
            }
            
            // Get additional details if available
            const patientDetails = patientDetailsList[patientId] || {};
            
            // Create patient object from real data
            const patientData = {
                id: patient.user_id,
                firstName: patient.first_name,
                lastName: patient.last_name,
                email: patient.email,
                phone: patient.phone || patientDetails.contact_number || 'Not provided',
                dob: patient.date_of_birth || patientDetails.dob || 'Not provided',
                address: patient.address || patientDetails.address || 'Not provided',
                medicalHistory: patientDetails.medical_history || 'No medical history available',
                profilePicture: patientDetails.profile_picture || patientDetails.picture_path || patient.profile_picture || patient.profile_image || patient.image || null
            };

            displayPatientDetails(patientData);
        }
        
        function displayErrorMessage(message) {
            const userDetailsContent = document.getElementById('userDetailsContent');
            userDetailsContent.innerHTML = `
                <div class="alert-error" style="color: #ef4444; padding: 1rem; text-align: center;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>${message}</p>
                </div>
            `;
        }

        function fetchUserDetails(userId) {
            // Get all user data from the page
            const doctors = <?php echo json_encode($doctors); ?>;
            const patients = <?php echo json_encode($patients); ?>;
            const recentUsers = <?php echo json_encode($recent_users); ?>;
            
            // Combine all users to search
            const allUsers = [...doctors, ...patients, ...recentUsers];
            
            // Find the unique user by ID
            const user = allUsers.find(u => u.user_id == userId);
            
            if (!user) {
                console.error('User not found with ID:', userId);
                displayErrorMessage('User information could not be found.');
                return;
            }
            
            // Create user object from real data
            const userData = {
                id: user.user_id,
                firstName: user.first_name,
                lastName: user.last_name,
                email: user.email,
                role: user.role,
                profilePicture: user.profile_picture || null
            };

            displayUserDetails(userData);
        }

        function displayDoctorDetails(doctor) {
            const userDetailsContent = document.getElementById('userDetailsContent');
            
            userDetailsContent.innerHTML = `
                <div class="user-details-container">
                    <div class="user-details-avatar doctor">
                        ${doctor.profilePicture ? 
                            `<img src="${doctor.profilePicture}" alt="${doctor.firstName} ${doctor.lastName}">` : 
                            '<i class="fas fa-user-md"></i>'}
                    </div>
                    <div class="user-details-info">
                        <div class="user-details-name">${doctor.firstName} ${doctor.lastName}</div>
                        <div class="user-details-role role-doctor">Doctor</div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="form-group-value">${doctor.email}</div>
                        </div>
                        <div class="form-group">
                            <label>Specialty</label>
                            <div class="form-group-value">${doctor.specialty}</div>
                        </div>
                        <div class="form-group">
                            <label>Years of Experience</label>
                            <div class="form-group-value">${doctor.yearsExperience}</div>
                        </div>
                        <div class="form-group">
                            <label>Contact</label>
                            <div class="form-group-value">${doctor.contact}</div>
                        </div>
                        <div class="form-group">
                            <label>Availability</label>
                            <div class="form-group-value">${doctor.availability}</div>
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <div class="form-group-value">${doctor.bio}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function displayPatientDetails(patient) {
            const userDetailsContent = document.getElementById('userDetailsContent');
            
            userDetailsContent.innerHTML = `
                <div class="user-details-container">
                    <div class="user-details-avatar patient">
                        ${patient.profilePicture ? 
                            `<img src="${patient.profilePicture}" alt="${patient.firstName} ${patient.lastName}">` : 
                            '<i class="fas fa-user"></i>'}
                    </div>
                    <div class="user-details-info">
                        <div class="user-details-name">${patient.firstName} ${patient.lastName}</div>
                        <div class="user-details-role role-patient">Patient</div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="form-group-value">${patient.email}</div>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <div class="form-group-value">${patient.phone}</div>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <div class="form-group-value">${patient.dob}</div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <div class="form-group-value">${patient.address}</div>
                        </div>
                        <div class="form-group">
                            <label>Medical History</label>
                            <div class="form-group-value">${patient.medicalHistory}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function displayUserDetails(user) {
            const userDetailsContent = document.getElementById('userDetailsContent');
            
            userDetailsContent.innerHTML = `
                <div class="user-details-container">
                    <div class="user-details-avatar admin">
                        ${user.profilePicture ? 
                            `<img src="${user.profilePicture}" alt="${user.firstName} ${user.lastName}">` : 
                            '<i class="fas fa-user-shield"></i>'}
                    </div>
                    <div class="user-details-info">
                        <div class="user-details-name">${user.firstName} ${user.lastName}</div>
                        <div class="user-details-role role-admin">Admin</div>
                        <div class="form-group">
                            <label>Email</label>
                            <div class="form-group-value">${user.email}</div>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <div class="form-group-value">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Confirm delete user
        function confirmDelete() {
            closeModal('userDetailsModal');
            document.getElementById('confirmDeleteModal').style.display = 'block';
        }

        // Delete user
        function deleteUser() {
            if (!currentUserId || !currentUserRole) {
                console.error('Cannot delete: Missing user ID or role');
                // Fallback to alert if Swal is not available
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Missing user information'
                    });
                } else {
                    alert('Error: Missing user information');
                }
                return;
            }

            // Show loading state
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            const modal = document.getElementById('confirmDeleteModal');
            const originalBtnText = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            console.log('Sending delete request for user ID:', currentUserId, 'Role:', currentUserRole);

            // Send AJAX request to delete the user
            fetch('api/delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    user_id: currentUserId,
                    user_role: currentUserRole
                }),
                credentials: 'same-origin'
            })
            .then(async response => {
                console.log('Response status:', response.status);
                const data = await response.json().catch(() => ({
                    success: false,
                    message: 'Invalid JSON response from server'
                }));
                
                console.log('Response data:', data);
                
                if (!response.ok) {
                    const error = new Error(data.message || `HTTP error! Status: ${response.status}`);
                    error.data = data;
                    throw error;
                }
                
                if (!data.success) {
                    const error = new Error(data.message || 'Failed to delete user');
                    error.data = data;
                    throw error;
                }
                
                return data;
            })
            .then(data => {
                console.log('Delete successful:', data);
                
                // Close the modal first
                if (modal) {
                    // Check if Bootstrap is available
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        } else {
                            // Fallback if instance not found
                            modal.style.display = 'none';
                            modal.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            const backdrop = document.querySelector('.modal-backdrop');
                            if (backdrop) {
                                backdrop.remove();
                            }
                        }
                    } else {
                        // Simple hide if Bootstrap not available
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                    }
                }
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    return Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message || 'User deleted successfully',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert('User deleted successfully');
                    return Promise.resolve();
                }
            })
            .then(() => {
                // Reload the page after successful deletion
                window.location.reload();
            })
            .catch(error => {
                console.error('Error deleting user:', error);
                
                // Reset button state
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalBtnText;
                }
                
                // Show error message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: `
                            <div style="text-align: left;">
                                <p>${error.message || 'An error occurred while deleting the user'}</p>
                                ${error.data ? `
                                    <div style="margin-top: 10px; color: #6b7280; font-size: 0.875rem;">
                                        <p>Error details:</p>
                                        <pre style="background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 200px; white-space: pre-wrap;">
                                            ${JSON.stringify(error.data, null, 2)}
                                        </pre>
                                    </div>
                                ` : ''}
                            </div>
                        `
                    });
                } else {
                    alert('Error: ' + (error.message || 'Failed to delete user'));
                }
                
                // Log detailed error to console
                if (error.response) {
                    console.error('Response error:', error.response);
                }
            });
        }

        // Update appointment status
        function updateAppointmentStatus(appointmentId, status) {
            if (!confirm(`Are you sure you want to ${status} this appointment?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            formData.append('status', status);

            fetch('processes/update_appointment_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the pending appointments modal
                    location.reload();
                } else {
                    alert('Failed to update appointment status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the appointment status.');
            });
        }

        // Search appointments
        function searchAppointments(searchTerm, containerId) {
            const container = document.querySelector(`#${containerId} .appointments-list`);
            if (!container) return;

            const cards = container.querySelectorAll('.appointment-card');
            if (!cards.length) return;

            searchTerm = searchTerm.toLowerCase().trim();

            // If search term is empty, show all cards
            if (!searchTerm) {
                cards.forEach(card => {
                    card.style.display = '';
                });
                return;
            }

            // Otherwise, filter cards based on search term
            cards.forEach(card => {
                const textContent = card.textContent.toLowerCase();
                card.style.display = textContent.includes(searchTerm) ? '' : 'none';
            });
        }
        
        // Show all appointment cards in a modal
        function showAllAppointmentCards(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                const cards = modal.querySelectorAll('.appointment-card');
                cards.forEach(card => {
                    card.style.display = '';
                });
                // Also clear any search input
                const searchInput = modal.querySelector('.search-box');
                if (searchInput) {
                    searchInput.value = '';
                }
            }
        }

        // Initialize when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Show all appointment cards immediately for any visible modals
            ['appointmentsModal', 'pendingAppointmentsModal'].forEach(modalId => {
                showAllAppointmentCards(modalId);
                
                // Also show when modals are opened
                const modal = document.getElementById(modalId);
                if (modal) {
                    // For both Bootstrap and custom modals
                    modal.addEventListener('show', () => showAllAppointmentCards(modalId));
                    modal.addEventListener('shown', () => showAllAppointmentCards(modalId));
                    
                    // For older Bootstrap versions
                    if (typeof bootstrap !== 'undefined') {
                        const bsModal = new bootstrap.Modal(modal);
                        modal.addEventListener('shown.bs.modal', () => showAllAppointmentCards(modalId));
                    }
                }
            });
            
            // Also handle the openModal function that might be called from buttons
            if (typeof window.openModal === 'undefined') {
                window.openModal = function(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.style.display = 'block';
                        showAllAppointmentCards(modalId);
                    }
                };
            }
            
            // Update closeModal if needed
            if (typeof window.closeModal === 'undefined') {
                window.closeModal = function(modalId) {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.style.display = 'none';
                    }
                };
            }
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnalyticsChart();
            
            // Add search functionality to modal search boxes
            document.querySelectorAll('.modal-search .search-box').forEach(searchBox => {
                searchBox.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const modal = this.closest('.modal');
                    const modalId = modal.id;
                    
                    if (modalId === 'appointmentsModal' || modalId === 'pendingAppointmentsModal') {
                        searchAppointments(searchTerm, modalId);
                    } else {
                        // Existing search for users
                        const items = modal.querySelectorAll('.user-profile-card, .user-card');
                        items.forEach(item => {
                            const textContent = item.textContent.toLowerCase();
                            item.style.display = textContent.includes(searchTerm) ? '' : 'none';
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>