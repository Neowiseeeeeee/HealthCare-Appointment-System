<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Location: auth/login.php');
    exit();
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Name not available';
$user_email = $_SESSION['email'] ?? 'Email not available';
$user_role = $_SESSION['role'] ?? 'patient';
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

    <!-- Slide menu -->
    <div
        aria-label="Slide menu"
        class="fixed top-16 right-4 h-auto w-52 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50"
        id="slideMenu"
    >
        <nav class="mt-8 flex flex-col space-y-3 px-6 pb-6">
            <a
                class="text-gray-800 font-semibold hover:text-blue-600 select-none rounded-md px-3 py-2 transition-colors"
                href="change_password.php"
            >Change Password</a>
            <a
                class="text-gray-800 font-semibold hover:text-blue-600 select-none rounded-md px-3 py-2 transition-colors"
                href="auth/logout.php"
            >Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Book an Appointment</h2>
                
                <!-- Booking Form -->
                <form id="bookingForm" class="space-y-6">
                    <!-- Doctor Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Doctor</label>
                        <select id="doctorSelect" class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2" required>
                            <option value="">Choose a doctor</option>
                            <option value="2">Dr. Sarah Johnson - Cardiologist</option>
                            <option value="3">Dr. Michael Chen - Pediatrician</option>
                            <option value="6">Dr. Emily Williams - Dermatologist</option>
                            <option value="8">Dr. James Martinez - Orthopedics</option>
                            <option value="9">Dr. Lisa Thompson - Neurologist</option>
                            <option value="10">Dr. Robert Kim - Psychiatrist</option>
                            <option value="11">Dr. Amanda Garcia - Gynecologist</option>
                            <option value="12">Dr. David Lee - Ophthalmologist</option>
                        </select>
                    </div>

                    <!-- Date Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Date</label>
                        <input type="date" id="appointmentDate" 
                               class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2"
                               required>
                    </div>

                    <!-- Time Slots -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                        <div id="timeSlots" class="grid grid-cols-3 gap-3">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Appointment Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Type</label>
                        <select id="appointmentType" class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2" required>
                            <option value="">Select type</option>
                            <option value="consultation">General Consultation</option>
                            <option value="followup">Follow-up Visit</option>
                            <option value="specialist">Specialist Consultation</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>

                    <!-- Reason for Visit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Visit</label>
                        <textarea id="visitReason" 
                                  class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2"
                                  rows="3"
                                  placeholder="Please describe your symptoms or reason for visit"
                                  required></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Book Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-auto">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-xl font-bold">DocNow</h3>
                    <p class="text-sm text-gray-400">Your Health, Our Priority</p>
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            <div class="mt-4 text-center text-sm text-gray-400">
                <p>&copy; 2024 DocNow. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Toggle mobile menu
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const isOpen = !menu.classList.contains('translate-x-full');
            
            if (isOpen) {
                menu.classList.add('translate-x-full');
            } else {
                menu.classList.remove('translate-x-full');
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobileMenu');
            const menuButton = document.getElementById('mobileMenuButton');
            
            if (!menu.contains(event.target) && !menuButton.contains(event.target) && !menu.classList.contains('translate-x-full')) {
                menu.classList.add('translate-x-full');
            }
        });

        // Toggle settings menu visibility
        function toggleMenu() {
            const menu = document.getElementById("slideMenu");
            if (menu.classList.contains("hidden")) {
                menu.classList.remove("hidden");
            } else {
                menu.classList.add("hidden");
            }
        }

        // Close settings menu
        function closeMenu() {
            const menu = document.getElementById("slideMenu");
            menu.classList.add("hidden");
        }

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdown = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');

            function toggleDropdown(event) {
                event.stopPropagation();
                userDropdownMenu.classList.toggle('show');
                userDropdown.classList.toggle('active');
            }

            function closeDropdown(event) {
                if (!userDropdown.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.remove('show');
                    userDropdown.classList.remove('active');
                }
            }

            userDropdown.addEventListener('click', toggleDropdown);
            document.addEventListener('click', closeDropdown);

            // Initialize booking functionality
            initializeBooking();
        });

        // Booking functionality
        function initializeBooking() {
            const dateInput = document.getElementById('appointmentDate');
            const doctorSelect = document.getElementById('doctorSelect');

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;

            // Handle date selection
            dateInput.addEventListener('change', function() {
                const selectedDate = this.value;
                const selectedDoctor = doctorSelect.value;
                if (selectedDoctor && selectedDate) {
                    loadTimeSlots(selectedDoctor, selectedDate);
                }
            });

            // Handle doctor selection
            doctorSelect.addEventListener('change', function() {
                const selectedDate = dateInput.value;
                const selectedDoctor = this.value;
                if (selectedDoctor && selectedDate) {
                    loadTimeSlots(selectedDoctor, selectedDate);
                }
            });

            // Handle form submission
            document.getElementById('bookingForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    doctor_id: doctorSelect.value,
                    appointment_date: dateInput.value,
                    appointment_time: document.querySelector('input[name="timeSlot"]:checked')?.value,
                    appointment_type: document.getElementById('appointmentType').value,
                    reason: document.getElementById('visitReason').value,
                    patient_id: '<?php echo $user_id; ?>'
                };

                // Validate form data
                if (!formData.doctor_id) {
                    alert('Please select a doctor');
                    return;
                }
                if (!formData.appointment_date) {
                    alert('Please select a date');
                    return;
                }
                if (!formData.appointment_time) {
                    alert('Please select a time slot');
                    return;
                }
                if (!formData.appointment_type) {
                    alert('Please select an appointment type');
                    return;
                }
                if (!formData.reason.trim()) {
                    alert('Please provide a reason for your visit');
                    return;
                }

                // For now, simulate a successful booking since we don't have the backend
                alert('Appointment booked successfully!');
                window.location.href = 'patient_dashboard.php';
            });
        }

        // Load available time slots (mock data for now)
        function loadTimeSlots(doctorId, date) {
            const timeSlotsContainer = document.getElementById('timeSlots');
            timeSlotsContainer.innerHTML = '<div class="col-span-3 text-center">Loading available times...</div>';

            // Simulate API call delay
            setTimeout(() => {
                // Generate mock time slots
                const mockTimeSlots = [
                    "09:00 AM", "09:30 AM", "10:00 AM", "10:30 AM",
                    "11:00 AM", "11:30 AM", "02:00 PM", "02:30 PM",
                    "03:00 PM", "03:30 PM", "04:00 PM", "04:30 PM"
                ];

                if (mockTimeSlots.length === 0) {
                    timeSlotsContainer.innerHTML = '<div class="col-span-3 text-center text-gray-500">No available time slots for this date</div>';
                    return;
                }

                timeSlotsContainer.innerHTML = mockTimeSlots.map(slot => `
                    <div class="relative">
                        <input type="radio" id="slot_${slot.replace(/\s/g, '_')}" name="timeSlot" value="${slot}"
                               class="hidden peer" required>
                        <label for="slot_${slot.replace(/\s/g, '_')}"
                               class="block text-center p-2 border border-gray-300 rounded-lg cursor-pointer
                                      peer-checked:bg-blue-500 peer-checked:text-white hover:bg-gray-50
                                      peer-checked:hover:bg-blue-600 transition-colors">
                            ${slot}
                        </label>
                    </div>
                `).join('');
            }, 500); // Simulate network delay
        }
    </script>
</body>
</html>
