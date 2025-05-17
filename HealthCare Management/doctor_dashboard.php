<?php
session_start();

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
    SELECT u.*, d.specialty, d.contact_number, d.availability_info
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Doctor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            margin-bottom: 1rem;
            height: calc(100vh - 340px);
            min-height: 330px;
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

        /* Add new styles for patient cards */
        .patient-card {
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
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
            margin-top: 1.5rem;
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
            margin-top: 0;
            padding-bottom: 0.5rem;
            position: sticky;
            bottom: 0;
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

    <main class="main-content">
        <div class="container">
            <div class="dashboard-header">
                <div>
                    <h1 style="font-size: 1.5rem; color: #1f2937;">Welcome back, Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h1>
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <p style="color: #6b7280;"><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Practitioner'); ?></p>
                        <p style="color: #6b7280;">|</p>
                        <p style="color: #6b7280;"><?php echo htmlspecialchars($doctor['email']); ?></p>
                    </div>
                </div>
                <div style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; display: flex; align-items: center; gap: 0.375rem;">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    <span>Available</span>
                </div>
            </div>

            <div class="dashboard-grid">
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

                <div class="card">
                    <div class="card-header">Upcoming Appointments</div>
                    <div class="card-body">
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
                                    
                                    <!-- Adding action buttons for appointment status update -->
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

                <div class="card">
                    <div class="card-header">Recent Messages</div>
                    <div class="card-body">
                        <?php if ($messages->num_rows > 0): ?>
                            <?php while ($row = $messages->fetch_assoc()): ?>
                                <div class="message-item patient-card">
                                    <div class="patient-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <div class="patient-visit">
                                        <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                    <div class="patient-visit">
                                        <strong>Subject:</strong> <?php echo htmlspecialchars($row['subject'] ?? 'No Subject'); ?>
                                    </div>
                                    <div class="patient-email">
                                        <?php echo htmlspecialchars(substr($row['content'], 0, 100) . (strlen($row['content']) > 100 ? '...' : '')); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No messages found.</p>
                        <?php endif; ?>
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
    <div id="newAppointmentModal" class="modal">
        <div class="modal-content">
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

    <!-- Compose Message Modal -->
    <div id="composeMessageModal" class="modal">
        <div class="modal-content">
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
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" action="update_profile.php" method="post">
                <div class="form-group">
                    <label for="specialty">Specialty</label>
                    <input type="text" id="specialty" name="specialty" value="<?php echo htmlspecialchars($doctor['specialty'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($doctor['contact_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="availability_info">Availability Information</label>
                    <textarea id="availability_info" name="availability_info" rows="3"><?php echo htmlspecialchars($doctor['availability_info'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto;">
            <span class="close" id="patientDetailsClose">&times;</span>
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
        var newAppointmentModal = document.getElementById('newAppointmentModal');
        var composeMessageModal = document.getElementById('composeMessageModal');
        var editProfileModal = document.getElementById('editProfileModal');
        var patientDetailsModal = document.getElementById('patientDetailsModal');
        var newAppointmentBtn = document.getElementById('newAppointmentBtn');
        var composeMessageBtn = document.getElementById('composeMessageBtn');
        var closeBtns = document.getElementsByClassName('close');

        newAppointmentBtn.onclick = function() {
            newAppointmentModal.style.display = 'block';
        }

        composeMessageBtn.onclick = function() {
            composeMessageModal.style.display = 'block';
        }

        function openEditProfile(e) {
            e.preventDefault();
            editProfileModal.style.display = 'block';
        }

        // Make sure the X button works for all modals
        for (var i = 0; i < closeBtns.length; i++) {
            closeBtns[i].onclick = function() {
                newAppointmentModal.style.display = 'none';
                composeMessageModal.style.display = 'none';
                editProfileModal.style.display = 'none';
                patientDetailsModal.style.display = 'none';
            }
        }
        
        // Specific handler for patient details close button
        document.getElementById('patientDetailsClose').addEventListener('click', function() {
            patientDetailsModal.style.display = 'none';
        });

        window.onclick = function(event) {
            if (event.target == newAppointmentModal) {
                newAppointmentModal.style.display = 'none';
            }
            if (event.target == composeMessageModal) {
                composeMessageModal.style.display = 'none';
            }
            if (event.target == editProfileModal) {
                editProfileModal.style.display = 'none';
            }
            if (event.target == patientDetailsModal) {
                patientDetailsModal.style.display = 'none';
            }
        }

        // Function to pre-select recipient in compose message modal
        function openMessageDialog(patientId) {
            var selectElement = document.getElementById('recipient');
            for (var i = 0; i < selectElement.options.length; i++) {
                if (selectElement.options[i].value == patientId) {
                    selectElement.options[i].selected = true;
                    break;
                }
            }
            composeMessageModal.style.display = 'block';
        }

        // Function to show patient details modal with patient information
        function showPatientDetails(patientId) {
            // Get the modal and content elements
            var modal = document.getElementById('patientDetailsModal');
            var contentDiv = document.getElementById('patientProfileContent');
            
            // Show loading state
            contentDiv.innerHTML = '<div style="text-align: center; padding: 20px;">Loading patient information...</div>';
            modal.style.display = 'block';
            
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
    </script>
</body>
</html>