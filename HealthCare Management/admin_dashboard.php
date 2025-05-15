<?php
session_start();

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

// Get pending appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
$row = $result->fetch_assoc();
$pending_appointments = $row['count'];

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
        }
        
        .analytics-tab.active {
            background-color: #2563eb;
            color: white;
            border-color: #2563eb;
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
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f9fafb;
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
                <div>
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
                            <div class="analytics-tab active" id="appointmentsTab" onclick="switchAnalyticsTab('appointments')">Appointments</div>
                            <div class="analytics-tab" id="registrationsTab" onclick="switchAnalyticsTab('registrations')">Registrations</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="analyticsChart"></canvas>
                    </div>
                </div>

                <!-- System Notifications -->
                <div class="card">
                    <div class="card-header">
                        <div class="title">System Notifications</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($system_logs)): ?>
                            <div class="notification-item">
                                <div class="notification-header">
                                    <div class="notification-title">System Status</div>
                                    <div class="notification-time"><?php echo date('M j, g:i a'); ?></div>
                                </div>
                                <div class="notification-content">All systems operational.</div>
                                <span class="notification-badge badge-success">Active</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($system_logs as $log): ?>
                                <div class="notification-item">
                                    <div class="notification-header">
                                        <div class="notification-title"><?php echo htmlspecialchars($log['action']); ?></div>
                                        <div class="notification-time"><?php echo date('M j, g:i a', strtotime($log['created_at'])); ?></div>
                                    </div>
                                    <div class="notification-content"><?php echo htmlspecialchars($log['description']); ?></div>
                                    <?php
                                        $badge_class = 'badge-info';
                                        if (stripos($log['action'], 'error') !== false) {
                                            $badge_class = 'badge-error';
                                        } elseif (stripos($log['action'], 'warning') !== false) {
                                            $badge_class = 'badge-warning';
                                        } elseif (stripos($log['action'], 'success') !== false) {
                                            $badge_class = 'badge-success';
                                        }
                                    ?>
                                    <span class="notification-badge <?php echo $badge_class; ?>"><?php echo ucfirst(strtolower($log['action'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Sample notifications to show design -->
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title">New Patient Registration</div>
                                <div class="notification-time"><?php echo date('M j, g:i a', strtotime('-1 hour')); ?></div>
                            </div>
                            <div class="notification-content">A new patient has registered on the platform.</div>
                            <span class="notification-badge badge-info">Info</span>
                        </div>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title">System Update</div>
                                <div class="notification-time"><?php echo date('M j, g:i a', strtotime('-3 hours')); ?></div>
                            </div>
                            <div class="notification-content">The system has been updated to version 2.4.0.</div>
                            <span class="notification-badge badge-success">Success</span>
                        </div>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title">Database Warning</div>
                                <div class="notification-time"><?php echo date('M j, g:i a', strtotime('-1 day')); ?></div>
                            </div>
                            <div class="notification-content">Database storage is approaching 80% capacity.</div>
                            <span class="notification-badge badge-warning">Warning</span>
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
                <i class="fas fa-search" style="color: #6b7280;"></i>
                <input type="text" class="search-box" placeholder="Search appointments..." id="appointmentSearchBox" style="border: none; background: transparent; max-width: none; width: 100%;">
            </div>
            <div class="modal-body">
                <p>Appointment list will be shown here...</p>
                <!-- To be populated with appointment data -->
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
                <p>Pending appointment list will be shown here...</p>
                <!-- To be populated with pending appointment data -->
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

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> DocNow. All rights reserved.</p>
    </footer>

    <script>
        // Global variables
        let currentUserId = null;
        let currentUserRole = null;
        let analyticsChart = null;
        let currentAnalyticsTab = 'appointments';

        // Initialize analytics chart
        function initializeAnalyticsChart() {
            const ctx = document.getElementById('analyticsChart').getContext('2d');
            
            // Sample data - would come from PHP/AJAX in a real application
            const appointmentsData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    {
                        label: 'Completed',
                        data: [45, 59, 80, 81, 56, 75],
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Pending',
                        data: [20, 15, 18, 12, 15, 22],
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Cancelled',
                        data: [5, 8, 3, 7, 4, 6],
                        backgroundColor: 'rgba(239, 68, 68, 0.2)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 2
                    }
                ]
            };
            
            const registrationsData = {
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
        }
        
        // Switch analytics tab
        function switchAnalyticsTab(tab) {
            if (tab === currentAnalyticsTab) return;
            
            currentAnalyticsTab = tab;
            
            // Update active tab
            document.getElementById('appointmentsTab').classList.toggle('active', tab === 'appointments');
            document.getElementById('registrationsTab').classList.toggle('active', tab === 'registrations');
            
            // Update chart data
            if (tab === 'appointments') {
                analyticsChart.data = {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Completed',
                            data: [45, 59, 80, 81, 56, 75],
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Pending',
                            data: [20, 15, 18, 12, 15, 22],
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Cancelled',
                            data: [5, 8, 3, 7, 4, 6],
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 2
                        }
                    ]
                };
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
                profilePicture: doctorDetails.profile_picture || null
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
                profilePicture: patientDetails.profile_picture || null
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
                return;
            }

            // This would be an AJAX request to delete the user in a real application
            console.log(`Deleting ${currentUserRole} with ID: ${currentUserId}`);
            
            // For demo, show a success message and close modals
            alert(`User deleted successfully!`);
            closeModal('confirmDeleteModal');
            
            // In a real app, refresh the page or update the user lists
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnalyticsChart();
            
            // Add search functionality to modal search boxes
            document.querySelectorAll('.modal-search .search-box').forEach(searchBox => {
                searchBox.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const modalId = this.closest('.modal').id;
                    const items = document.querySelectorAll(`#${modalId} .user-profile-card, #${modalId} .user-card`);
                    
                    items.forEach(item => {
                        const textContent = item.textContent.toLowerCase();
                        item.style.display = textContent.includes(searchTerm) ? '' : 'none';
                    });
                });
            });
        });
    </script>
</body>
</html>