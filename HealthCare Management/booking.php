<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Location: auth/login.php');
    exit();
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Name not available';
$user_email = $_SESSION['email'] ?? 'Email not available';
$user_role = $_SESSION['role'] ?? 'patient';

// Database connection
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$selectedDoctor = null;
$doctors = [];
$appointmentTypes = ['General Checkup', 'Follow-up', 'Consultation', 'Vaccination', 'Laboratory', 'Prescription Renewal', 'Other'];
$bookingSuccess = false;
$bookingError = '';

// Get all active doctors
$doctorQuery = "SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, d.specialty
                FROM users u
                JOIN doctors d ON u.user_id = d.doctor_id
                WHERE u.role = 'doctor'
                ORDER BY u.last_name, u.first_name";
$doctorResult = $conn->query($doctorQuery);

if ($doctorResult && $doctorResult->num_rows > 0) {
    while ($row = $doctorResult->fetch_assoc()) {
        $doctors[] = $row;
        
        // If doctor_id is in URL, set as selected
        if (isset($_GET['doctor_id']) && $_GET['doctor_id'] == $row['user_id']) {
            $selectedDoctor = $row['user_id'];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $doctor_id = $_POST['doctor_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $appointment_type = $_POST['appointment_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($appointment_type)) {
        $bookingError = 'Please fill in all required fields.';
    } else {
        // Check if the doctor exists
        $checkDoctorQuery = "SELECT user_id FROM users WHERE user_id = ? AND role = 'doctor'";
        $checkDoctorStmt = $conn->prepare($checkDoctorQuery);
        $checkDoctorStmt->bind_param("i", $doctor_id);
        $checkDoctorStmt->execute();
        $checkDoctorResult = $checkDoctorStmt->get_result();
        
        if ($checkDoctorResult->num_rows === 0) {
            $bookingError = 'Selected doctor does not exist.';
        } else {
            // Combine date and time for appointment_datetime
            $appointment_datetime = $appointment_date . ' ' . $appointment_time;
            
            // Check if the selected time is available (not already booked)
            $checkAvailabilityQuery = "SELECT appointment_id FROM appointments 
                                       WHERE doctor_id = ? AND appointment_datetime = ? AND status != 'cancelled'";
            $checkAvailabilityStmt = $conn->prepare($checkAvailabilityQuery);
            $checkAvailabilityStmt->bind_param("is", $doctor_id, $appointment_datetime);
            $checkAvailabilityStmt->execute();
            $checkAvailabilityResult = $checkAvailabilityStmt->get_result();
            
            if ($checkAvailabilityResult->num_rows > 0) {
                $bookingError = 'This time slot is already booked. Please select a different time.';
            } else {
                // Insert appointment into database
                $insertQuery = "INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, appointment_type, reason, status) 
                                VALUES (?, ?, ?, ?, ?, 'pending')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("iisss", $user_id, $doctor_id, $appointment_datetime, $appointment_type, $reason);
                
                if ($insertStmt->execute()) {
                    $bookingSuccess = true;
                } else {
                    $bookingError = 'Failed to book appointment. Please try again later.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>DocNow - Book Appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
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

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
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
    </style>
</head>
<body class="bg-gray-100 font-sans flex flex-col min-h-screen relative overflow-x-hidden">
    <header class="bg-blue-600 shadow-md">
        <nav class="w-full flex items-center justify-between h-16">
            <div class="logo-section">
                <img src="Pictures/Logo.jpg" alt="DocNow Logo" class="h-8 w-8 rounded ml-4">
                <span class="text-white font-semibold text-base select-none">DocNow</span>
            </div>
            <div class="nav-links">
                <a href="patient_dashboard.php" class="nav-link">Dashboard</a>
                <a href="doctors.php" class="nav-link">Doctors</a>
                <a href="booking.php" class="nav-link active">Booking</a>
                <div class="dropdown">
                    <div class="dropdown-toggle" id="userDropdown">
                        <span><?php echo htmlspecialchars($user_name); ?></span>
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
                <!-- Mobile menu button -->
                <button
                    id="mobileMenuButton"
                    class="md:hidden text-white text-xl p-2 hover:text-blue-300 transition-colors"
                    onclick="toggleMobileMenu()"
                    type="button"
                >
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </nav>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="fixed inset-y-0 right-0 w-64 bg-blue-800 transform translate-x-full transition-transform duration-200 ease-in-out md:hidden">
        <div class="flex flex-col h-full">
            <div class="flex justify-end p-4">
                <button
                    aria-label="Close mobile menu"
                    class="text-white text-xl p-2 hover:text-blue-300 transition-colors"
                    onclick="toggleMobileMenu()"
                    type="button"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="flex flex-col space-y-4 p-4">
                <a href="patient_dashboard.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Dashboard</a>
                <a href="doctors.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Doctors</a>
                <a href="booking.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors bg-blue-900">Booking</a>
                <hr class="border-blue-700">
                <a href="change_password.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Change Password</a>
                <a href="auth/logout.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Sign Out</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <?php if ($bookingSuccess): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"> Your appointment has been booked successfully.</span>
                    <a href="patient_dashboard.php" class="mt-3 inline-block bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded">
                        Return to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($bookingError)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"> <?php echo $bookingError; ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Book an Appointment</h2>
                    
                    <form id="bookingForm" action="booking.php" method="POST" class="space-y-6">
                        <!-- Doctor Selection -->
                        <div class="space-y-2">
                            <label for="doctor_id" class="block text-sm font-medium text-gray-700">Select Doctor <span class="text-red-500">*</span></label>
                            <select id="doctor_id" name="doctor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">-- Select a Doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['user_id']; ?>" <?php echo ($selectedDoctor == $doctor['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['doctor_name']); ?> 
                                        <?php echo !empty($doctor['specialty']) ? '- ' . htmlspecialchars($doctor['specialty']) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Appointment Type -->
                        <div class="space-y-2">
                            <label for="appointment_type" class="block text-sm font-medium text-gray-700">Appointment Type <span class="text-red-500">*</span></label>
                            <select id="appointment_type" name="appointment_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">-- Select Appointment Type --</option>
                                <?php foreach ($appointmentTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Selection -->
                        <div class="space-y-2">
                            <label for="appointment_date" class="block text-sm font-medium text-gray-700">Date <span class="text-red-500">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" 
                                  min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Time Selection -->
                        <div class="space-y-2">
                            <label for="appointment_time" class="block text-sm font-medium text-gray-700">Time <span class="text-red-500">*</span></label>
                            <select id="appointment_time" name="appointment_time" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">-- Select a Time Slot --</option>
                                <?php 
                                // Generate time slots from 9 AM to 5 PM
                                $start = 9; // 9 AM
                                $end = 17; // 5 PM
                                
                                for ($hour = $start; $hour < $end; $hour++) {
                                    $displayHour = $hour > 12 ? $hour - 12 : $hour;
                                    $ampm = $hour >= 12 ? 'PM' : 'AM';
                                    
                                    // For each hour, create slots for :00 and :30
                                    $time_00 = sprintf('%02d:00:00', $hour);
                                    $time_30 = sprintf('%02d:30:00', $hour);
                                    
                                    echo "<option value=\"$time_00\">$displayHour:00 $ampm</option>";
                                    echo "<option value=\"$time_30\">$displayHour:30 $ampm</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Reason for Visit -->
                        <div class="space-y-2">
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason for Visit</label>
                            <textarea id="reason" name="reason" rows="4" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Please describe the reason for your visit (optional)"></textarea>
                        </div>

                        <div class="pt-4">
                            <button type="submit" 
                                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center">
                                <i class="fas fa-calendar-check mr-2"></i> Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>© 2025 DocNow Healthcare. All rights reserved.</p>
                <p class="mt-2 text-gray-400 text-sm">Providing quality healthcare services online.</p>
            </div>
        </div>
    </footer>

    <script>
        // Toggle dropdown menu
        document.getElementById('userDropdown').addEventListener('click', function() {
            const dropdownMenu = document.getElementById('userDropdownMenu');
            dropdownMenu.classList.toggle('show');
            this.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-toggle') && 
                !event.target.parentNode.matches('.dropdown-toggle')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains('show')) {
                        dropdowns[i].classList.remove('show');
                    }
                }
                
                const toggles = document.getElementsByClassName('dropdown-toggle');
                for (let i = 0; i < toggles.length; i++) {
                    if (toggles[i].classList.contains('active')) {
                        toggles[i].classList.remove('active');
                    }
                }
            }
        });

        // Toggle mobile menu
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('translate-x-full');
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(event) {
            const doctor = document.getElementById('doctor_id').value;
            const appointmentType = document.getElementById('appointment_type').value;
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;
            
            if (!doctor || !appointmentType || !date || !time) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>