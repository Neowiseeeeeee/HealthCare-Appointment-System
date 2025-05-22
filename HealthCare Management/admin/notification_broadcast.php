<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/Login.php");
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

// Get notification history
$result = $conn->query("
    SELECT n.*, u.first_name, u.last_name, u.role
    FROM notifications n
    JOIN users u ON n.sender_id = u.user_id
    WHERE n.is_system = 1
    ORDER BY n.created_at DESC
    LIMIT 50
");

if ($result) {
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $notifications = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Notification Broadcasting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            max-width: 1280px;
            margin: 0 auto;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #1f2937;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #d1d5db;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .card-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f3f4f6;
        }

        .card-body {
            padding: 1rem;
        }

        .title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
        }

        /* Form Styles */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: #1f2937;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d5db;
            appearance: none;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #3b82f6;
            outline: 0;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 2.25rem 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: #1f2937;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            appearance: none;
        }

        .form-select:focus {
            border-color: #3b82f6;
            outline: 0;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #dc2626;
        }

        .search-box {
            padding: 0.5rem 0.75rem;
            padding-left: 2rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: #fff;
            font-size: 0.875rem;
            width: 240px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 0.625rem center;
            background-size: 1rem;
        }

        .search-box:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
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
            width: 80%;
            max-width: 700px;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-section">
            <img src="../assets/images/Logo.jpg" alt="DocNow Logo">
            <span>DocNow Admin</span>
        </div>
        <div class="nav-links">
            <div class="dropdown">
                <div class="dropdown-toggle" id="userDropdown">
                    <span><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <a href="../admin_dashboard.php" class="dropdown-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../change_password.php" class="dropdown-item">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../auth/logout.php" class="dropdown-item">
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
                    <h1 class="text-2xl font-bold">Notification Broadcasting</h1>
                    <p class="text-gray-600 text-sm">Send system-wide notifications to all users</p>
                </div>
                <div>
                    <a href="../admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

                <!-- Alert Container -->
                <div id="alertContainer" class="mb-4"></div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title mb-0">New Broadcast Message</h5>
                            </div>
                            <div class="card-body">
                                <form id="broadcastForm" method="post">
                                    <div class="mb-3">
                                        <label for="notificationTitle" class="form-label">Notification Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="notificationTitle" name="notificationTitle" required>
                                        <div id="notificationTitleError" class="invalid-feedback" style="display: none;">
                                            Please enter a notification title.
                                        </div>
                                        <div id="notificationTitleLengthError" class="invalid-feedback" style="display: none;">
                                            Title must be less than 255 characters.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notificationType" class="form-label">Notification Type</label>
                                        <select class="form-select" id="notificationType" name="notificationType">
                                            <option value="general" selected>General</option>
                                            <option value="appointment">Appointment</option>
                                            <option value="message">Message</option>
                                            <option value="medication">Medication</option>
                                            <option value="lab">Lab Results</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notificationContent" class="form-label">Message Content <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="notificationContent" name="notificationContent" rows="5" required></textarea>
                                        <div id="notificationContentError" class="invalid-feedback" style="display: none;">
                                            Please enter a message content.
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" id="submitBtn" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> Send Notification
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Broadcasts</h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="recentNotifications">
                                    <?php if (empty($notifications)): ?>
                                        <div class="text-center p-4 text-muted">
                                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                            <p>No recent notifications</p>
                                        </div>
                                    <?php else: ?>
                                        <?php 
                                        $displayCount = min(count($notifications), 5);
                                        for ($i = 0; $i < $displayCount; $i++): 
                                            $notification = $notifications[$i];
                                            $createDate = new DateTime($notification['created_at']);
                                            $timeAgo = $createDate->diff(new DateTime());
                                            
                                            if ($timeAgo->d > 0) {
                                                $timeAgoStr = $timeAgo->d . " day" . ($timeAgo->d > 1 ? "s" : "") . " ago";
                                            } elseif ($timeAgo->h > 0) {
                                                $timeAgoStr = $timeAgo->h . " hour" . ($timeAgo->h > 1 ? "s" : "") . " ago";
                                            } elseif ($timeAgo->i > 0) {
                                                $timeAgoStr = $timeAgo->i . " minute" . ($timeAgo->i > 1 ? "s" : "") . " ago";
                                            } else {
                                                $timeAgoStr = "Just now";
                                            }
                                        ?>
                                        <div class="notification-item">
                                            <div class="notification-header">
                                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                <div class="notification-time"><?php echo $timeAgoStr; ?></div>
                                            </div>
                                            <div class="notification-content"><?php echo htmlspecialchars($notification['content']); ?></div>
                                            <?php 
                                            $badgeClass = 'badge-info';
                                            switch ($notification['type']) {
                                                case 'appointment':
                                                    $badgeClass = 'badge-success';
                                                    break;
                                                case 'message':
                                                    $badgeClass = 'badge-info';
                                                    break;
                                                case 'medication':
                                                    $badgeClass = 'badge-warning';
                                                    break;
                                                case 'lab':
                                                    $badgeClass = 'badge-error';
                                                    break;
                                            }
                                            ?>
                                            <span class="notification-badge <?php echo $badgeClass; ?>"><?php echo ucfirst($notification['type']); ?></span>
                                        </div>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Notification Guide</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3 text-sm">Tips for effective system notifications:</p>
                                <ul class="text-sm ml-4">
                                    <li class="mb-2">Keep messages clear and concise</li>
                                    <li class="mb-2">Include only relevant information</li>
                                    <li class="mb-2">Use appropriate notification types</li>
                                    <li class="mb-2">Avoid sending too many notifications</li>
                                    <li>System notifications reach all users</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- System Notification Modal -->
        <div class="modal" id="systemNotificationModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Send System-wide Notification</h3>
                    <button class="btn-secondary" onclick="closeModal('systemNotificationModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 1rem; color: #6b7280;">Use this form to send a notification to all users in the system simultaneously.</p>
                    
                    <form id="systemNotificationForm">
                        <div style="margin-bottom: 1rem;">
                            <label for="sysNotificationTitle" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Notification Title <span style="color: #dc2626;">*</span></label>
                            <input type="text" id="sysNotificationTitle" class="form-control" required>
                            <div class="invalid-feedback" id="sysNotificationTitleError" style="display: none;">
                                Please enter a notification title.
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label for="sysNotificationType" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Notification Type</label>
                            <select id="sysNotificationType" class="form-select">
                                <option value="general" selected>General</option>
                                <option value="appointment">Appointment</option>
                                <option value="message">Message</option>
                                <option value="medication">Medication</option>
                                <option value="lab">Lab Results</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label for="sysNotificationContent" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Message Content <span style="color: #dc2626;">*</span></label>
                            <textarea id="sysNotificationContent" class="form-control" rows="5" required></textarea>
                            <div class="invalid-feedback" id="sysNotificationContentError" style="display: none;">
                                Please enter a message content.
                            </div>
                        </div>
                    </form>
                    
                    <div id="systemNotificationAlertContainer"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('systemNotificationModal')">Close</button>
                    <button class="btn btn-primary" id="sendSystemNotificationBtn">
                        <i class="fas fa-paper-plane"></i> Send to All Users
                    </button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Toggle user dropdown
                const userDropdown = document.getElementById('userDropdown');
                const userDropdownMenu = document.getElementById('userDropdownMenu');
                
                userDropdown.addEventListener('click', function() {
                    userDropdownMenu.classList.toggle('show');
                    userDropdown.classList.toggle('active');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userDropdown.contains(event.target)) {
                        userDropdownMenu.classList.remove('show');
                        userDropdown.classList.remove('active');
                    }
                });

                // Validate and submit broadcast form
                const broadcastForm = document.getElementById('broadcastForm');
                
                if (broadcastForm) {
                    broadcastForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const title = document.getElementById('notificationTitle').value.trim();
                        const content = document.getElementById('notificationContent').value.trim();
                        const type = document.getElementById('notificationType').value;
                        
                        // Reset errors
                        document.getElementById('notificationTitleError').style.display = 'none';
                        document.getElementById('notificationTitleLengthError').style.display = 'none';
                        document.getElementById('notificationContentError').style.display = 'none';
                        
                        let valid = true;
                        
                        if (!title) {
                            document.getElementById('notificationTitleError').style.display = 'block';
                            valid = false;
                        } else if (title.length > 255) {
                            document.getElementById('notificationTitleLengthError').style.display = 'block';
                            valid = false;
                        }
                        
                        if (!content) {
                            document.getElementById('notificationContentError').style.display = 'block';
                            valid = false;
                        }
                        
                        if (valid) {
                            // For demo purposes, simulate submission
                            const submitBtn = document.getElementById('submitBtn');
                            const originalText = submitBtn.innerHTML;
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                            
                            // Make actual API call to create_system_notification.php
                            const formData = new FormData();
                            formData.append('notificationTitle', title);
                            formData.append('notificationContent', content);
                            formData.append('notificationType', type);
                            
                            fetch('create_system_notification.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                const alertContainer = document.getElementById('alertContainer');
                                
                                if (data.status === 'success') {
                                    // Show success message
                                    alertContainer.innerHTML = `
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="fas fa-check-circle me-2"></i> ${data.message}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    `;
                                    
                                    // Reset form
                                    broadcastForm.reset();
                                } else {
                                    // Show error message
                                    alertContainer.innerHTML = `
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="fas fa-exclamation-circle me-2"></i> Error: ${data.message}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    `;
                                }
                                
                                // Reset button
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                
                                // Show error message
                                const alertContainer = document.getElementById('alertContainer');
                                alertContainer.innerHTML = `
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i> Error: Network or server error occurred.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                
                                // Reset button
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                                
                                // Scroll to top to see the success message
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    });
                }
                
                // Handle system notification modal
                const sendSystemNotificationBtn = document.getElementById('sendSystemNotificationBtn');
                
                if (sendSystemNotificationBtn) {
                    sendSystemNotificationBtn.addEventListener('click', function() {
                        const title = document.getElementById('sysNotificationTitle').value.trim();
                        const content = document.getElementById('sysNotificationContent').value.trim();
                        const type = document.getElementById('sysNotificationType').value;
                        
                        // Reset errors
                        document.getElementById('sysNotificationTitleError').style.display = 'none';
                        document.getElementById('sysNotificationContentError').style.display = 'none';
                        
                        let valid = true;
                        
                        if (!title) {
                            document.getElementById('sysNotificationTitleError').style.display = 'block';
                            valid = false;
                        }
                        
                        if (!content) {
                            document.getElementById('sysNotificationContentError').style.display = 'block';
                            valid = false;
                        }
                        
                        if (valid) {
                            // Disable button and show loading state
                            const originalText = sendSystemNotificationBtn.innerHTML;
                            sendSystemNotificationBtn.disabled = true;
                            sendSystemNotificationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                            
                            // Create form data from inputs
                            const formData = new FormData();
                            formData.append('notificationTitle', titleInput.value);
                            formData.append('notificationContent', contentInput.value);
                            formData.append('notificationType', typeInput.value);
                            
                            // Send data to the server
                            fetch('../admin/create_system_notification.php', {
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
                                if (data.status === "success") {
                                    // Show success message
                                    const alertContainer = document.getElementById('systemNotificationAlertContainer');
                                    alertContainer.innerHTML = `
                                        <div style="margin-top: 1rem; padding: 0.75rem 1rem; background-color: #d1fae5; color: #047857; border-radius: 0.375rem;">
                                            <i class="fas fa-check-circle"></i> ${data.message || 'System notification sent successfully.'}
                                        </div>
                                    `;
                                    
                                    // Reset form
                                    document.getElementById('systemNotificationForm').reset();
                                    
                                    // Close modal after 3 seconds
                                    setTimeout(function() {
                                        closeModal('systemNotificationModal');
                                    }, 3000);
                                } else {
                                    throw new Error(data.message || 'Failed to send notification');
                                }
                            })
                            .catch(error => {
                                // Show error message
                                const alertContainer = document.getElementById('systemNotificationAlertContainer');
                                alertContainer.innerHTML = `
                                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background-color: #fee2e2; color: #b91c1c; border-radius: 0.375rem;">
                                        <i class="fas fa-exclamation-circle"></i> ${error.message || 'Failed to send notification'}
                                    </div>
                                `;
                            })
                            .finally(() => {
                                // Reset button
                                sendSystemNotificationBtn.disabled = false;
                                sendSystemNotificationBtn.innerHTML = originalText;
                            });
                        }
                    });
                }
            });

            // Modal functions
            function showSystemNotificationModal() {
                document.getElementById('systemNotificationModal').style.display = 'block';
            }

            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
            }
        </script>
        
        <!-- Notification History JavaScript -->
        <script src="../includes/notification_history.js"></script>
    </body>
</html>