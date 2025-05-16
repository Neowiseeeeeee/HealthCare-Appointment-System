<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
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

// Fetch doctors from the database
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, d.specialty, d.contact_number, d.availability_info, d.experience, d.profile_picture
        FROM users u
        JOIN doctors d ON u.user_id = d.doctor_id
        WHERE u.role = 'doctor'
        ORDER BY u.last_name, u.first_name";

$result = $conn->query($sql);
$doctors = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Our Doctors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        /* Navbar and Dropdown Styles */
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

        /* Mobile Menu Styles */
        #mobileMenu {
            transition: transform 0.3s ease-in-out;
        }

        .translate-x-full {
            transform: translateX(100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Navbar -->
    <header class="bg-blue-600 shadow-md">
        <nav class="w-full flex items-center justify-between h-16">
            <div class="logo-section">
                <img src="Pictures/Logo.jpg" alt="DocNow Logo" class="h-8 w-8 rounded ml-4">
                <span class="text-white font-semibold text-base select-none">DocNow</span>
            </div>
            <div class="nav-links">
                <a href="patient_dashboard.php" class="nav-link">Dashboard</a>
                <a href="doctors.php" class="nav-link active">Doctors</a>
                <a href="booking.php" class="nav-link">Booking</a>
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
                <a href="doctors.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors bg-blue-900">Doctors</a>
                <a href="booking.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Booking</a>
                <hr class="border-blue-700">
                <a href="change_password.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Change Password</a>
                <a href="auth/logout.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Logout</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <!-- Search and Filter Section -->
        <div class="max-w-6xl mx-auto mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Search Bar -->
                    <div class="col-span-1 md:col-span-2 lg:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Doctors</label>
                        <div class="relative">
                            <input type="text" id="searchDoctor" 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Search by name or specialty">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Specialty Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Specialty</label>
                        <select id="specialtyFilter" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Specialties</option>
                            <?php
                            // Create an array of unique specialties
                            $specialties = [];
                            foreach ($doctors as $doctor) {
                                if (!empty($doctor['specialty']) && !in_array($doctor['specialty'], $specialties)) {
                                    $specialties[] = $doctor['specialty'];
                                }
                            }
                            sort($specialties);
                            
                            // Output specialty options
                            foreach ($specialties as $specialty) {
                                echo '<option value="' . htmlspecialchars($specialty) . '">' . htmlspecialchars($specialty) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctors Grid -->
        <div class="max-w-6xl mx-auto">
            <h2 class="text-2xl font-bold mb-6">Our Doctors</h2>
            
            <?php if (empty($doctors)): ?>
                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                    <p class="text-gray-600">No doctors found. Please check back later.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="doctorsGrid">
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300" 
                             data-specialty="<?php echo htmlspecialchars($doctor['specialty'] ?? ''); ?>">
                            <div class="p-6">
                                <div class="flex items-center mb-4">
                                    <?php if (!empty($doctor['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($doctor['profile_picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>" 
                                             class="w-16 h-16 rounded-full object-cover mr-4">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                            <span class="text-blue-600 text-xl font-semibold">
                                                <?php echo strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                        </h3>
                                        <?php if (!empty($doctor['specialty'])): ?>
                                            <p class="text-blue-600 font-medium"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                                        <?php else: ?>
                                            <p class="text-gray-400 italic">Specialty not specified</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <?php if (!empty($doctor['experience'])): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-briefcase mr-2 text-blue-500"></i>
                                            <?php echo htmlspecialchars($doctor['experience']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($doctor['contact_number'])): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-phone-alt mr-2 text-blue-500"></i>
                                            <?php echo htmlspecialchars($doctor['contact_number']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($doctor['email'])): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-envelope mr-2 text-blue-500"></i>
                                            <?php echo htmlspecialchars($doctor['email']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($doctor['availability_info'])): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-calendar-alt mr-2 text-blue-500"></i>
                                            <?php echo htmlspecialchars($doctor['availability_info']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="booking.php?doctor_id=<?php echo $doctor['user_id']; ?>" 
                                   class="block w-full bg-blue-500 hover:bg-blue-600 text-white text-center py-2 rounded-md transition duration-300">
                                    Book Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
        
        // Search functionality
        document.getElementById('searchDoctor').addEventListener('input', filterDoctors);
        document.getElementById('specialtyFilter').addEventListener('change', filterDoctors);
        
        function filterDoctors() {
            const searchTerm = document.getElementById('searchDoctor').value.toLowerCase();
            const specialtyFilter = document.getElementById('specialtyFilter').value.toLowerCase();
            const doctorCards = document.querySelectorAll('.doctor-card');
            
            doctorCards.forEach(card => {
                const doctorName = card.querySelector('h3').textContent.toLowerCase();
                const doctorSpecialty = card.dataset.specialty.toLowerCase();
                
                const matchesSearch = doctorName.includes(searchTerm) || 
                                      (doctorSpecialty && doctorSpecialty.includes(searchTerm));
                                      
                const matchesSpecialty = specialtyFilter === '' || 
                                         (doctorSpecialty && doctorSpecialty === specialtyFilter);
                
                if (matchesSearch && matchesSpecialty) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Check if no results and display message
            const visibleCards = document.querySelectorAll('.doctor-card[style="display: block"]');
            const doctorsGrid = document.getElementById('doctorsGrid');
            const noResultsMessage = document.getElementById('noResultsMessage');
            
            if (visibleCards.length === 0) {
                if (!noResultsMessage) {
                    const message = document.createElement('div');
                    message.id = 'noResultsMessage';
                    message.className = 'col-span-full p-4 text-center text-gray-500';
                    message.textContent = 'No doctors found matching your search criteria.';
                    doctorsGrid.appendChild(message);
                }
            } else {
                if (noResultsMessage) {
                    noResultsMessage.remove();
                }
            }
        }
    </script>
</body>
</html>