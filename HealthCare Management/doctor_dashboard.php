<?php
session_start();

// Prevent caching of restricted pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: auth/Login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get doctor info
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT u.*, d.specialty, d.contact_number, d.availability_info, d.profile_picture
    FROM users u
    LEFT JOIN doctors d ON u.user_id = d.doctor_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
$stmt->close();

// Get today's appointments
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = ? AND DATE(appointment_datetime) = ?
");
$stmt->bind_param("is", $doctor_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$today_appointments = $row['count'];
$stmt->close();

// Get completed appointments this week
$week_start = date('Y-m-d', strtotime('this week'));
$week_end = date('Y-m-d', strtotime('this week +6 days'));
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = ? 
    AND status = 'completed'
    AND DATE(appointment_datetime) BETWEEN ? AND ?
");
$stmt->bind_param("iss", $doctor_id, $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$completed_this_week = $row['count'];
$stmt->close();

// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = ? AND a.appointment_datetime >= NOW()
    ORDER BY a.appointment_datetime ASC LIMIT 5
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$upcoming = $stmt->get_result();
$stmt->close();

// Get recent messages
$stmt = $conn->prepare("
    SELECT m.*, u.first_name, u.last_name
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.receiver_id = ? 
    ORDER BY m.created_at DESC LIMIT 3
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

// Get doctor's patients
$stmt = $conn->prepare("
    SELECT DISTINCT u.user_id as patient_id, u.first_name, u.last_name, u.email
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = ?
    ORDER BY u.last_name, u.first_name
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients = $stmt->get_result();
$stmt->close();

// Do not close connection here as we'll need it for notifications and messages later
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Doctor Dashboard</title>
    <script src="assets/js/prevent-back-navigation.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        .hidden {
            display: none;
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

        #notificationIcon {
            position: relative;
            z-index: 10000;
            pointer-events: auto;
            color: white;
            border-radius: 0.375rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
        }

        #notificationIcon:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        #notificationIcon i {
            font-size: 1.25rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
        }

        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .dropdown {
            position: relative;
            display: inline-block;
            z-index: 9999;
            pointer-events: auto;
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 15px;  /* Equal spacing between upper cards and lower cards */
            height: auto;
            min-height: 280px;
        }

        .grid-col-3 {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .grid-col-3 .card {
            flex: 1;
            min-height: 0; /* Allows cards to shrink below content size */
            max-height: 280px; /* Set consistent height for all upper cards */
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
        }

        .card-body {
            padding: 0.75rem;
            overflow-y: auto;
            flex: 1;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
            max-height: 200px; /* Control height of upper cards */
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
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease-in-out;
        }

        .patient-card:hover {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }

        .patient-card .patient-info {
            flex-grow: 1;
        }

        .patient-card .patient-name {
            font-weight: 500;
            color: #1f2937;
            transition: color 0.2s ease;
        }

        .patient-card:hover .patient-name {
            color: #2563eb;
        }

        .patient-card .patient-email {
            color: #6b7280;
            font-size: 0.875rem;
            transition: color 0.2s ease;
        }

        .patient-card .patient-visit {
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            transition: color 0.2s ease;
        }

        .patient-card:hover .patient-email,
        .patient-card:hover .patient-visit {
            color: #4b5563;
        }

        /* Notification card styles */
        .notification-card {
            max-height: 120px; /* Fixed height */
            overflow: hidden; /* Hide overflow */
            position: relative;
            transition: all 0.2s ease;
            margin-bottom: 12px;
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
        }
        
        .notification-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to bottom, rgba(255,255,255,0) 0%, rgba(255,255,255,0.9) 100%);
            pointer-events: none;
        }
        
        .notification-card:hover {
            background-color: #f9fafb;
            border-color: #d1d5db;
        }
        
        /* Modal container */
        .modal {
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }
        
        /* Modal dialog */
        .modal-dialog {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }
        
        /* Modal content */
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            width: 100%;
            overflow: hidden;
        }
        
        /* Modal header */
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(to right, #2563eb, #3b82f6);
            color: white;
            flex-shrink: 0;
        }
        
        /* Modal body */
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
            min-height: 100px;
            max-height: calc(90vh - 150px);
            word-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            overflow-wrap: break-word;
        }
        
        /* Modal footer */
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        
        /* Message content styling */
        .message-content {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.25rem;
            max-height: 300px;
            overflow-y: auto;
            word-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            line-height: 1.6;
        }
        
        /* Ensure all modals have consistent styling */
        #messageDetailModal .modal-content,
        #notificationDetailModal .modal-content,
        #messageModal .modal-content {
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        #messageDetailModal .modal-body,
        #notificationDetailModal .modal-body,
        #messageModal .modal-body {
            flex: 1;
            max-height: calc(90vh - 150px);
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        /* Style for notification content to ensure text is truncated */
        .notification-content {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Enhanced modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            margin: 20px;
            position: relative;
            animation: slideIn 0.3s ease;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, #2563eb, #3b82f6);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
        
        .modal-close {
            cursor: pointer;
            font-size: 22px;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .message-field {
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .message-label {
            font-weight: 600;
            color: #4b5563;
        }
        
        .message-content {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            line-height: 1.6;
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            overflow-x: hidden;
            border: 1px solid #f0f0f0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
        }
        
        .message-sender, .message-subject {
            color: #111827;
            font-weight: 500;
        }
        
        .message-date {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .modal-button {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .modal-button-primary {
            background: linear-gradient(to right, #2563eb, #3b82f6);
            color: white;
            border: none;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        
        .modal-button-primary:hover {
            background: linear-gradient(to right, #1d4ed8, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.25);
        }
        
        /* Enhanced button styles for modal actions */
        .modal-action-btn {
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }
        
        .modal-close-btn {
            background-color: white;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .modal-close-btn:hover {
            background-color: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-1px);
        }
        
        .modal-reply-btn {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border: none;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        
        .modal-reply-btn:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
        }
        
        .modal-reply-btn i {
            margin-right: 0.5rem;
            font-size: 0.85rem;
        }
        
        /* Apply styles to existing modal buttons */
        .modal button {
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-weight: 500;
            border-radius: 8px !important;
            padding: 0.625rem 1.25rem !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.3px;
        }
        
        .modal button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }
        
        .modal button[id="replyMessageBtn"] {
            background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2) !important;
        }
        
        .modal button[id="replyMessageBtn"]:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb) !important;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3) !important;
        }
        
        .modal button:not([id="replyMessageBtn"]) {
            background-color: white !important;
            color: #4b5563 !important;
            border: 1px solid #e5e7eb !important;
        }
        
        .modal button:not([id="replyMessageBtn"]):hover {
            background-color: #f9fafb !important;
            border-color: #d1d5db !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .patient-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .patient-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .message-btn {
            background: transparent;
            border: none;
            color: #2563eb;
            padding: 0.5rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .message-btn:hover {
            background: rgba(37, 99, 235, 0.1);
            transform: scale(1.1);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 15px; /* Match spacing with other sections */
        }

        .action-btn {
            flex: 1;
            padding: 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn.primary {
            background-color: #2563eb;
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            background-color: #1d4ed8;
        }

        .action-btn.secondary {
            background-color: #ffffff;
            color: #2563eb;
            border: 1px solid #2563eb;
        }

        .action-btn.secondary:hover {
            background-color: #f3f4f6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 15px; /* Consistent spacing between sections */
            margin-bottom: 15px; /* Space before buttons */
            padding-bottom: 0;
            position: relative; /* Changed from sticky for better layout */
            background: #f3f4f6;
            z-index: 10;
        }

        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .number {
            color: #1f2937;
            font-size: 2rem;
            font-weight: 600;
        }

        /* Add hover effects for messages and appointments */
        .message-item {
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .message-item:hover {
            background-color: #f3f4f6;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-color: #e5e7eb;
        }

        .appointment-item {
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
        }

        .appointment-item:hover {
            background-color: #ffffff !important;
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
        }

        .appointment-item:hover .far {
            color: #2563eb;
            transition: color 0.2s ease;
        }

        .footer {
            background-color: #1f2937;
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
        }
        
        /* Modern Modal Styles */
        .modal {
            backdrop-filter: blur(4px);
            background-color: rgba(0,0,0,0.6) !important;
        }
        
        .modal-content {
            border-radius: 16px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
            overflow: hidden !important;
        }
        
        .modal-header {
            background: linear-gradient(to right, #2563eb, #3b82f6) !important;
            border-bottom: none !important;
            padding: 16px 20px !important;
        }
        
        .modal-title {
            font-size: 1.1rem !important;
            letter-spacing: 0.5px;
            font-weight: 600 !important;
        }
        
        .modal-close {
            height: 32px;
            width: 32px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.25);
            transform: scale(1.05);
        }
        
        .modal-body {
            background: linear-gradient(to bottom, #f9fafc, #ffffff) !important;
            padding: 20px !important;
        }
        
        .message-label {
            color: #4b5563;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .message-content {
            background-color: white !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            padding: 16px !important;
            box-shadow: inset 0 0 4px rgba(0,0,0,0.05);
            line-height: 1.6 !important;
        }
        
        .message-sender {
            color: #2563eb !important;
            font-weight: 500 !important;
        }
        
        .message-date {
            font-style: italic;
            color: #6b7280 !important;
            font-size: 0.85rem !important;
        }
        
        .message-subject {
            font-weight: 600 !important;
            color: #111827 !important;
        }
        
        .message-field {
            border-bottom: 1px solid #f3f4f6 !important;
            padding-bottom: 12px !important;
            margin-bottom: 12px !important;
        }
        
        .modal-button {
            font-weight: 500 !important;
            border-radius: 8px !important;
            padding: 8px 16px !important;
            transition: all 0.2s !important;
        }
        
        .modal-button-primary {
            background: linear-gradient(to right, #2563eb, #3b82f6) !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2) !important;
        }
        
        .modal-button-primary:hover {
            background: linear-gradient(to right, #1d4ed8, #2563eb) !important;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                height: auto;
                gap: 1.5rem;
            }

            .card {
                height: 400px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                position: static;
            }
        }

        /* Add Modal Styles */
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
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .btn-save {
            background-color: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            width: 100%;
        }

        .btn-save:hover {
            background-color: #1d4ed8;
      }

    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <img src="assets/images/logo.jpg" alt="DocNow Logo">
            <span>DocNow</span>
        </div>
        <div class="nav-links">
            <div class="dropdown">
                <div class="dropdown-toggle" id="userDropdown">
                    <span><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <a href="#" class="dropdown-item" onclick="openEditProfile(event)">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
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

    <!-- Messages Panel -->
    <div id="messagesPanel" class="fixed right-4 top-16 bg-white rounded-lg shadow-lg border border-gray-200 w-96 z-50 hidden" style="display: none;">
        <div class="p-3 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
            <h3 class="font-semibold">Messages & Notifications</h3>
            <button onclick="toggleMessagesPanel()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-3 py-2 border-b border-gray-200 bg-gray-50">
            <div class="flex space-x-2">
                <button id="messageFilterBtn" onclick="filterMessages('message')" class="text-sm py-1 px-3 rounded bg-blue-600 text-white filter-btn" data-filter="message">Message</button>
                <button id="notificationFilterBtn" onclick="filterMessages('notification')" class="text-sm py-1 px-3 rounded bg-gray-200 hover:bg-gray-300 text-gray-700 filter-btn" data-filter="notification">Notification</button>
            </div>
        </div>
        <div id="messagesList" class="overflow-y-auto max-h-96 p-2">
            <p class="text-gray-500 text-center py-4">Loading messages...</p>
        </div>
    </div>

    <!-- Message/Notification Modal -->
    <div id="messageModal" class="fixed inset-0 flex items-center justify-center z-50 hidden" style="background-color: rgba(0,0,0,0.5);">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
                <h3 id="modalTitle" class="font-semibold">Message Details</h3>
                <button onclick="closeMessageModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                        <span id="modalSender" class="font-medium"></span>
                    </div>
                    <div id="modalSubjectContainer" class="mb-2 hidden">
                        <span class="text-gray-600 text-sm">Subject: </span>
                        <span id="modalSubject" class="text-sm font-medium"></span>
                    </div>
                    <div class="border-t border-gray-100 pt-2">
                        <p id="modalContent" class="text-gray-700 whitespace-pre-wrap break-words overflow-hidden overflow-wrap-anywhere" style="max-width: 100%; word-break: break-word;"></p>
                    </div>
                </div>
                <div id="replyContainer" class="mt-4 hidden">
                    <hr class="my-3">
                    <h4 class="text-sm font-medium mb-2">Reply</h4>
                    <textarea id="replyText" class="w-full border border-gray-300 rounded p-2 text-sm" rows="3" placeholder="Type your reply here..."></textarea>
                    <div class="flex justify-end mt-2">
                        <button id="sendReplyBtn" class="bg-blue-600 text-white text-sm px-4 py-1 rounded hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-1"></i> Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div id="composeModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
        <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; max-height: 80vh; overflow-y: auto; margin: 0 20px; position: relative;">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
                <h3 class="font-semibold">Compose Message</h3>
                <span class="close">&times;</span>
            </div>
            <div class="p-4">
                <form id="composeForm" method="post" action="processes/send_message.php">
                    <div class="mb-3">
                        <label for="recipientSelect" class="block text-sm font-medium text-gray-700 mb-1">To:</label>
                        <select id="recipientSelect" name="recipient_id" class="w-full border border-gray-300 rounded p-2 text-sm" required>
                            <option value="">Select Recipient</option>
                            <!-- This will be populated with JavaScript -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="messageSubject" class="block text-sm font-medium text-gray-700 mb-1">Subject:</label>
                        <input type="text" id="messageSubject" name="subject" class="w-full border border-gray-300 rounded p-2 text-sm" required>
                    </div>
                    <div class="mb-3">
                        <label for="messageContent" class="block text-sm font-medium text-gray-700 mb-1">Message:</label>
                        <textarea id="messageContent" name="content" class="w-full border border-gray-300 rounded p-2 text-sm" rows="4" required></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-1"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="container">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; border: 2px solid #2563eb;">
                        <?php
                        // Check if doctor has profile picture
                        $profile_pic = !empty($doctor['profile_picture']) && file_exists($doctor['profile_picture']) 
                            ? $doctor['profile_picture'] 
                            : "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/person.svg";
                        ?>
                        <img src="<?php echo $profile_pic; ?>" alt="Profile" class="profile-picture" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div>
                        <h1 style="font-size: 1.5rem; color: #1f2937; margin-bottom: 0.25rem;">Welcome back, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h1>
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            <p style="color: #6b7280;"><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Practitioner'); ?></p>
                            <p style="color: #6b7280;">|</p>
                            <p style="color: #6b7280;"><?php echo htmlspecialchars($doctor['email']); ?></p>
                        </div>
                    </div>
                </div>
                <div style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; display: flex; align-items: center; gap: 0.375rem;">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    <span>Available</span>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="grid-col-1">
                    <div class="card">
                        <div class="card-header">My Patients</div>
                        <div class="card-body">
                            <?php if ($patients->num_rows > 0): ?>
                                <?php while ($row = $patients->fetch_assoc()): ?>
                                    <div class="patient-card" onclick="showPatientDetails(<?php echo $row['patient_id']; ?>)">
                                        <div class="patient-card-content">
                                            <div class="patient-avatar">
                                                <?php
                                                // Get patient profile picture
                                                $conn = new mysqli("localhost", "root", "", "docnow_db");
                                                $stmt = $conn->prepare("SELECT picture_path FROM patients WHERE patient_id = ?");
                                                $stmt->bind_param("i", $row['patient_id']);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $patient_data = $result->fetch_assoc();
                                                $stmt->close();
                                                $conn->close();

                                                $profile_pic = !empty($patient_data['picture_path']) && file_exists($patient_data['picture_path']) 
                                                    ? $patient_data['picture_path'] 
                                                    : "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/person.svg";
                                                ?>
                                                <img src="<?php echo $profile_pic; ?>" alt="Profile" class="patient-image">
                                            </div>
                                            <div class="patient-info">
                                                <div class="patient-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                                <div class="patient-email"><?php echo htmlspecialchars($row['email']); ?></div>
                                            </div>
                                            <button class="message-btn" onclick="event.stopPropagation(); openMessageDialog(<?php echo $row['patient_id']; ?>)">
                                                <i class="fas fa-comment-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p>No patients found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid-col-2">
                    <div class="card">
                        <div class="card-header">Upcoming Appointments</div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if ($upcoming->num_rows > 0): ?>
                                <?php while($row = $upcoming->fetch_assoc()): ?>
                                    <div class="appointment-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            <div style="background: <?php echo getStatusColor($row['status']); ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem;">
                                                <?php echo ucfirst($row['status']); ?>
                                            </div>
                                        </div>
                                        <div style="color: #6b7280; font-size: 0.875rem;">
                                            <i class="far fa-calendar"></i> <?php echo date('M d, Y - h:i A', strtotime($row['appointment_datetime'])); ?>
                                        </div>
                                        <div style="color: #6b7280; font-size: 0.875rem;">
                                            <i class="far fa-clipboard"></i> <?php echo htmlspecialchars($row['appointment_type']); ?>
                                        </div>
                                        <?php if (!empty($row['reason'])): ?>
                                        <div style="color: #6b7280; font-size: 0.875rem;">
                                            <i class="far fa-comment"></i> <?php echo htmlspecialchars($row['reason']); ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($row['status'] !== 'completed' && $row['status'] !== 'cancelled'): ?>
                                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.75rem;">
                                            <form action="processes/update_appointment_status.php" method="post" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" style="background-color: #10b981; color: white; padding: 0.25rem 0.5rem; border: none; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer;">
                                                    <i class="fas fa-check"></i> Complete
                                                </button>
                                            </form>

                                            <form action="processes/update_appointment_status.php" method="post" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $row['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" style="background-color: #ef4444; color: white; padding: 0.25rem 0.5rem; border: none; border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer;">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p>No upcoming appointments.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid-col-3">
                    <!-- Notifications Card -->
                    <div class="card">
                        <div class="card-header">
                            <div class="flex justify-between items-center">
                                <span>Notifications</span>
                                <button type="button" class="btn-refresh" onclick="loadNotifications()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="notificationsList" class="messages-list" style="height: 100%; max-height: 190px; overflow-y: auto; padding: 0.5rem;">
                                <?php
                                // Create a new database connection for notifications
                                $notif_conn = new mysqli("localhost", "root", "", "docnow_db");
                                if ($notif_conn->connect_error) {
                                    die("Connection failed: " . $notif_conn->connect_error);
                                }

                                // Get recent notifications for this doctor
                                $stmt = $notif_conn->prepare("
                                    SELECT n.*, un.is_read, u.first_name, u.last_name, u.role
                                    FROM notifications n
                                    JOIN user_notifications un ON n.notification_id = un.notification_id
                                    LEFT JOIN users u ON n.sender_id = u.user_id
                                    WHERE un.user_id = ?
                                    ORDER BY n.created_at DESC
                                    LIMIT 5
                                ");

                                if ($stmt) {
                                    $stmt->bind_param("i", $doctor_id);
                                    $stmt->execute();
                                    $notifications_result = $stmt->get_result();

                                    if ($notifications_result->num_rows > 0) {
                                        while ($notification = $notifications_result->fetch_assoc()) {
                                            $is_read_class = $notification['is_read'] ? '' : 'border-l-4 border-blue-500 bg-blue-50';
                                            $time_ago = date('M d, g:i A', strtotime($notification['created_at']));

                                            echo '<div class="notification-card p-3 rounded-lg mb-4 cursor-pointer ' . $is_read_class . '" style="background-color: white; border: 1px solid #e5e7eb; transition: all 0.2s ease-in-out;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 6px rgba(0, 0, 0, 0.1)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'none\'"
                                                  data-notification-id="' . $notification['notification_id'] . '"
                                                  data-content="' . htmlspecialchars($notification['content'], ENT_QUOTES) . '"
                                                  data-title="' . htmlspecialchars($notification['title'], ENT_QUOTES) . '">';
                                            echo '<div class="flex justify-between items-start">';

                                            // Message content area
                                            echo '<div class="w-full">';

                                            // Sender and date info row
                                            $created_at = new DateTime($notification['created_at']);
                                            echo '<div class="grid grid-cols-3 gap-2 text-xs text-gray-500 mb-2 border-b pb-2">';
                                            echo '<div><span class="font-medium">From:</span> ' . 
                                                 ($notification['is_system'] ? 'System' : 
                                                 (isset($notification['first_name']) ? htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) : 'System')) . '</div>';
                                            echo '<div><span class="font-medium">Date:</span> ' . $created_at->format('M d, Y') . '</div>';
                                            echo '<div><span class="font-medium">Time:</span> ' . $created_at->format('g:i A') . '</div>';
                                            echo '</div>'; // End of info row

                                            // Title row
                                            echo '<div class="font-medium text-gray-900 mb-1">' . htmlspecialchars($notification['title']) . '</div>';

                                            // Show sender info
                                            if ($notification['sender_id'] && !empty($notification['first_name'])) {
                                                $sender = 'From: ' . htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']);
                                                if ($notification['role'] == 'admin') {
                                                    $sender .= ' (Admin)';
                                                }
                                                echo '<div class="text-xs text-gray-500 mt-1">' . $sender . '</div>';
                                            } else {
                                                echo '<div class="text-xs text-gray-500 mt-1">From: Admin User (Admin)</div>';
                                            }

                                            // Message content with better spacing
                                            echo '<div class="text-sm text-gray-600 mt-2 leading-relaxed">' . htmlspecialchars($notification['content']) . '</div>';

                                            echo '</div>'; // End of content div

                                            // Remove message icon as requested
                                            echo '</div>'; // End of flex container

                                            // Add click event to notification cards
                                            $notificationCard = 'notification-card-' . $notification['notification_id'];
                                            echo '<script>
                                                var card = document.currentScript.parentNode;
                                                card.id = "' . $notificationCard . '";
                                                card.onclick = function() {
                                                    // Mark as read
                                                    markAsRead("notification", "' . $notification['notification_id'] . '");

                                                    // Show toast notification
                                                    showToast({
                                                        title: "' . htmlspecialchars($notification['title'], ENT_QUOTES) . '",
                                                        message: "' . htmlspecialchars($notification['content'], ENT_QUOTES) . '",
                                                        type: "info",
                                                        duration: 5000
                                                    });

                                                    // Update UI to show read state
                                                    this.classList.remove("border-l-4", "border-blue-500", "bg-blue-50");
                                                };
                                            </script>';

                                            echo '</div>'; // End of notification card
                                        }
                                    } else {
                                        echo '<p class="text-center py-4">No notifications</p>';
                                    }
                                    $stmt->close();
                                    $notif_conn->close(); // Close this specific connection
                                } else {
                                    echo '<p class="text-center py-4">Error loading notifications</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Card -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <div class="flex justify-between items-center">
                                <span>Messages</span>
                                <button type="button" class="btn-refresh" onclick="loadMessages()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="messagesList" class="messages-list" style="height: 100%; max-height: 190px; overflow-y: auto; padding: 0.5rem;">
                                <?php
                                // Create a new database connection for messages
                                $msg_conn = new mysqli("localhost", "root", "", "docnow_db");
                                if ($msg_conn->connect_error) {
                                    die("Connection failed: " . $msg_conn->connect_error);
                                }

                                // Get both sent and received messages with proper sender and receiver info
                                $stmt = $msg_conn->prepare("
                                    SELECT 
                                        m.*,
                                        CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type,
                                        sender.first_name as sender_first_name,
                                        sender.last_name as sender_last_name,
                                        receiver.first_name as receiver_first_name,
                                        receiver.last_name as receiver_last_name
                                    FROM messages m
                                    JOIN users sender ON m.sender_id = sender.user_id
                                    JOIN users receiver ON m.receiver_id = receiver.user_id
                                    WHERE m.sender_id = ? OR m.receiver_id = ?
                                    ORDER BY m.created_at DESC
                                    LIMIT 10
                                ");

                                if ($stmt) {
                                    $stmt->bind_param("iii", $doctor_id, $doctor_id, $doctor_id);
                                    $stmt->execute();
                                    $messages_result = $stmt->get_result();

                                    if ($messages_result->num_rows > 0) {
                                        while ($message = $messages_result->fetch_assoc()) {
                                            $is_sent = ($message['message_type'] === 'sent');
                                            $is_read = isset($message['is_read']) ? $message['is_read'] == 1 : true;
                                            $message_class = $is_sent ? 'bg-blue-100' : ($is_read ? '' : 'bg-blue-50');
                                            $message_style = $is_sent ? 'margin-left: auto;' : '';
                                            $time_ago = date('M d, g:i A', strtotime($message['created_at']));

                                            echo '<div class="message-item p-2 rounded-lg mb-2 cursor-pointer transition-all duration-200 hover:shadow ' . $message_class . '" style="background-color: white; border: 1px solid #e5e7eb; ' . $message_style . '; width: 100%; height: 80px; overflow: hidden;" onmouseover="this.style.transform=\'translateY(-1px)\';" onmouseout="this.style.transform=\'translateY(0)\'">';
                                            echo '<div class="flex justify-between items-start h-full">';

                                            // Message content area
                                            echo '<div class="w-full overflow-hidden">';

                                            // Sender and date info row
                                            $created_at = new DateTime($message['created_at']);
                                            echo '<div class="flex justify-between items-center text-xs text-gray-500 h-full">';

                                            // Sender info
                                            echo '<div class="truncate"><span class="font-medium">' . ($is_sent ? 'To:' : 'From:') . '</span> ';
                                            if ($is_sent) {
                                                echo htmlspecialchars($message['receiver_first_name'] . ' ' . $message['receiver_last_name']);
                                            } else {
                                                echo htmlspecialchars($message['sender_first_name'] . ' ' . $message['sender_last_name']);
                                            }
                                            echo '</div>';
                                            
                                            // Date and time
                                            echo '<div class="text-right whitespace-nowrap pl-2">' . $created_at->format('M d, g:i A') . '</div>';
                                            echo '</div>';

                                            // Message content is now hidden, only showing sender and time

                                            // No reply button needed as requested

                                            // Add clickable functionality to the message card
                                            $messageCard = 'message-card-' . $message['message_id'];
                                            echo '<script>
                                                var card = document.currentScript.parentNode;
                                                card.id = "' . $messageCard . '";
                                                card.setAttribute("data-message-id", "' . $message['message_id'] . '");
                                                card.setAttribute("data-content", "' . htmlspecialchars($message['content'], ENT_QUOTES) . '");
                                                card.setAttribute("data-subject", "' . htmlspecialchars(!empty($message['subject']) ? $message['subject'] : "", ENT_QUOTES) . '");
                                                card.onclick = function(e) {
                                                    // Don\'t trigger if clicking on message icon button
                                                    if (e.target.closest(".message-icon")) return;

                                                    // Mark as read if its a received message
                                                    if (' . ($is_sent ? 'false' : 'true') . ') {
                                                        markAsRead("message", "' . $message['message_id'] . '");
                                                        this.classList.remove("bg-blue-50");
                                                    }

                                                    // Show message detail modal
                                                    showMessageDetail({
                                                        id: "' . $message['message_id'] . '",
                                                        type: "message",
                                                        content: `' . str_replace(["\n", "\r"], ['\\n', ''], addslashes($message['content'])) . '`,
                                                        subject: `' . addslashes(!empty($message['subject']) ? $message['subject'] : "No Subject") . '`,
                                                        sender: `' . ($is_sent ? 'You' : htmlspecialchars(trim($message['sender_first_name'] . ' ' . $message['sender_last_name']), ENT_QUOTES)) . '`,
                                                        sender_id: "' . ($is_sent ? $doctor_id : $message['sender_id']) . '",
                                                        receiver: `' . ($is_sent ? htmlspecialchars(trim($message['receiver_first_name'] . ' ' . $message['receiver_last_name']), ENT_QUOTES) : 'You') . '`,
                                                        is_sent: ' . ($is_sent ? 'true' : 'false') . ',
                                                        formatted_date: "' . $time_ago . '",
                                                        timestamp: "' . $message['created_at'] . '"
                                                    });
                                                };
                                            </script>';


                                            // Close content div 
                                            echo '</div>';

                                            // Add message icon to the right side like in patient card
                                            echo '<div class="flex items-center">';
                                            echo '<button class="message-icon text-blue-600" onclick="event.stopPropagation(); openMessageDialog(' . ($is_sent ? $message['receiver_id'] : $message['sender_id']) . ');">';
                                            echo '<i class="fas fa-comment-dots"></i>';
                                            echo '</button>';
                                            echo '</div>';

                                            echo '</div>'; // End of flex container
                                            echo '</div>'; // End of message card
                                        }
                                    } else {
                                        echo '<p class="text-center py-4">No messages</p>';
                                    }
                                    $stmt->close();
                                    $msg_conn->close(); // Close this specific connection
                                } else {
                                    echo '<p class="text-center py-4">Error loading messages</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Today's Appointments</h3>
                    <div class="number"><?php echo $today_appointments; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed This Week</h3>
                    <div class="number"><?php echo $completed_this_week; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Patients</h3>
                    <div class="number"><?php echo $patients->num_rows; ?></div>
                </div>
            </div>

            <div class="action-buttons">
                <button class="action-btn primary" id="newAppointmentBtn">
                    <i class="fas fa-calendar-plus"></i>
                    New Appointment
                </button>
                <button class="action-btn secondary" id="composeMessageBtn">
                    <i class="fas fa-envelope"></i>
                    Compose Message
                </button>
            </div>
        </div>
    </main>


    <!-- New Appointment Modal -->
    <div id="newAppointmentModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
        <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 0 20px; position: relative; padding: 20px;">
            <span class="close">&times;</span>
            <h2>Schedule New Appointment</h2>
            <form id="newAppointmentForm" action="processes/create_appointment.php" method="post">
                <div class="form-group">
                    <label for="patient">Select Patient</label>
                    <select id="patient" name="patient_id" required>
                        <option value="">-- Select Patient --</option>
                        <?php 
                        // Connect to the database again to get fresh data for the select dropdown
                        $conn = new mysqli("localhost", "root", "", "docnow_db");
                        $stmt = $conn->prepare("
                            SELECT DISTINCT u.user_id as patient_id, u.first_name, u.last_name
                            FROM users u
                            WHERE u.role = 'patient'
                            ORDER BY u.last_name, u.first_name
                        ");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $row['patient_id']; ?>">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </option>
                        <?php endwhile; 
                        $stmt->close();
                        $conn->close();
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="appointment_date">Date</label>
                    <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="appointment_time">Time</label>
                    <input type="time" id="appointment_time" name="appointment_time" required>
                </div>
                <div class="form-group">
                    <label for="appointment_type">Appointment Type</label>
                    <select id="appointment_type" name="appointment_type" required>
                        <option value="General Checkup">General Checkup</option>
                        <option value="Follow-up">Follow-up</option>
                        <option value="Consultation">Consultation</option>
                        <option value="Treatment">Treatment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reason">Reason for Visit</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="Enter reason for appointment"></textarea>
                </div>
                <button type="submit" class="btn-save">Schedule Appointment</button>
            </form>
        </div>
    </div>

    <script>
    // Handle compose message form submission
    document.addEventListener('DOMContentLoaded', function() {
        const composeForm = document.getElementById('composeMessageForm');
        if (composeForm) {
            composeForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Show loading state
                const submitBtn = composeForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';

                // Get form data
                const formData = new FormData(composeForm);

                // Send AJAX request
                fetch('processes/send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert('Message sent successfully!');
                        // Close the modal
                        document.getElementById('composeMessageModal').style.display = 'none';
                        // Reset the form
                        composeForm.reset();
                        // Reload messages if needed
                        if (typeof loadMessages === 'function') {
                            loadMessages();
                        }
                    } else {
                        throw new Error(data.message || 'Failed to send message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });
        }
    });
    </script>

    <!-- Compose Message Modal 2 -->
    <div id="composeMessageModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
        <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; margin: 0 20px; position: relative; padding: 20px;">
            <span class="close">&times;</span>
            <h2>Compose Message</h2>
            <form id="composeMessageForm" action="processes/send_message.php" method="post">
                <div class="form-group">
                    <label for="recipient">Select Recipient</label>
                    <select id="recipient" name="recipient_id" required>
                        <option value="">-- Select Recipient --</option>
                        <?php 
                        // Connect to the database again to get fresh data for the select dropdown
                        $conn = new mysqli("localhost", "root", "", "docnow_db");
                        $stmt = $conn->prepare("
                            SELECT DISTINCT u.user_id as patient_id, u.first_name, u.last_name
                            FROM users u
                            WHERE u.role = 'patient'
                            ORDER BY u.last_name, u.first_name
                        ");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $row['patient_id']; ?>">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </option>
                        <?php endwhile; 
                        $stmt->close();
                        $conn->close();
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn-save">Send Message</button>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
        <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 0 20px; position: relative; padding: 20px;">
            <span class="close">&times;</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" action="processes/update_doctor_profile.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                    <div style="margin-top: 15px;" id="profile_picture_preview">
                        <?php if(!empty($doctor['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($doctor['profile_picture']); ?>" alt="Current profile picture" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #4e73df;">
                            <p style="font-size: 12px; color: #6b7280;">Current profile picture</p>
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; background-color: #f0f2f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px dashed #ccc;">
                                <i class="fas fa-user-md" style="font-size: 40px; color: #4e73df;"></i>
                            </div>
                            <p style="font-size: 12px; color: #6b7280;">No profile picture uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="specialty">Specialty</label>
                    <input type="text" id="specialty" name="specialty" value="<?php echo htmlspecialchars($doctor['specialty'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($doctor['contact_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="experience">Years of Experience</label>
                    <input type="text" id="experience" name="experience" value="<?php echo htmlspecialchars($doctor['experience'] ?? ''); ?>" placeholder="e.g. 5 years in cardiology">
                </div>
                <div class="form-group">
                    <label for="availability_info">Availability Information</label>
                    <textarea id="availability_info" name="availability_info" rows="3"><?php echo htmlspecialchars($doctor['availability_info'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Add JavaScript for form submission and image preview -->
    <script>
        // Preview uploaded image
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const previewContainer = document.getElementById('profile_picture_preview');
                    previewContainer.innerHTML = `
                        <img src="${event.target.result}" alt="Profile picture preview" 
                             style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #4e73df;">
                        <p style="font-size: 12px; color: #6b7280;">New profile picture preview</p>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle form submission with AJAX
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const form = this;

            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Saving...';
            submitButton.disabled = true;

            // Clear any existing messages
            const existingMessages = form.querySelector('.alert-message');
            if (existingMessages) {
                existingMessages.remove();
            }

            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Reset button state
                submitButton.textContent = originalText;
                submitButton.disabled = false;

                if (data.success) {
                    // Show success message
                    const successMessage = document.createElement('div');
                    successMessage.className = 'alert-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4';
                    successMessage.role = 'alert';
                    successMessage.innerHTML = `
                        <strong class="font-bold">Success!</strong>
                        <span class="block sm:inline"> Your profile has been updated successfully.</span>
                    `;

                    form.insertBefore(successMessage, form.firstChild);

                    // Update the doctor's information in the dashboard
                    updateDoctorProfileUI(data, formData);

                    // Close the modal after a short delay to show the success message
                    setTimeout(() => {
                        closeModal('editProfileModal');
                        // Clear the form and messages when modal is closed
                        setTimeout(() => {
                            const message = form.querySelector('.alert-message');
                            if (message) message.remove();
                        }, 300);
                    }, 1500);

                } else {
                    throw new Error(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitButton.textContent = originalText;
                submitButton.disabled = false;

                // Show error message
                const errorMessage = document.createElement('div');
                errorMessage.className = 'alert-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                errorMessage.role = 'alert';
                errorMessage.innerHTML = `
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"> ${error.message || 'Failed to update profile. Please try again.'}</span>
                `;

                form.insertBefore(errorMessage, form.firstChild);

                // Scroll to error message
                errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            // Function to update the UI with new profile data
            function updateDoctorProfileUI(data, formData) {
                // Update profile picture if it was changed
                const profilePicElements = document.querySelectorAll('.profile-picture, #profile_picture_preview img');
                if (data.profile_picture) {
                    profilePicElements.forEach(element => {
                        element.src = data.profile_picture + '?t=' + new Date().getTime();
                        // Ensure the image is visible
                        element.style.display = 'block';
                    });
                }

                // Update other profile information
                const specialty = formData.get('specialty');
                const contactNumber = formData.get('contact_number');
                const experience = formData.get('experience');
                const availabilityInfo = formData.get('availability_info');

                // Update the doctor's info in the header
                const doctorInfo = document.querySelector('.dashboard-header');
                if (doctorInfo) {
                    const doctorName = doctorInfo.querySelector('h1');
                    if (doctorName && doctorName.nextElementSibling) {
                        const doctorTitle = doctorName.nextElementSibling;
                        doctorTitle.textContent = specialty || 'Doctor';
                    }

                    // Update the contact information
                    const contactInfo = doctorInfo.querySelector('.text-gray-600');
                    if (contactInfo) {
                        const contactItems = contactInfo.querySelectorAll('p');
                        if (contactItems.length > 1) {
                            // Update contact number
                            contactItems[0].innerHTML = contactNumber ? 
                                `<i class="fas fa-phone-alt mr-2"></i> ${contactNumber}` : 
                                '<span class="text-gray-400">No contact number</span>';

                            // Update experience
                            contactItems[1].innerHTML = experience ? 
                                `<i class="fas fa-briefcase mr-2"></i> ${experience}` : 
                                '<span class="text-gray-400">Experience not specified</span>';
                        }
                    }
                }

                // Show a toast notification
                showNotification('Profile updated successfully!', 'success');
            }
        });
    </script>

    <!-- Add notification system -->
    <script>
    // Notification function for showing toast messages
    function showNotification(message, type = 'success') {
        // Create notification container if it doesn't exist
        let notificationContainer = document.getElementById('notification-container');
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.id = 'notification-container';
            notificationContainer.style.position = 'fixed';
            notificationContainer.style.top = '20px';
            notificationContainer.style.right = '20px';
            notificationContainer.style.zIndex = '9999';
            document.body.appendChild(notificationContainer);

            // Add styles for notifications
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes fadeOut {
                    from { opacity: 1; transform: translateX(0); }
                    to { opacity: 0; transform: translateX(100%); }
                }
                .notification {
                    padding: 12px 20px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    color: white;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    animation: slideIn 0.3s ease-out;
                }
                .notification i {
                    font-size: 1.2em;
                }
            `;
            document.head.appendChild(style);
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'notification';

        // Set background color based on type
        if (type === 'success') {
            notification.style.backgroundColor = '#10B981'; // Green
        } else if (type === 'error') {
            notification.style.backgroundColor = '#EF4444'; // Red
        } else {
            notification.style.backgroundColor = '#3B82F6'; // Blue
        }

        // Add icon based on type
        let icon = '';
        if (type === 'success') {
            icon = '<i class="fas fa-check-circle"></i>';
        } else if (type === 'error') {
            icon = '<i class="fas fa-exclamation-circle"></i>';
        } else {
            icon = '<i class="fas fa-info-circle"></i>';
        }

        notification.innerHTML = `
            ${icon}
            <span>${message}</span>
        `;

        // Add to container
        notificationContainer.appendChild(notification);


        // Remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
    </script>

    <!-- Patient Details Modal -->
    <div id="patientDetailsModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
        <div class="modal-content" style="width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin: 0 20px; position: relative; padding: 20px;">
            <span class="close">&times;</span>
            <h2>Patient Profile</h2>
            <div id="patientProfileContent" style="margin-top: 20px;">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; <?php echo date('Y'); ?> DocNow. All rights reserved.
    </footer>

    <script>
        // Helper function to get color based on appointment status
        <?php
        function getStatusColor($status) {
            switch($status) {
                case 'confirmed':
                    return '#10b981'; // green
                case 'pending':
                    return '#eab308'; // yellow
                case 'cancelled':
                    return '#ef4444'; // red
                case 'completed':
                    return '#3b82f6'; // blue
                case 'rescheduled':
                    return '#8b5cf6'; // purple
                default:
                    return '#6b7280'; // gray
            }
        }
        ?>

        // Dropdown toggle
        document.getElementById('userDropdown').addEventListener('click', function() {
            document.getElementById('userDropdownMenu').classList.toggle('show');
            this.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-toggle') && !event.target.matches('.dropdown-toggle *')) {
                var dropdowns = document.getElementsByClassName('dropdown-menu');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                        document.querySelector('.dropdown-toggle').classList.remove('active');
                    }
                }
            }
        });

        // Modal functionality
        const modalOpeners = {
            'newAppointmentBtn': 'newAppointmentModal',
            'composeMessageBtn': 'composeMessageModal',
            'editProfileBtn': 'editProfileModal'
        };

        // Add click handlers for all modal openers
        Object.entries(modalOpeners).forEach(([btnId, modalId]) => {
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.addEventListener('click', function(e) {
                    if (e && e.preventDefault) e.preventDefault();
                    openModal(modalId);
                });
            }
        });

        // Function to open a modal with smooth transition
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.opacity = '1';
                }, 10);
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
            }
        }

        // Function to close a modal with smooth transition
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto'; // Re-enable scrolling
                }, 200);
            }
        }

        // Close modals when clicking the close button
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    closeModal(modal.id);
                }
            });
        });

        // Close modals when clicking outside the content
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.style.display === 'flex') {
                        closeModal(modal.id);
                    }
                });
            }
        });

        // Function to open edit profile modal
        function openEditProfile(e) {
            if (e) e.preventDefault();
            openModal('editProfileModal');
        }

        // Function to pre-select recipient in compose message modal
        function openMessageDialog(patientId) {
            var selectElement = document.getElementById('recipient');
            if (selectElement) {
                for (var i = 0; i < selectElement.options.length; i++) {
                    if (selectElement.options[i].value == patientId) {
                        selectElement.options[i].selected = true;
                        break;
                    }
                }
            }
            openModal('composeMessageModal');
        }

        // Function to load notifications and messages
        function loadNotifications() {
            // Get the notifications container
            const container = document.getElementById('messagesNotificationsList');
            if (!container) return;

            // Show loading message
            container.innerHTML = '<p class="text-center py-4">Loading messages and notifications...</p>';

            // Get current active filter
            const activeFilter = document.querySelector('.filter-btn.active')?.dataset.filter || 'all';

            // Fetch notifications from database
            fetch('api/fetch_all_notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Clear loading message
                    container.innerHTML = '';

                    if (!data.success) {
                        container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load notifications. Please try again.</p>' +
                                              '<div class="text-center"><button onclick="loadNotifications()" class="btn-retry"><i class="fas fa-sync-alt"></i> Retry</button></div>' +
                                              '<p class="text-center text-xs mt-2 text-gray-500">Error details: ' + (data.error_details || 'Unknown error') + '</p>';
                        return;
                    }

                    const items = data.data;
                    const counts = data.counts;

                    // Update counts
                    updateFilterCounts(counts);

                    // If no notifications
                    if (items.length === 0) {
                        container.innerHTML = '<p class="text-center py-4">No messages or notifications</p>';
                        return;
                    }

                    // Filter items based on active filter
                    let filteredItems = items;
                    if (activeFilter === 'messages') {
                        filteredItems = items.filter(item => item.type === 'message');
                    } else if (activeFilter === 'notifications') {
                        filteredItems = items.filter(item => item.type === 'notification');
                    } else if (activeFilter === 'unread') {
                        filteredItems = items.filter(item => !item.is_read);
                    }

                    if (filteredItems.length === 0) {
                        container.innerHTML = `<p class="text-center py-4">No ${activeFilter === 'all' ? 'messages or notifications' : activeFilter} to display</p>`;
                        return;
                    }

                    // Add each item to the container
                    filteredItems.forEach(item => {
                        // Create notification/message item
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'message-item patient-card';
                        itemDiv.dataset.id = item.id;
                        itemDiv.dataset.type = item.type;

                        // Add unread class if not read
                        if (!item.is_read) {
                            itemDiv.classList.add('unread');
                        }

                        // Add data attributes for filtering
                        itemDiv.setAttribute('data-type', item.type);
                        itemDiv.setAttribute('data-read', item.is_read ? 'read' : 'unread');
                        itemDiv.setAttribute('data-id', item.id);

                        // Select icon based on type
                        let icon = 'fa-bell';
                        let iconClass = 'text-gray-600';

                        if (item.type === 'message') {
                            icon = 'fa-envelope';
                            iconClass = 'text-blue-600';
                        } else if (item.subtype === 'appointment') {
                            icon = 'fa-calendar-check';
                            iconClass = 'text-green-600';
                        } else if (item.subtype === 'system') {
                            icon = 'fa-cog';
                            iconClass = 'text-purple-600';
                        }

                        // Set notification content
                        itemDiv.innerHTML = `
                            <div class="message-content">
                                <div class="message-icon">
                                    <i class="fas ${icon} ${iconClass}"></i>
                                </div>
                                <div class="message-info">
                                    <div class="message-header">
                                        <h4 class="message-title">${item.title}</h4>
                                        <span class="message-date">${item.formatted_date}</span>
                                    </div>
                                    <div class="message-sender">
                                        <strong>From: </strong>${item.type === 'message' ? (item.is_sent ? 'You' : (item.sender || 'Unknown Sender')) : 'System'}
                                    </div>
                                    <p class="message-text">${item.content ? (item.content.length > 100 ? item.content.substring(0, 100) + '...' : item.content) : 'No content available'}</p>
                                </div>
                                ${!item.is_read ? '<div class="unread-indicator"></div>' : ''}
                                <div class="message-type-badge" style="position: absolute; top: 10px; right: 10px; font-size: 10px; background-color: ${item.type === 'message' ? '#3b82f6' : '#4b5563'}; color: white; padding: 2px 6px; border-radius: 4px;">
                                    ${item.type === 'message' ? 'MESSAGE' : 'NOTIFICATION'}
                                </div>
                            </div>
                        `;

                        // COMPLETELY SEPARATE click handlers for messages vs notifications
                        if (item.type === 'message') {
                            // MESSAGE HANDLER - this handler is ONLY for messages
                            itemDiv.addEventListener('click', function() {
                                console.log("Clicked a MESSAGE item");

                                // Mark as read
                                markAsRead('message', item.id);

                                // Handle displaying the message ONLY in message modal
                                // USING THE NOTIFICATION MODAL FOR MESSAGES
                                const messageModal = document.getElementById('notificationModal');

                                // If message modal not found, exit
                                if (!messageModal) {
                                    console.error("Notification modal not found");
                                    return;
                                }

                                // Display the modal
                                messageModal.style.display = 'flex';

                                // Set message title
                                const messageTitle = document.getElementById('modalTitle');
                                if (messageTitle) {
                                    messageTitle.innerHTML = '<span style="background-color: #3b82f6; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">MESSAGE</span> ' + 
                                    (item.title || 'Message');
                                }

                                // Set message sender
                                const messageSender = document.getElementById('messageModalSender');
                                if (messageSender) {
                                    messageSender.textContent = (item.is_sent === 'true' || item.is_sent === true) ? 'You' : (item.sender || 'Unknown');
                                }

                                // Set message receiver if it's a sent message
                                const messageReceiverContainer = document.getElementById('messageModalReceiverContainer');
                                const messageReceiver = document.getElementById('messageModalReceiver');

                                if (messageReceiverContainer && messageReceiver) {
                                    if (item.is_sent === 'true' || item.is_sent === true) {
                                        messageReceiver.textContent = item.receiver || 'Recipient';
                                        messageReceiverContainer.style.display = 'block';
                                    } else {
                                        messageReceiverContainer.style.display = 'none';
                                    }
                                }

                                // Set message date
                                const messageDate = document.getElementById('messageModalDate');
                                if (messageDate) {
                                    messageDate.textContent = item.formatted_date || '';
                                }

                                // Set message content
                                const messageBody = document.getElementById('messageModalBody');
                                if (messageBody) {
                                    messageBody.textContent = item.content || '';

                                    // Format newlines
                                    if (messageBody.textContent) {
                                        messageBody.innerHTML = messageBody.textContent.replace(/\n/g, '<br>');
                                    }
                                }

                                // Show message modal
                                messageModal.style.display = 'flex';
                                setTimeout(() => {
                                    messageModal.style.opacity = '1';
                                }, 10);
                            });
                        } else {
                            // NOTIFICATION HANDLER - this handler is ONLY for notifications
                            itemDiv.addEventListener('click', function() {
                                console.log("Clicked a NOTIFICATION item");

                                // Mark as read
                                markAsRead('notification', item.id);

                                // Handle displaying the notification ONLY in notification modal
                                const notificationModal = document.getElementById('notificationModal');

                                // If notification modal not found, exit
                                if (!notificationModal) {
                                    console.error("Notification modal not found");
                                    return;
                                }

                                // Set notification title
                                const notificationTitle = document.getElementById('notificationModalTitle');
                                if (notificationTitle) {
                                    notificationTitle.innerHTML = '<span style="background-color: #4b5563; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">NOTIFICATION</span> ' + 
                                    (item.title || 'System Alert');
                                }

                                // Set notification sender - always System
                                const notificationSender = document.getElementById('notificationModalSender');
                                if (notificationSender) {
                                    notificationSender.textContent = 'System';
                                }

                                // Set notification date
                                const notificationDate = document.getElementById('notificationModalDate');
                                if (notificationDate) {
                                    notificationDate.textContent = item.formatted_date || '';
                                }

                                // Set notification content
                                const notificationBody = document.getElementById('notificationModalBody');
                                if (notificationBody) {
                                    notificationBody.textContent = item.content || '';

                                    // Format newlines
                                    if (notificationBody.textContent) {
                                        notificationBody.innerHTML = notificationBody.textContent.replace(/\n/g, '<br>');
                                    }
                                }

                                // Show notification modal
                                notificationModal.style.display = 'flex';
                                setTimeout(() => {
                                    notificationModal.style.opacity = '1';
                                }, 10);
                            });
                        }

                        // Add to container
                        container.appendChild(itemDiv);


                    });

                    // Set up search functionality
                    const searchInput = document.getElementById('messageSearch');
                    if (searchInput) {
                        searchInput.addEventListener('input', filterMessages);
                    }

                    // Set up filter buttons
                    const filterButtons = document.querySelectorAll('.filter-btn');
                    filterButtons.forEach(button => {
                        button.addEventListener('click', (e) => {
                            // Remove active class from all buttons
                            filterButtons.forEach(btn => btn.classList.remove('active'));
                            // Add active class to clicked button
                            e.target.classList.add('active');
                            // Filter messages
                            filterMessages();
                        });
                    });
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                    container.innerHTML = '<p class="text-center py-4">Failed to load notifications. Please try again.</p>';
                    // Add a retry button
                    const retryBtn = document.createElement('button');
                    retryBtn.className = 'btn btn-primary mt-3';
                    retryBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Retry';
                    retryBtn.onclick = loadNotifications;
                    container.appendChild(retryBtn);
                });
        }

        // Function to filter messages based on search and filter buttons
        function filterMessages() {
            const search = document.getElementById('messageSearch').value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
            const items = document.querySelectorAll('.message-item');

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const type = item.getAttribute('data-type');
                const readStatus = item.getAttribute('data-read');

                // Check if item matches search
                const matchesSearch = search === '' || text.includes(search);

                // Check if item matches filter
                let matchesFilter = false;
                if (activeFilter === 'all') {
                    matchesFilter = true;
                } else if (activeFilter === 'messages' && type === 'message') {
                    matchesFilter = true;
                } else if (activeFilter === 'notifications' && type !== 'message') {
                    matchesFilter = true;
                } else if (activeFilter === 'unread' && readStatus === 'unread') {
                    matchesFilter = true;
                }

                // Show or hide item
                if (matchesSearch && matchesFilter) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Function to show message modal
        function showMessageModal(notification) {
            const modalId = notification.type === 'message' ? 'messageModal' : 'notificationModal';
            const modal = document.getElementById(modalId);
            const contentDiv = document.getElementById(notification.type === 'message' ? 'messageContent' : 'notificationContent');

            // Set up modal content
            let modalHtml = `
                <h3 class="text-xl font-semibold mb-2">${notification.title}</h3>
                <div class="text-sm text-gray-500 mb-1">From: ${notification.sender}</div>
                <div class="text-sm text-gray-500 mb-4">Date: ${notification.date}</div>
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-gray-700">${notification.content}</p>
                </div>
            `;

            contentDiv.innerHTML = modalHtml;

            // Show reply section for messages
            const replySection = document.getElementById('messageReplySection');
            if (replySection) {
                replySection.style.display = notification.type === 'message' ? 'block' : 'none';

                // Set recipient ID for reply
                if (notification.type === 'message') {
                    const msgId = notification.id.replace('m_', '');
                    document.getElementById('replyToMessageId').value = msgId;
                }
            }

            // Show modal
            modal.style.display = 'block';

            // Mark as read
            if (!notification.is_read) {
                // Make API call to mark as read
                if (notification.type === 'message') {
                    const msgId = notification.id.replace('m_', '');
                    fetch('processes/mark_message_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `message_id=${msgId}`
                    });
                } else {
                    fetch('processes/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `notification_id=${notification.id}`
                    });
                }

                // Update UI
                document.querySelector(`[data-id="${notification.id}"]`).classList.remove('unread');
                document.querySelector(`[data-id="${notification.id}"]`).setAttribute('data-read', 'read');
            }
        }

        // Function to show patient details in modal
        function showPatientDetails(patientId) {
            // Get the modal and content elements
            var modal = document.getElementById('patientDetailsModal');
            var contentDiv = document.getElementById('patientProfileContent');

            // Show loading state
            contentDiv.innerHTML = '<div style="text-align: center; padding: 20px;">Loading patient details...</div>';
            openModal('patientDetailsModal');

            // Fetch patient data with AJAX from the existing API
            fetch('api/get_patient_details.php?patient_id=' + patientId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Build the patient profile HTML using the API data
                        var patientData = data.data;
                        var html = `
                            <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; margin-bottom: 25px;">
                                    <div style="width: 80px; height: 80px; overflow: hidden; border-radius: 50%; margin-right: 15px;">
                                        <img src="${patientData.picture_path}" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <h3 style="margin: 0; font-size: 20px;">${patientData.name}</h3>
                                        <p style="margin: 5px 0; color: #6b7280;">${patientData.email}</p>
                                        <p style="margin: 5px 0; color: #6b7280;">
                                            Patient<br>
                                            ID: ${patientData.user_id}
                                        </p>
                                    </div>
                                </div>

                                <div style="display: flex; flex-wrap: wrap; margin-bottom: 25px;">
                                    <div style="flex: 1; min-width: 150px; margin-bottom: 15px;">
                                        <strong>Age:</strong> ${patientData.age || 'Not specified'}
                                    </div>
                                    <div style="flex: 1; min-width: 150px; margin-bottom: 15px;">
                                        <strong>Gender:</strong> ${patientData.gender || 'Not specified'}
                                    </div>
                                    <div style="flex: 1; min-width: 150px; margin-bottom: 15px;">
                                        <strong>Marital Status:</strong> ${patientData.marital_status || 'Not specified'}
                                    </div>
                                </div>`;

                        if (patientData.bio) {
                            html += `
                                <div style="margin-bottom: 20px;">
                                    <strong>Bio:</strong> ${patientData.bio}
                                </div>`;
                        }

                        if (patientData.address) {
                            html += `
                                <div style="margin-bottom: 20px;">
                                    <strong>Address:</strong> ${patientData.address}
                                </div>`;
                        }

                        html += `
                            <h3 style="margin: 25px 0 15px 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px;">Medical Information</h3>

                            <div style="margin-bottom: 15px;">
                                <strong>Blood Type:</strong> ${patientData.blood_type || 'Not specified'}
                            </div>

                            <div style="margin-bottom: 15px;">
                                <strong>Allergies:</strong> ${patientData.allergies || 'None'}
                            </div>

                            <div style="margin-bottom: 15px;">
                                <strong>Current Medications:</strong> ${patientData.current_medications || 'None'}
                            </div>

                            <div style="margin-bottom: 25px;">
                                <strong>Medical History:</strong> ${patientData.medical_history || 'None'}
                            </div>

                            <h3 style="margin: 25px 0 15px 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px;">Emergency Contact</h3>

                            <div style="margin-bottom: 10px;">
                                <strong>Name:</strong> ${patientData.emergency_contact.name || 'Not provided'}
                            </div>

                            <div style="margin-bottom: 10px;">
                                <strong>Phone Number:</strong> ${patientData.emergency_contact.phone || 'Not provided'}
                            </div>

                            <div style="margin-bottom: 10px;">
                                <strong>Relationship:</strong> ${patientData.emergency_contact.relationship || 'Not specified'}
                            </div>

                            <div style="margin-top: 25px; background-color: #eef2ff; padding: 15px; border-radius: 8px;">
                                <h3 style="margin: 0 0 10px 0; color: #4f46e5;">Appointment History</h3>
                                <div style="display: flex; gap: 20px;">
                                    <div style="flex: 1; text-align: center;">
                                        <div style="font-size: 24px; font-weight: bold; color: #4f46e5;">${patientData.stats.total_appointments}</div>
                                        <div>Total Appointments</div>
                                    </div>
                                    <div style="flex: 1; text-align: center;">
                                        <div style="font-size: 24px; font-weight: bold; color: #10b981;">${patientData.stats.completed_appointments}</div>
                                        <div>Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>`;

                        contentDiv.innerHTML = html;
                    } else {
                        contentDiv.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    contentDiv.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error loading patient data. Please try again.</div>';
                    console.error('Error fetching patient details:', error);
                });
        }

        // Function to show message details
        function showMessageDetail(item) {
            // Get message elements
            const modal = document.getElementById('messageDetailModal');
            const title = document.getElementById('messageDetailTitle');
            const sender = document.getElementById('messageDetailSender');
            const receiver = document.getElementById('messageDetailReceiver');
            const date = document.getElementById('messageDetailDate');
            const subject = document.getElementById('messageDetailSubject');
            const content = document.getElementById('messageDetailContent');
            const replyBtn = document.getElementById('replyMessageBtn');

            // Set message details
            title.textContent = item.subject || 'Message';
            sender.textContent = item.sender;
            receiver.textContent = item.receiver;
            date.textContent = item.formatted_date || new Date(item.timestamp).toLocaleString();
            subject.textContent = item.subject || 'No Subject';
            content.textContent = item.content;

            // Set up reply button
            replyBtn.onclick = function() {
                modal.style.display = 'none';
                if (item.sender_id) {
                    openMessageDialog(item.sender_id, item.subject?.startsWith('Re:') ? item.subject : 'Re: ' + (item.subject || 'Message'));
                }
            };

            // Show the modal
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);
        }

        // Function to show message details (old)
        function showMessageDetailOld(item) {
            // Keep this for backward compatibility
            const modal = document.getElementById('messageModal');
            const title = document.getElementById('messageModalTitle');
            const sender = document.getElementById('messageModalSender');
            const receiver = document.getElementById('messageModalReceiver');
            const receiverContainer = document.getElementById('messageModalReceiverContainer');
            const subject = document.getElementById('messageModalSubject');
            const subjectContainer = document.getElementById('messageModalSubjectContainer');
            const date = document.getElementById('messageModalDate');
            const body = document.getElementById('messageModalBody');
            const replyToInput = document.getElementById('replyToMessageId');

            // Add MESSAGE badge to title
            title.innerHTML = `<span style="background-color: #3b82f6; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">MESSAGE</span> ${item.title || 'Message'}`;

            // Set proper sender name based on if message was sent by current user
            if (item.is_sent === 'true' || item.is_sent === true) {
                sender.textContent = 'You';

                // Show recipient information
                if (receiver && receiverContainer) {
                    receiver.textContent = item.receiver || 'Recipient';
                    receiverContainer.style.display = 'block';
                }
            } else {
                sender.textContent = item.sender || 'Unknown Sender';

                // Hide recipient for received messages
                if (receiverContainer) {
                    receiverContainer.style.display = 'none';
                }
            }

            // Set date
            date.textContent = item.formatted_date || (item.date || '');

            // Set subject if available
            if (subject && subjectContainer) {
                if (item.subject || item.title) {
                    subject.textContent = item.subject || item.title;
                    subjectContainer.style.display = 'block';
                } else {
                    subjectContainer.style.display = 'none';
                }
            }

            // Set message content with proper formatting
            body.textContent = item.content || item.message || '';
            if (body.textContent) {
                body.innerHTML = body.textContent.replace(/\n/g, '<br>');
            }
        }
    </script>

  <!-- Message Detail Modal -->
  <div id="messageDetailModal" class="modal">
    <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 700px; max-height: 80vh; overflow-y: auto; margin: 20px; position: relative;">
      <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
        <h3 id="messageDetailTitle" class="font-semibold">Message Details</h3>
        <span class="close" style="cursor: pointer; font-size: 24px; font-weight: bold;">&times;</span>
      </div>
      <div class="p-6">
        <div class="space-y-4">
          <div class="flex justify-between border-b pb-2">
            <div class="w-1/2">
              <span class="font-semibold">From: </span>
              <span id="messageDetailSender"></span>
            </div>
            <div class="w-1/2 text-right">
              <span class="text-gray-500" id="messageDetailDate"></span>
            </div>
          </div>
          <div class="border-b pb-2">
            <span class="font-semibold">To: </span>
            <span id="messageDetailReceiver"></span>
          </div>
          <div class="border-b pb-2">
            <span class="font-semibold">Subject: </span>
            <span id="messageDetailSubject"></span>
          </div>
          <div class="pt-4">
            <div class="font-semibold mb-2">Message:</div>
            <div id="messageDetailContent" class="bg-white p-4 rounded-lg border border-gray-200" style="white-space: normal; word-wrap: break-word; word-break: break-all; overflow-wrap: break-word; max-width: 100%; overflow-x: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); line-height: 1.6;"></div>
          </div>
          <div class="mt-6 flex justify-end space-x-3">
            <button onclick="document.getElementById('messageDetailModal').style.display='none'" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Notification Detail Modal -->
  <div id="notificationDetailModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; transition: opacity 0.3s ease;">
    <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 700px; max-height: 80vh; overflow-y: auto; margin: 20px; position: relative;">
      <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
        <h3 id="notificationDetailTitle" class="font-semibold">Notification Details</h3>
        <span class="close" style="cursor: pointer; font-size: 24px; font-weight: bold;" onclick="document.getElementById('notificationDetailModal').style.display='none'">&times;</span>
      </div>
      <div class="p-6">
        <div class="space-y-4">
          <div class="flex justify-between border-b pb-2">
            <div class="w-1/2">
              <span class="font-semibold">From: </span>
              <span id="notificationDetailSender"></span>
            </div>
            <div class="w-1/2 text-right">
              <span class="text-gray-500" id="notificationDetailDate"></span>
            </div>
          </div>
          <div class="border-b pb-2">
            <span class="font-semibold">Subject: </span>
            <span id="notificationDetailSubject"></span>
          </div>
          <div class="pt-4">
            <div class="font-semibold mb-2">Message:</div>
            <div id="notificationDetailContent" class="message-content"></div>
          </div>
          <div class="mt-6 flex justify-end">
            <button onclick="document.getElementById('notificationDetailModal').style.display='none'" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
  // Function to show notification details
  function showNotificationDetail(notification) {
    const modal = document.getElementById('notificationDetailModal');
    if (!modal) return;

    // Set notification details
    document.getElementById('notificationDetailTitle').textContent = notification.subject || 'Notification';
    document.getElementById('notificationDetailSender').textContent = notification.sender || 'System';
    document.getElementById('notificationDetailDate').textContent = notification.formatted_date || new Date().toLocaleString();
    document.getElementById('notificationDetailSubject').textContent = notification.subject || 'No Subject';
    document.getElementById('notificationDetailContent').textContent = notification.content || 'No content';

    // Set up view related button
    const viewRelatedBtn = document.getElementById('viewRelatedBtn');
    if (viewRelatedBtn) {
      viewRelatedBtn.onclick = function() {
        // Add your logic to view related content (e.g., appointment, patient record)
        if (notification.related_id && notification.related_type) {
          // Example: Redirect or show related content based on notification type
          console.log('View related:', notification.related_type, notification.related_id);
        }
      };
    }

    // Show the modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      modal.style.opacity = '1';
    }, 10);

    // Mark as read
    markAsRead('notification', notification.id);
  }

  // Function to show message details
  function showMessageDetail(item) {
    const modal = document.getElementById('messageDetailModal');
    if (!modal) return;

    // Set message details
    document.getElementById('messageDetailTitle').textContent = item.subject || 'Message';
    document.getElementById('messageDetailSender').textContent = item.sender || 'Unknown Sender';
    document.getElementById('messageDetailReceiver').textContent = item.receiver || 'Recipient';
    document.getElementById('messageDetailDate').textContent = item.formatted_date || new Date().toLocaleString();
    document.getElementById('messageDetailSubject').textContent = item.subject || 'No Subject';
    document.getElementById('messageDetailContent').textContent = item.content || 'No content';

    // Set up reply button
    const replyBtn = document.getElementById('replyMessageBtn');
    if (replyBtn) {
      replyBtn.onclick = function() {
        modal.style.display = 'none';
        if (item.sender_id) {
          openMessageDialog(item.sender_id, item.subject?.startsWith('Re:') ? item.subject : 'Re: ' + (item.subject || 'Message'));
        }
      };
    }

    // Show the modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      modal.style.opacity = '1';
    }, 10);
  }

  // Add click handler for all interactive elements
  document.addEventListener('click', function(e) {
    // Check if clicked element or its parent has notification-item class
    let notificationItem = e.target.closest('.notification-item, [data-notification-id]');

    if (notificationItem) {
      e.preventDefault();

      // Get notification data from data attributes or create a sample notification
      const notificationData = {
        id: notificationItem.getAttribute('data-notification-id') || '1',
        type: 'notification',
        sender: notificationItem.getAttribute('data-sender') || 'System',
        subject: notificationItem.getAttribute('data-subject') || 'Notification',
        content: notificationItem.getAttribute('data-content') || 'No content',
        formatted_date: notificationItem.getAttribute('data-date') || new Date().toLocaleString(),
        related_id: notificationItem.getAttribute('data-related-id'),
        related_type: notificationItem.getAttribute('data-related-type')
      };

      showNotificationDetail(notificationData);

      // Prevent default link behavior if it's a link
      if (e.target.tagName === 'A' || e.target.closest('a')) {
        e.preventDefault();
      }

      return;
    }

    // Handle notification modal close
    const notificationModal = document.getElementById('notificationDetailModal');
    if ((e.target === notificationModal || e.target.closest('.close')) && notificationModal) {
      notificationModal.style.opacity = '0';
      setTimeout(() => {
        notificationModal.style.display = 'none';
        document.body.style.overflow = 'auto';
      }, 200);
      return;
    }
    // Check if clicked element or its parent has message-item class
    let messageItem = e.target.closest('.message-item, [data-message-id]');

    if (messageItem) {
      e.preventDefault();

      // Get message data from data attributes or create a sample message
      const messageData = {
        id: messageItem.getAttribute('data-message-id') || '1',
        type: 'message',
        sender: messageItem.getAttribute('data-sender') || 'Sender',
        receiver: messageItem.getAttribute('data-receiver') || 'You',
        subject: messageItem.getAttribute('data-subject') || 'No Subject',
        content: messageItem.getAttribute('data-content') || 'No content',
        formatted_date: messageItem.getAttribute('data-date') || new Date().toLocaleString(),
        sender_id: messageItem.getAttribute('data-sender-id') || ''
      };

      showMessageDetail(messageData);

      // Mark as read if it's a message
      markAsRead('message', messageData.id);
    }

    // Close modal when clicking the close button or outside the modal
    if (e.target.classList.contains('close') || e.target === document.getElementById('messageDetailModal')) {
      const modal = document.getElementById('messageDetailModal');
      if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
          modal.style.display = 'none';
          document.body.style.overflow = 'auto';
        }, 200);
      }
    }
  });

  // Function to open message dialog (if not defined)
  if (typeof openMessageDialog !== 'function') {
    window.openMessageDialog = function(userId, subject) {
      const composeModal = document.getElementById('composeMessageModal');
      const messageDetailModal = document.getElementById('messageDetailModal');
      
      // Set recipient and subject in the compose form
      const recipientInput = document.getElementById('composeRecipient');
      const subjectInput = document.getElementById('composeSubject');
      const messageInput = document.getElementById('composeMessage');
      
      if (recipientInput && subjectInput && messageInput) {
        // Get the sender's name from the message detail modal
        const senderName = document.getElementById('messageDetailSender')?.textContent || '';
        recipientInput.value = senderName;
        
        // Set the subject, adding 'Re: ' if it's not already there
        const replyPrefix = 'Re: ';
        const currentSubject = subject || '';
        subjectInput.value = currentSubject.startsWith(replyPrefix) ? currentSubject : (replyPrefix + currentSubject);
        
        // Focus on the message input
        setTimeout(() => {
          messageInput.focus();
        }, 100);
      }
      
      // Close the message detail modal and open the compose modal
      if (messageDetailModal) {
        messageDetailModal.style.display = 'none';
      }
      
      if (composeModal) {
        composeModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    };
  }

  // Function to mark as read (if not defined)
  if (typeof markAsRead !== 'function') {
    window.markAsRead = function(type, id) {
      console.log('Marking as read:', type, id);
      // Add your mark as read logic here
    };
  }
  </script>
  <div id="messageDetailModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
    <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 700px; max-height: 80vh; overflow-y: auto; margin: 0 20px; position: relative;">
      <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
        <h3 class="font-semibold">Message Details</h3>
        <span class="close" style="cursor: pointer; font-size: 24px;" onclick="document.getElementById('messageDetailModal').style.display='none'">&times;</span>
      </div>
      <div class="p-6">
        <div class="space-y-4">
          <div class="flex justify-between border-b pb-2">
            <div class="w-1/2">
              <span class="font-semibold">From: </span>
              <span id="messageDetailSender"></span>
            </div>
            <div class="w-1/2 text-right">
              <span class="text-gray-500" id="messageDetailDate"></span>
            </div>
            <div>
              <div class="font-medium text-gray-500">Subject:</div>
              <div id="messageDetailSubject" class="font-medium"></div>
            </div>
          </div>

          <div class="mt-6">
            <div class="font-medium text-gray-500 mb-2">Message:</div>
            <div id="messageDetailContent" class="message-content"></div>
          </div>

          <div class="mt-6 flex justify-end space-x-3">
            <button onclick="document.getElementById('messageDetailModal').style.display='none'" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Message Modal (Compose) -->
  <div id="composeMessageModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 5%;">
    <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); position: relative; width: 100%; margin: 0 20px;">
      <span class="close" id="messageModalClose">&times;</span>
      <h2 id="messageModalTitle">Message</h2>
      <div id="messageModalContent" style="margin-top: 20px;">
        <div class="form-group">
          <label><strong>From:</strong></label>
          <p id="messageModalSender"></p>
        </div>
        <div class="form-group" id="messageModalReceiverContainer">
          <label><strong>To:</strong></label>
          <p id="messageModalReceiver"></p>
        </div>
        <div class="form-group">
          <label><strong>Date:</strong></label>
          <p id="messageModalDate"></p>
        </div>
        <div class="form-group" id="messageModalSubjectContainer">
          <label><strong>Subject:</strong></label>
          <p id="messageModalSubject"></p>
        </div>
        <div class="form-group">
          <label><strong>Message:</strong></label>
          <div id="messageModalBody" style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 8px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; white-space: normal; max-width: 100%; overflow-x: hidden; hyphens: auto;"></div>
        </div>
        <div class="form-group" style="margin-top: 20px; text-align: right;">
          <input type="hidden" id="replyToMessageId">
          <textarea id="messageReply" class="form-control" rows="4" placeholder="Type your reply..." style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 10px;"></textarea>
          <button class="btn-primary" onclick="sendReply()" style="background-color: #3b82f6; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;">Send Reply</button>
        </div>
      </div>
    </div>
  </div>



  <script>
  // Initialize notifications when page loads
  // Function to filter messages and notifications
  function filterMessages(filterType) {
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => {
      if (btn.dataset.filter === filterType) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    // Reload notifications with the new filter
    loadNotifications();
  }

  // Function to update filter counts
  function updateFilterCounts(counts) {
    // If the counts object doesn't exist, do nothing
    if (!counts) return;

    // Update filter button text with counts if needed
    document.querySelectorAll('.filter-btn').forEach(btn => {
      const filterType = btn.dataset.filter;
      let count = 0;

      if (filterType === 'all') {
        count = counts.total || 0;
      } else if (filterType === 'messages') {
        count = counts.messages || 0;
      } else if (filterType === 'notifications') {
        count = counts.notifications || 0;
      } else if (filterType === 'unread') {
        count = counts.unread || 0;
      }

      // Add count to button text if greater than 0
      if (count > 0) {
        let countSpan = btn.querySelector('.count-badge');
        if (!countSpan) {
          countSpan = document.createElement('span');
          countSpan.className = 'count-badge';
          btn.appendChild(countSpan);
        }
        countSpan.textContent = count;
      } else {
        // Remove count if 0
        const countSpan = btn.querySelector('.count-badge');
        if (countSpan) {
          btn.removeChild(countSpan);
        }
      }
    });
  }

  // Function to mark notification or message as read
  function markAsRead(type, id) {
    // Create form data
    const formData = new FormData();
    formData.append('type', type);
    formData.append('id', id);

    // Send request to mark as read
    fetch('api/mark_as_read.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      // Refresh notifications list
      loadNotifications();
    })
    .catch(error => {
      console.error('Error marking item as read:', error);
    });
  }

  // Function to show message details
  function showMessageDetail(item) {
    // Get message elements
    const modal = document.getElementById('messageDetailModal');
    const title = document.getElementById('messageDetailTitle') || {};
    const sender = document.getElementById('messageDetailSender') || {};
    const receiver = document.getElementById('messageDetailReceiver') || {};
    const date = document.getElementById('messageDetailDate') || {};
    const subject = document.getElementById('messageDetailSubject') || {};
    const content = document.getElementById('messageDetailContent') || {};
    const replyBtn = document.getElementById('replyMessageBtn');

    // Set message details
    if (title.textContent !== undefined) title.textContent = item.subject || 'Message';
    if (sender.textContent !== undefined) sender.textContent = item.sender || 'Unknown Sender';
    if (receiver.textContent !== undefined) receiver.textContent = item.receiver || 'Recipient';
    if (date.textContent !== undefined) date.textContent = item.formatted_date || new Date(item.timestamp || new Date()).toLocaleString();
    if (subject.textContent !== undefined) subject.textContent = item.subject || 'No Subject';
    if (content.textContent !== undefined) content.textContent = item.content || 'No content';

    // Show the modal
    if (modal) {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      setTimeout(() => {
        modal.style.opacity = '1';
      }, 10);
    }
  }

  // Add click handler for message items
  document.addEventListener('click', function(e) {
    const messageItem = e.target.closest('.message-item, .notification-item, [data-message-id]');
    if (messageItem) {
      e.preventDefault();
      let itemData;
      
      // Try to get data from attributes or data-item
      if (messageItem.getAttribute('data-item')) {
        itemData = JSON.parse(messageItem.getAttribute('data-item') || '{}');
      } else {
        // Create itemData from element's data attributes
        itemData = {
          id: messageItem.getAttribute('data-message-id') || messageItem.getAttribute('data-id'),
          type: messageItem.getAttribute('data-type') || 'message',
          content: messageItem.getAttribute('data-content') || '',
          subject: messageItem.getAttribute('data-subject') || 'No Subject',
          sender: messageItem.getAttribute('data-sender') || 'Unknown Sender',
          timestamp: messageItem.getAttribute('data-timestamp') || new Date().toISOString(),
          formatted_date: messageItem.getAttribute('data-formatted-date') || new Date().toLocaleString()
        };
      }
      
      if (itemData) {
        showMessageDetail(itemData);

        // Mark as read if it's a message
        if (itemData.id && itemData.type === 'message') {
          markAsRead('message', itemData.id);
        }
      }
    }

    // Close modal when clicking the close button or outside the modal
    if (e.target.classList.contains('close') || e.target === document.getElementById('messageDetailModal')) {
      const modal = document.getElementById('messageDetailModal');
      if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
          modal.style.display = 'none';
          document.body.style.overflow = 'auto';
        }, 200);
      }
    }
  });

  function showMessageDetailOld(item) {
    // Keep this for backward compatibility
    const modal = document.getElementById('messageModal');
    const title = document.getElementById('messageModalTitle');
    const sender = document.getElementById('messageModalSender');
    const receiver = document.getElementById('messageModalReceiver');
    const receiverContainer = document.getElementById('messageModalReceiverContainer');
    const subject = document.getElementById('messageModalSubject');
    const subjectContainer = document.getElementById('messageModalSubjectContainer');
    const date = document.getElementById('messageModalDate');
    const body = document.getElementById('messageModalBody');
    const replyToInput = document.getElementById('replyToMessageId');

    // Add MESSAGE badge to title
    title.innerHTML = `<span style="background-color: #3b82f6; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">MESSAGE</span> ${item.title || 'Message'}`;

    // Set proper sender name based on if message was sent by current user
    if (item.is_sent === 'true' || item.is_sent === true) {
      sender.textContent = 'You';

      // Show recipient information
      if (receiver && receiverContainer) {
        receiver.textContent = item.receiver || 'Recipient';
        receiverContainer.style.display = 'block';
      }
    } else {
      sender.textContent = item.sender || 'Unknown Sender';

      // Hide recipient for received messages
      if (receiverContainer) {
        receiverContainer.style.display = 'none';
      }
    }

    // Set date
    date.textContent = item.formatted_date || (item.date || '');

    // Set subject if available
    if (subject && subjectContainer) {
      if (item.subject || item.title) {
        subject.textContent = item.subject || item.title;
        subjectContainer.style.display = 'block';
      } else {
        subjectContainer.style.display = 'none';
      }
    }

    // Set message content with proper formatting
    body.textContent = item.content || item.message || '';
    if (body.textContent) {
      body.innerHTML = body.textContent.replace(/\n/g, '<br>');
    }

    // Set reply to ID
    if (item.id) {
      const messageId = item.id.split('_')[1] || item.id;
      replyToInput.value = messageId;
    }

    // Show modal with smooth transition
    modal.style.display = 'flex';
    modal.style.opacity = '0';
    setTimeout(() => {
      modal.style.opacity = '1';
    }, 10);
  }

  // Function to show reply form
  function showReplyForm() {
    const replyContainer = document.getElementById('replyContainer');
    const replyButton = document.getElementById('replyButton');

    replyContainer.style.display = 'block';
    replyButton.style.display = 'none';
    document.getElementById('messageReply').focus();

    // Scroll to reply form
    setTimeout(() => {
      replyContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
  }

  // Function to hide reply form
  function hideReplyForm() {
    const replyContainer = document.getElementById('replyContainer');
    const replyButton = document.getElementById('replyButton');

    replyContainer.style.display = 'none';
    replyButton.style.display = 'inline-block';
    document.getElementById('messageReply').value = '';
  }

  // Function to close the modal
  function closeModal() {
    const modal = document.getElementById('notificationModal');
    modal.style.opacity = '0';
    setTimeout(() => {
      modal.style.display = 'none';
    }, 200);
  }

  // Function to send a reply
  function sendReply() {
    const replyText = document.getElementById('messageReply').value.trim();
    const senderId = document.getElementById('replyContainer').getAttribute('data-sender-id');

    if (!replyText) {
      alert('Please enter a message');
      return;
    }

    // Here you would typically send the reply to the server
    console.log('Sending reply to', senderId, ':', replyText);

    // For now, just show an alert and close the modal
    alert('Reply sent!');
    document.getElementById('messageReply').value = '';
    closeModal();
  }

  // Close modal when clicking the X or outside the modal
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('notificationModal');
    const closeBtn = modal.querySelector('.close');

    // Close when clicking the X
    closeBtn.addEventListener('click', closeModal);

    // Close when clicking outside the modal content
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeModal();
      }
    });

    // Close with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        closeModal();
      }
    });
  });

  document.addEventListener('DOMContentLoaded', function() {
    // Load notifications
    loadNotifications();

    // Set up search functionality for messages
    const searchInput = document.getElementById('messageSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const items = document.querySelectorAll('.message-item');

        items.forEach(item => {
          const content = item.textContent.toLowerCase();
          if (content.includes(searchTerm)) {
            item.style.display = '';
          } else {
            item.style.display = 'none';
          }
        });
      });
    }

    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);

    // Set up message modal close button
    document.querySelectorAll('.modal .close').forEach(close => {
      close.addEventListener('click', function() {
        document.querySelectorAll('.modal').forEach(modal => {
          modal.style.opacity = '0';
          setTimeout(() => {
            modal.style.display = 'none';
          }, 200);
        });
      });
    });

    // Set up compose button
    document.getElementById('composeBtn').addEventListener('click', function() {
      composeMessageModal.style.display = 'block';
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
      const messageModal = document.getElementById('messageModal');
      const notificationModal = document.getElementById('notificationModal');

      if (event.target === messageModal) {
        messageModal.style.opacity = '0';
        setTimeout(() => {
          messageModal.style.display = 'none';
        }, 200);
      }

      if (event.target === notificationModal) {
        notificationModal.style.opacity = '0';
        setTimeout(() => {
          notificationModal.style.display = 'none';
        }, 200);
      }
    });
  });

  // Function to send reply to a message
  function sendReply() {
    const messageId = document.getElementById('replyToMessageId').value;
    const replyContent = document.getElementById('messageReply').value.trim();

    if (!replyContent) {
      alert('Please enter a reply');
      return;
    }

    // Show loading state
    const sendButton = document.querySelector('#messageReplySection button');
    const originalText = sendButton.textContent;
    sendButton.textContent = 'Sending...';
    sendButton.disabled = true;

    // Send reply - use the existing API endpoint with the correct parameter name
    fetch('api/send_reply.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `message_id=${messageId}&reply=${encodeURIComponent(replyContent)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Clear textarea and close modal
        document.getElementById('messageReply').value = '';
        document.getElementById('messageModal').style.display = 'none';

        // Reload notifications to show the new message
        loadNotifications();
      } else {
        alert('Failed to send reply: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error sending reply:', error);
      alert('An error occurred while sending your reply');
    })
    .finally(() => {
      // Reset button
      sendButton.textContent = originalText;
      sendButton.disabled = false;
    });
  }
  </script>

  <style>
  /* Messages & Notifications Styles */
  .messages-filter {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    overflow-x: auto;
    padding-bottom: 8px;
    justify-content: flex-start;
  }

  /* Message item and modal styles */
  .message-item {
    border-radius: 8px;
    margin-bottom: 8px;
    padding: 12px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
  }

  .message-item:hover {
    border-color: #2563eb;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.1);
    transform: translateY(-2px);
  }

  .message-btn {
    cursor: pointer;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: #2563eb;
    transition: all 0.2s ease;
  }

  .message-btn:hover {
    background: rgba(37, 99, 235, 0.1);
    transform: scale(1.1);
  }

  /* Modal styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
  }

  .modal-content {
    background-color: #ffffff;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 600px;
    position: relative;
    animation: modalFadeIn 0.3s;
  }

  @keyframes modalFadeIn {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .filter-btn {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    color: #374151;
    cursor: pointer;
    font-size: 14px;
    padding: 6px 12px;
    transition: all 0.2s;
    position: relative;
    flex-shrink: 0;
    display: inline-block;
  }

  .filter-btn:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }

  .filter-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
  }

  .count-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .messages-search {
    margin-bottom: 16px;
  }

  .messages-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
  }

  .messages-search input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
  }

  .message-item {
    position: relative;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
    cursor: pointer;
  }

  .message-item:hover {
    background: #ffffff;
    transform: translateY(-2px);
    border-color: #3b82f6;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
  }

  .message-item.unread {
    background: #ebf5ff;
    border-left: 3px solid #3b82f6;
  }

  .message-content {
    display: flex;
    gap: 10px;
  }

  .message-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .message-info {
    flex-grow: 1;
  }

  .message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
  }

  .message-title {
    font-weight: 600;
    font-size: 14px;
    color: #111827;
  }

  .message-date {
    font-size: 12px;
    color: #6b7280;
  }

  .message-sender {
    font-size: 12px;
    color: #4b5563;
    margin-bottom: 4px;
  }

  .message-text {
    font-size: 13px;
    color: #4b5563;
    line-height: 1.4;
  }

  .unread-indicator {
    width: 8px;
    height: 8px;
    background-color: #3b82f6;
    border-radius: 50%;
    position: absolute;
    top: 12px;
    right: 12px;
  }

  .btn-retry {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    margin-top: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .messages-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-size: 14px;
  }

  .messages-list {
    max-height: 500px;
    overflow-y: auto;
  }

  .message-item {
    border-left: 4px solid transparent;
    transition: all 0.2s;
    margin-bottom: 12px;
    cursor: pointer;
  }

  .message-item.unread {
    border-left-color: #3b82f6;
    background-color: #eff6ff;
  }

  .message-content {
    display: flex;
    gap: 12px;
  }

  .message-icon {
    font-size: 16px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .message-info {
    flex: 1;
  }

  .message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
  }

  .message-title {
    font-weight: 600;
    font-size: 14px;
    margin: 0;
  }

  .message-date {
    font-size: 12px;
    color: #6b7280;
  }

  .message-sender {
    font-size: 13px;
    color: #4b5563;
    margin-bottom: 4px;
  }

  .message-text {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
  }

  .btn-refresh {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    font-size: 14px;
    float: right;
  }

  .btn-refresh:hover {
    color: #3b82f6;
  }

  /* Modal Styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
  }

  .modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    width: 80%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    position: relative;
  }

  .close {
    position: absolute;
    top: 10px;
    right: 15px;
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
  }

  .close:hover {
    color: black;
  }
</style>

<script>
  // Initialize the page when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Load both messages and notifications
    loadMessages();
    loadNotifications();
  });

  // Function to load notifications
  function loadNotifications() {
    const container = document.getElementById('notificationsList');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<p class="text-center py-4">Loading notifications...</p>';

    // Use sample data for now (not connected to database yet)
    const sampleData = {
      success: true,
      data: [
        {
          id: 'n_1',
          type: 'notification',
          title: 'Notification Testing',
          message: 'try 1 hello',
          sender: 'Admin User',
          sender_role: 'Admin',
          timestamp: '2025-05-17T21:20:00',
          is_read: false
        },
        {
          id: 'n_2',
          type: 'notification',
          title: 'New Feature',
          message: 'Check out the new patient messaging feature in your dashboard',
          timestamp: '2025-05-18T16:20:00',
          is_read: true
        }
      ]
    };

    // Process the data
    // Clear container
    container.innerHTML = '';

    if (sampleData.data.length === 0) {
      container.innerHTML = '<p class="text-center py-4 text-gray-500">No notifications found</p>';
      return;
    }

    // Add each notification as a separate card
    sampleData.data.forEach(notification => {
      // Format timestamp
      const timestamp = new Date(notification.timestamp);
      const timeString = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const dateString = timestamp.toLocaleDateString();

      // Create notification card
      const card = document.createElement('div');
      card.className = 'message-item bg-white rounded-lg p-3 cursor-pointer transition-all hover:shadow-md mb-2';

      // Style based on read status
      if (!notification.is_read) {
        card.classList.add('border-l-4', 'border-blue-500', 'bg-blue-50', 'unread');
      }

      // Add onClick handler
      card.onclick = function() {
        showItemModal(notification);
      };

      // Create notification content
      card.innerHTML = `
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <div class="font-medium">${notification.title || 'Notification'}<span class="text-xs text-gray-500 ml-2">${dateString}, ${timeString}</span></div>
            <div class="text-xs text-gray-500 mt-1">From: ${notification.sender || 'Admin User'} (${notification.sender_role || 'Admin'})</div>
            <div class="text-sm text-gray-600 mt-1">${notification.message.substring(0, 120)}${notification.message.length > 120 ? '...' : ''}</div>
          </div>
          <div>
            <i class="fas fa-bell text-gray-400"></i>
          </div>
        </div>
      `;

      // Add to container
      container.appendChild(card);
    });
  }

  // Function to toggle messages panel
  function toggleMessagesPanel() {
    const panel = document.getElementById('messagesPanel');

    if (panel) {
      const isHidden = panel.classList.contains('hidden');

      // Toggle panel visibility
      if (isHidden) {
        // Show panel
        panel.classList.remove('hidden');
        panel.style.display = 'block';
        // Load messages when opening
        loadMessages();
      } else {
        // Hide panel
        panel.classList.add('hidden');
        panel.style.display = 'none';
      }

      // Close user dropdown if open
      const userDropdown = document.getElementById('userDropdownMenu');
      if (userDropdown && userDropdown.classList.contains('show')) {
        userDropdown.classList.remove('show');
      }
    }
  }

    // Function to filter messages by type (message/notification)
    function filterMessages(filter) {
      const messageBtn = document.getElementById('messageFilterBtn');
      const notificationBtn = document.getElementById('notificationFilterBtn');
      const messagesContainer = document.getElementById('messagesList');

      // Reset all buttons first
      [messageBtn, notificationBtn].forEach(btn => {
        btn.className = 'inline-block py-3 px-6 text-center font-medium text-gray-500 hover:bg-gray-100 transition-colors duration-200';
        btn.querySelector('i').className = 'far mr-2 text-gray-400';
      });

      // Set active state for the selected tab
      if (filter === 'message') {
        messageBtn.classList.remove('text-gray-500', 'hover:bg-gray-100');
        messageBtn.classList.add('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        messageBtn.querySelector('i').classList.remove('text-gray-400');
        messageBtn.querySelector('i').classList.add('text-blue-500', 'fa-comment-dots');

        notificationBtn.querySelector('i').classList.add('fa-bell');
      } else {
        notificationBtn.classList.remove('text-gray-500', 'hover:bg-gray-100');
        notificationBtn.classList.add('text-blue-600', 'border-b-2', 'border-blue-600', 'bg-blue-50');
        notificationBtn.querySelector('i').classList.remove('text-gray-400');
        notificationBtn.querySelector('i').classList.add('text-blue-500', 'fa-bell');

        messageBtn.querySelector('i').classList.add('fa-comment-dots');
      }

      // Filter messages in the list
      const messageItems = messagesContainer.querySelectorAll('.message-item');
      let hasMessages = false;

      messageItems.forEach(item => {
        const itemType = item.getAttribute('data-type');
        if (itemType === filter) {
          item.style.display = 'flex';
          hasMessages = true;
        } else {
          item.style.display = 'none';
        }
      });

      // Show no messages found if no items match the filter
      const noMessagesElement = messagesContainer.querySelector('.no-messages');
      if (noMessagesElement) {
        noMessagesElement.remove();
      }

      if (!hasMessages) {
        const noMessages = document.createElement('p');
        noMessages.className = 'text-gray-500 text-center py-4 no-messages';
        noMessages.textContent = `No ${filter}s found`;
        messagesContainer.appendChild(noMessages);
      }
    }

    // Function to render items in a container
    function renderItems(container, items, type) {
      // Clear container
      container.innerHTML = '';

      // Filter items by type if specified
      const filteredItems = type ? items.filter(item => item.type === type) : items;

      if (filteredItems.length === 0) {
        container.innerHTML = `<p class="text-center py-4 text-gray-500">No ${type}s found</p>`;
        return;
      }

      // Add each item to the container
      filteredItems.forEach(itemData => {
        // Create message item
        const item = document.createElement('div');
        item.className = 'message-item bg-white rounded-lg p-3 cursor-pointer transition-all hover:shadow-md mb-2';
        item.setAttribute('data-id', itemData.id);
        item.setAttribute('data-type', itemData.type);

        // Add click handler to show modal
        item.onclick = function() {
          showItemModal(itemData);
        };

        // Style based on read status
        if (!itemData.is_read) {
          item.classList.add('border-l-4', 'border-blue-500', 'bg-blue-50', 'unread');
        }

        // Add icon based on type
        const iconClass = itemData.type === 'message' ? 'fa-envelope' : 'fa-bell';
        const typeBadge = itemData.type === 'message' ? 
          '<span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Message</span>' :
          '<span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Notification</span>';

        // Format timestamp
        const timestamp = new Date(itemData.timestamp);
        const timeString = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const dateString = timestamp.toLocaleDateString();

        // Create item HTML based on type
        if (itemData.type === 'message') {
          // Message format with sender info
          item.innerHTML = `
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="font-medium">You<span class="text-xs text-gray-500 ml-2">${dateString}, ${timeString}</span></div>
                <div class="text-sm text-gray-600 mt-1">${itemData.message.substring(0, 120)}${itemData.message.length > 120 ? '...' : ''}</div>
              </div>
              <div class="flex items-center">
                <button class="message-btn" onclick="event.stopPropagation(); composeMessageTo(event, '${itemData.sender_id || ''}', '${itemData.sender || 'User'}')">
                  <i class="fas fa-reply text-blue-500"></i>
                </button>
              </div>
            </div>
          `;
        } else {
          // Notification format with sender info
          item.innerHTML = `
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="font-medium">${itemData.title || 'Notification'}<span class="text-xs text-gray-500 ml-2">${dateString}, ${timeString}</span></div>
                <div class="text-xs text-gray-500 mt-1">From: ${itemData.sender || 'Admin User'} (${itemData.sender_role || 'Admin'})</div>
                <div class="text-sm text-gray-600 mt-1">${itemData.message.substring(0, 120)}${itemData.message.length > 120 ? '...' : ''}</div>
              </div>
              <div>
                <i class="fas fa-bell text-gray-400"></i>
              </div>
            </div>
          `;
        }

        // Add click event to mark as read and open message/notification modal
        item.addEventListener('click', () => {
          // Show message/notification modal
          showItemModal(itemData);

          // Mark as read if not already read
          if (!itemData.is_read) {
            itemData.is_read = true;
            item.classList.remove('border-l-4', 'border-blue-500', 'bg-blue-50', 'unread');

            // Update the unread count if this is a message
            if (itemData.type === 'message') {
              const messageCountElement = document.getElementById('messageCount');
              if (messageCountElement) {
                const currentCount = parseInt(messageCountElement.textContent) || 0;
                if (currentCount > 0) {
                  messageCountElement.textContent = currentCount - 1;
                }
              }
            }
          }
        });

        // Add the item to the container
        container.appendChild(item);
      });
    }

    // Function to show item modal with full content
    function showItemModal(itemData) {
      // Get the modal element (create it if it doesn't exist)
      let modal = document.getElementById('itemDetailModal');

      if (!modal) {
        // Create modal if it doesn't exist
        modal = document.createElement('div');
        modal.id = 'itemDetailModal';
        modal.className = 'modal';
        modal.innerHTML = `
          <div class="modal-content">
            <div class="modal-header flex justify-between items-center mb-4 pb-2 border-b">
              <h3 id="itemModalTitle" class="text-lg font-semibold"></h3>
              <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closeItemModal()">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <div class="flex items-center mb-2">
                  <span class="text-gray-700 font-medium mr-2">From:</span>
                  <span id="itemModalSender" class="text-gray-900"></span>
                </div>
                <div class="flex items-center mb-2">
                  <span class="text-gray-700 font-medium mr-2">Date:</span>
                  <span id="itemModalDate" class="text-gray-900"></span>
                </div>
              </div>
              <div class="bg-gray-50 p-3 rounded-md">
                <p id="itemModalContent" class="text-gray-700 whitespace-pre-wrap"></p>
              </div>
              <div id="itemModalReplySection" class="mt-4 hidden">
                <div class="flex justify-between items-center mb-2">
                  <h4 class="font-medium">Reply</h4>
                </div>
                <textarea id="itemModalReplyText" rows="3" class="w-full p-2 border rounded-md resize-none focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Type your reply here..."></textarea>
                <div class="flex justify-end mt-2">
                  <button type="button" id="itemModalSendReply" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    <i class="fas fa-paper-plane mr-1"></i> Send Reply
                  </button>
                </div>
              </div>
              <div id="itemModalActionsSection" class="mt-4 flex justify-end">
                <button type="button" id="itemModalComposeBtn" class="px-4 py-2 bg-white border border-blue-500 text-blue-500 rounded-md hover:bg-blue-50 hidden">
                  <i class="fas fa-reply mr-1"></i> Compose Message
                </button>
                <button type="button" class="ml-2 px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300" onclick="closeItemModal()">
                  Close
                </button>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(modal);
      }

      // Get elements
      const title = document.getElementById('itemModalTitle');
      const sender = document.getElementById('itemModalSender');
      const date = document.getElementById('itemModalDate');
      const content = document.getElementById('itemModalContent');
      const replySection = document.getElementById('itemModalReplySection');
      const replyButton = document.getElementById('itemModalSendReply');

      // Format date
      const timestamp = new Date(itemData.timestamp);
      const formattedDate = timestamp.toLocaleString([], {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });

      // Populate modal
      title.textContent = itemData.title || (itemData.type === 'message' ? 'Message' : 'Notification');
      sender.textContent = itemData.sender ? `${itemData.sender} (${itemData.sender_role || 'User'})` : 'System';
      date.textContent = formattedDate;
      content.textContent = itemData.message;

      // Show/hide reply section based on item type
      const composeBtn = document.getElementById('itemModalComposeBtn');

      if (itemData.type === 'message') {
        // For messages, show reply section
        replySection.classList.remove('hidden');
        composeBtn.classList.add('hidden');

        // Set up reply button
        replyButton.onclick = function() {
          sendReplyTo(itemData.id, document.getElementById('itemModalReplyText').value);
        };
      } else {
        // For notifications, hide reply section but show compose button
        replySection.classList.add('hidden');
        composeBtn.classList.remove('hidden');

        // Set up compose button
        composeBtn.onclick = function() {
          // Close the modal
          closeItemModal();

          // Open compose message interface
          composeMessageTo(event, itemData.sender_id || '', itemData.sender || 'Admin');
        };
      }

      // Show modal
      modal.style.display = 'block';
    }

    // Function to close the item modal
    function closeItemModal() {
      const modal = document.getElementById('itemDetailModal');
      if (modal) {
        modal.style.display = 'none';
      }
    }

    // Function to send a reply to a message
    function sendReplyTo(messageId, replyText) {
      if (!replyText.trim()) {
        alert('Please enter a reply message');
        return;
      }

      // Here you would typically send the reply to the server
      // For now, we'll just simulate a successful reply
      alert('Reply sent successfully!');

      // Clear the reply text
      document.getElementById('itemModalReplyText').value = '';

      // Close the modal
      closeItemModal();
    }

    // Function to compose a new message to someone
    function composeMessageTo(event, recipientId, recipientName) {
      event.stopPropagation(); // Prevent opening the message detail modal

      // Here you would open a compose message modal
      alert(`Compose message to ${recipientName}`);

      // Typically, you would have a modal for composing messages
      // For demo purposes, we're just showing an alert
    }

    // Function to load messages (using sample data for now)
    function loadMessages() {
      const container = document.getElementById('messagesList');
      const messageCountElement = document.getElementById('messageCount');

      if (!container) return;

      // Show loading state
      container.innerHTML = '<p class="text-center py-4">Loading messages...</p>';

      // Use sample data for now (not connected to database yet)
      const sampleData = {
        success: true,
        data: [
          {
            id: 'm_1',
            type: 'message',
            sender: 'You',
            sender_role: 'Doctor',
            message: 'sdasd\ndsad',
            timestamp: '2025-05-19T10:04:00',
            is_read: false
          },
          {
            id: 'm_2',
            type: 'message',
            sender: 'You',
            sender_role: 'Doctor',
            message: 'dsad\ndsadas',
            timestamp: '2025-05-19T10:04:00',
            is_read: false
          }
        ]
      };

      // Process the data
      setTimeout(() => {
        const messages = sampleData.data;
        const unreadCount = messages.filter(msg => !msg.is_read).length;

        // Update message count in navbar if element exists
        if (messageCountElement) {
          messageCountElement.textContent = unreadCount;
        }

        // Render messages
        renderItems(container, messages, 'message');
      } catch (error) {
        console.error('Error loading messages:', error);
        container.innerHTML = '<p class="text-center py-4 text-red-500">Error loading messages</p>';
      }
    }, 500);
  }

  // Function to show message/notification modal
  function showMessageModal(item) {
    const modal = document.getElementById('messageModal');
    if (!modal) return;

    // Set modal content based on item type
    const title = item.title || 'No Subject';
    const message = item.message || 'No content';
    const timestamp = new Date(item.timestamp).toLocaleString();
    const type = item.type === 'message' ? 'Message' : 'Notification';
    const icon = item.type === 'message' ? 'envelope' : 'bell';

    // Update modal content
    modal.querySelector('.modal-title').textContent = title;
    modal.querySelector('.modal-body').innerHTML = `
        <div class="mb-3">
          <div class="d-flex align-items-center mb-2">
            <i class="fas fa-${icon} me-2 text-primary"></i>
            <span class="badge bg-${item.type === 'message' ? 'primary' : 'success'} mb-1">${type}</span>
          </div>
          <p class="text-muted small mb-2">${timestamp}</p>
          <div class="border rounded p-3 bg-light">
            <p class="mb-0">${message}</p>
          </div>
        </div>
      `;

      // Show the modal
      const modalInstance = new bootstrap.Modal(modal);
      modalInstance.show();
    }

    // Function to handle API errors with retry button
    function handleApiError(error, container, retryFunction) {
      console.error('Error:', error);
      container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load data</p>' +
                          '<div class="text-center"><button class="py-1 px-3 bg-blue-500 text-white rounded hover:bg-blue-600" onclick="' + retryFunction + '()"><i class="fas fa-sync-alt mr-1"></i>Retry</button></div>';
    }

    // Helper function to format date
    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    }

    // Helper function for time ago display
    function timeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const secondsPast = (now.getTime() - date.getTime()) / 1000;

      if (secondsPast < 60) {
        return 'Just now';
      }
      if (secondsPast < 3600) {
        return `${Math.floor(secondsPast / 60)}m ago`;
      }
      if (secondsPast < 86400) {
        return `${Math.floor(secondsPast / 3600)}h ago`;
      }
      if (secondsPast < 604800) {
        return `${Math.floor(secondsPast / 86400)}d ago`;
      }
      return formatDate(dateString);
    }

    // Message Modal Functions
    function showMessageModal(messageElement) {
      // Get data from clicked message
      const messageId = messageElement.getAttribute('data-message-id');
      const sender = messageElement.getAttribute('data-sender');
      const subject = messageElement.getAttribute('data-subject');
      const content = messageElement.getAttribute('data-content');
      const isNotification = messageElement.classList.contains('notification-card');

      // Set modal content
      document.getElementById('modalTitle').textContent = isNotification ? 'Notification Details' : 'Message Details';
      document.getElementById('modalSender').textContent = sender;

      // Show/hide subject if it exists
      const subjectContainer = document.getElementById('modalSubjectContainer');
      if (subject && subject.trim() !== '') {
          document.getElementById('modalSubject').textContent = subject;
          subjectContainer.classList.remove('hidden');
      } else {
          subjectContainer.classList.add('hidden');
      }

      // Set message content
      document.getElementById('modalContent').textContent = content;

      // Show/hide reply container for messages (not for notifications)
      const replyContainer = document.getElementById('replyContainer');
      if (isNotification) {
          replyContainer.classList.add('hidden');
      } else {
          replyContainer.classList.remove('hidden');
          // Set data attribute for reply button
          document.getElementById('sendReplyBtn').setAttribute('data-message-id', messageId);
          document.getElementById('sendReplyBtn').onclick = function() {
              sendReply(messageId);
          };
      }

      // Show modal
      document.getElementById('messageModal').classList.remove('hidden');
    }

    // Function to open the compose message modal
    function showComposeModal(recipient) {
      const modal = document.getElementById('composeModal');
      const select = document.getElementById('recipientSelect');

      // Clear previous selection
      while (select.options.length > 1) {
        select.remove(1);
      }

      // Add option for the recipient if provided
      if (recipient) {
        const option = document.createElement('option');
        option.value = recipient;
        option.text = recipient;
        option.selected = true;
        select.add(option);
      }

      // Show the modal
      modal.classList.remove('hidden');
    }

    // Function to close the compose message modal
    function closeComposeModal() {
      document.getElementById('composeModal').classList.add('hidden');

      // Clear form fields
      document.getElementById('messageSubject').value = '';
      document.getElementById('messageContent').value = '';
    }

    // Function to open message dialog with a specific user
    function openMessageDialog(userId) {
      // Show compose modal
      const modal = document.getElementById('composeModal');
      const select = document.getElementById('recipientSelect');

      // Get the recipient information
      fetch('api/get_user.php?user_id=' + userId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Clear previous selection
          while (select.options.length > 1) {
            select.remove(1);
          }

          // Add user to recipient dropdown
          const option = document.createElement('option');
          option.value = userId;
          option.text = data.name || 'User #' + userId;
          option.selected = true;
          select.add(option);

          // Show the modal
          modal.classList.remove('hidden');
        } else {
          alert('Error loading recipient information.');
        }
      })
      .catch(error => {
        console.error('Error:', error);

        // Fallback if API call fails - still show the modal with a generic option
        while (select.options.length > 1) {
          select.remove(1);
        }

        const option = document.createElement('option');
        option.value = userId;
        option.text = 'User #' + userId;
        option.selected = true;
        select.add(option);

        modal.classList.remove('hidden');
      });
    }

    // Close message modal
    function closeMessageModal() {
      document.getElementById('messageModal').classList.add('hidden');
      // Clear reply texta
      document.getElementById('replyText').value = '';
    }

    // Add click handlers for notification cards after they're loaded
    setTimeout(function() {
      // Add click event to notification cards
      const notificationCards = document.querySelectorAll('.notification-card');
      notificationCards.forEach(card => {
        card.addEventListener('click', function() {
            // Get notification data
            const title = this.querySelector('.font-medium').textContent.split(' ')[0]; // Get just the title
            const sender = this.querySelector('.text-xs.text-gray-500').textContent;
            const content = this.querySelector('.text-sm.text-gray-600').textContent;

            // Set data attributes
            this.setAttribute('data-sender', sender);
            this.setAttribute('data-content', content);
            this.setAttribute('data-subject', title);

            // Show modal
            showMessageModal(this);
        });
      });
    }, 2000);
  </script>
</body>
</html>
