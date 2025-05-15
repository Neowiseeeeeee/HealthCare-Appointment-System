<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Specialty</label>
                        <select id="specialtyFilter" 
                                class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2">
                            <option value="">All Specialties</option>
                            <option value="Cardiologist">Cardiology</option>
                            <option value="Dermatologist">Dermatology</option>
                            <option value="Neurologist">Neurology</option>
                            <option value="Pediatrician">Pediatrics</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Psychiatrist">Psychiatry</option>
                            <option value="Gynecologist">Gynecology</option>
                            <option value="Ophthalmologist">Ophthalmology</option>
                        </select>
                    </div>

                    <!-- Availability Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                        <select id="availabilityFilter" 
                                class="w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2">
                            <option value="">Any Availability</option>
                            <option value="available">Available Now</option>
                            <option value="this-week">This Week</option>
                            <option value="next-week">Next Week</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctors Grid -->
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="doctorsGrid">
                <!-- Loading State -->
                <div class="col-span-full flex justify-center items-center py-12" id="loadingState">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
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
        // Mock doctor data
        const mockDoctors = [
            {
                id: 1,
                name: "Dr. Sarah Johnson",
                specialty: "Cardiologist",
                email: "sarah.johnson@docnow.com",
                contact_number: "+1 (555) 123-4567",
                experience: "15 years",
                availability_info: "Available for consultations Monday to Friday, 9:00 AM - 5:00 PM",
                image: "Pictures/default-profile.jpg"
            },
            {
                id: 2,
                name: "Dr. Michael Chen",
                specialty: "Pediatrician",
                email: "michael.chen@docnow.com",
                contact_number: "+1 (555) 234-5678",
                experience: "10 years",
                availability_info: "Available this week: Monday to Thursday, 8:00 AM - 4:00 PM",
                image: "Pictures/default-profile.jpg"
            },
            {
                id: 3,
                name: "Dr. Emily Williams",
                specialty: "Dermatologist",
                email: "emily.williams@docnow.com",
                contact_number: "+1 (555) 345-6789",
                experience: "12 years",
                availability_info: "Next week availability: Tuesday to Saturday, 10:00 AM - 6:00 PM",
                image: "Pictures/default-profile.jpg"
            }
        ];

        // Function to display doctors
        function displayDoctors(doctors) {
            const grid = document.getElementById('doctorsGrid');
            document.getElementById('loadingState').style.display = 'none';

            if (doctors.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 text-lg">No doctors found matching your criteria</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = doctors.map(doctor => `
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-center mb-4">
                            <img src="${doctor.image}" alt="${doctor.name}" 
                                 class="w-32 h-32 rounded-full object-cover"
                                 onerror="this.src='Pictures/default-profile.jpg'">
                        </div>
                        <h3 class="text-xl font-semibold text-center mb-2">${doctor.name}</h3>
                        <p class="text-blue-600 text-center mb-4">${doctor.specialty}</p>
                        <div class="space-y-2 mb-4">
                            <p class="text-gray-600 text-sm"><i class="fas fa-envelope mr-2"></i>${doctor.email}</p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-phone mr-2"></i>${doctor.contact_number}</p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-user-md mr-2"></i>${doctor.experience}</p>
                            <p class="text-gray-600 text-sm"><i class="fas fa-calendar-alt mr-2"></i>${doctor.availability_info}</p>
                        </div>
                        <div class="flex justify-center">
                            <a href="booking.php?doctor_id=${doctor.id}" 
                               class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                                Book Appointment
                            </a>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Function to apply filters
        function applyFilters() {
            const searchTerm = document.getElementById('searchDoctor').value.toLowerCase();
            const selectedSpecialty = document.getElementById('specialtyFilter').value;
            const selectedAvailability = document.getElementById('availabilityFilter').value.toLowerCase();

            let filteredDoctors = mockDoctors;

            if (searchTerm) {
                filteredDoctors = filteredDoctors.filter(doctor => 
                    doctor.name.toLowerCase().includes(searchTerm) ||
                    doctor.specialty.toLowerCase().includes(searchTerm)
                );
            }

            if (selectedSpecialty) {
                filteredDoctors = filteredDoctors.filter(doctor => 
                    doctor.specialty === selectedSpecialty
                );
            }

            if (selectedAvailability) {
                filteredDoctors = filteredDoctors.filter(doctor => {
                    const availInfo = doctor.availability_info.toLowerCase();
                    switch(selectedAvailability) {
                        case 'available':
                            return availInfo.includes('available');
                        case 'this-week':
                            return availInfo.includes('this week');
                        case 'next-week':
                            return availInfo.includes('next week');
                        default:
                            return true;
                    }
                });
            }

            displayDoctors(filteredDoctors);
        }

        // Initialize page
        window.addEventListener('load', () => {
            setTimeout(() => {
                displayDoctors(mockDoctors);
            }, 1000);
        });

        // Add event listeners for filters
        document.getElementById('searchDoctor').addEventListener('input', applyFilters);
        document.getElementById('specialtyFilter').addEventListener('change', applyFilters);
        document.getElementById('availabilityFilter').addEventListener('change', applyFilters);

        // Mobile menu functionality
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

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdown = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');

            if (userDropdown && userDropdownMenu) {
                userDropdown.addEventListener('click', function(event) {
                    event.stopPropagation();
                    userDropdownMenu.classList.toggle('show');
                    this.classList.toggle('active');
                });

                document.addEventListener('click', function(event) {
                    if (!userDropdown.contains(event.target)) {
                        userDropdownMenu.classList.remove('show');
                        userDropdown.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>
</html> 