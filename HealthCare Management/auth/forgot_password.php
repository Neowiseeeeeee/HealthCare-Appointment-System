<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #3b82f6;
        }
        .nav-link {
            color: #ffffff;
            transition: color 0.3s ease, background-color 0.3s ease;
            padding: 8px 16px;
            border-radius: 0.375rem;
            white-space: nowrap;
        }
        .nav-link:hover, .nav-link.active {
            color: #ffffff;
            background-color: #4338ca;
        }
        .nav-link.active {
            font-weight: 600;
        }
        #mobile-menu {
            display: none;
        }
        @media (max-width: 767px) {
            .md\:hidden {
                display: block !important;
            }
            .md\:block {
                display: none !important;
            }
            .flex-space-x-6 {
                space-x: 0 !important;
            }
            .nav-links-container {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            .nav-link {
                width: 100%;
                text-align: left;
                padding: 12px 16px;
            }
            #mobile-menu {
                width: 100%;
                position: fixed;
                top: 0;
                right: 0;
                height: 100vh;
                background-color: #3b82f6;
                z-index: 20;
                transform: translateX(100%);
                transition: transform 0.3s ease-in-out;
                overflow-y: auto;
            }
            #mobile-menu.active {
                transform: translateX(0);
            }
            #mobile-menu-close {
                position: absolute;
                top: 16px;
                left: 16px;
                color: white;
                font-size: 24px;
                cursor: pointer;
            }
            .mobile-menu-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem;
                border-bottom: 1px solid #60a5fa;
            }
        }
        .nav-links-container {
            display: flex;
            align-items: center;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="bg-blue-500 shadow-md rounded-b-lg sticky-header">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center flex-wrap">
            <a href="../index.php" class="flex items-center text-white text-xl font-semibold mr-4">
                <img src="../assets/images/logo.jpg" alt="DocNow Logo" class="mr-2 rounded-md" width="40" height="40">
                DocNow
            </a>
            <nav class="hidden md:block">
                <ul class="flex space-x-6 nav-links-container">
                    <li><a href="../index.php" class="nav-link">Home</a></li>
                    <li><a href="#" id="doctors-link" class="nav-link">Doctors</a></li>
                    <li><a href="#" id="booking-link" class="nav-link">Booking</a></li>
                    <li><a href="../index.php#contact" class="nav-link">Contacts</a></li>
                    <li><a href="../index.php#about" class="nav-link">About</a></li>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                </ul>
            </nav>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-gray-200 hover:text-white focus:outline-none focus:shadow-outline w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div id="mobile-menu" class="hidden nav-links-container">
                    <div class="mobile-menu-header">
                        <h3 class="text-white text-xl font-semibold">Menu</h3>
                        <span id="mobile-menu-close">&times;</span>
                    </div>
                    <a href="../index.php" class="block text-gray-200 hover:text-white transition-colors duration-300">Home</a>
                    <a href="#" id="mobile-doctors-link" class="block text-gray-200 hover:text-white transition-colors duration-300">Doctors</a>
                    <a href="#" id="mobile-booking-link" class="block text-gray-200 hover:text-white transition-colors duration-300">Booking</a>
                    <a href="../index.php#contact" class="block text-gray-200 hover:text-white transition-colors duration-300">Contacts</a>
                    <a href="../index.php#about" class="block text-gray-200 hover:text-white transition-colors duration-300">About</a>
                    <a href="login.php" class="block text-white hover:bg-blue-600 px-4 py-2 rounded-md transition-colors duration-300">Login</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex justify-center">
        <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-semibold text-blue-600 mb-6 text-center">Reset Your Password</h2>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $_SESSION['error_message']; ?></p>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $_SESSION['success_message']; ?></p>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6 text-center text-gray-600">
                <p>Enter your email address and new password to reset your account.</p>
            </div>

            <form id="resetForm" action="process_reset_password.php" method="POST" class="space-y-4">
                <div class="form-group">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address:</label>
                    <input type="email" id="email" name="email" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Enter your registered email">
                </div>
                <div class="form-group">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                    <input type="password" id="password" name="password" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Enter your new password">
                </div>
                <div class="form-group">
                    <label for="confirmPassword" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Confirm your new password">
                    <p id="passwordMatch" class="text-red-500 text-sm mt-1" style="display: none;">Passwords do not match.</p>
                </div>
                <button type="submit" 
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition-colors">
                    Reset Password
                </button>
            </form>
            <div class="text-center mt-4 text-sm">
                <a href="login.php" class="text-blue-500 hover:text-blue-700">Back to Login</a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 rounded-t-lg shadow-md mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>© 2023 DocNow. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const doctorsLink = document.getElementById('doctors-link');
        const bookingLink = document.getElementById('booking-link');
        const mobileDoctorsLink = document.getElementById('mobile-doctors-link');
        const mobileBookingLink = document.getElementById('mobile-booking-link');

        function redirectToLogin() {
            alert('Please log in to access this feature.');
            window.location.href = 'login.php';
        }

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
            });
        }

        if(mobileMenuClose){
            mobileMenuClose.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
            });
        }

        if (doctorsLink) {
            doctorsLink.addEventListener('click', redirectToLogin);
        }
        if (bookingLink) {
            bookingLink.addEventListener('click', redirectToLogin);
        }

        if (mobileDoctorsLink) {
            mobileDoctorsLink.addEventListener('click', redirectToLogin);
        }
        if (mobileBookingLink) {
            mobileBookingLink.addEventListener('click', redirectToLogin);
        }

        // Add password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMatchError = document.getElementById('passwordMatch');
        const resetForm = document.getElementById('resetForm');

        function checkPasswordMatch() {
            if (passwordInput.value !== confirmPasswordInput.value) {
                passwordMatchError.style.display = 'block';
                return false;
            } else {
                passwordMatchError.style.display = 'none';
                return true;
            }
        }

        confirmPasswordInput.addEventListener('keyup', checkPasswordMatch);

        resetForm.addEventListener('submit', function(event) {
            if (!checkPasswordMatch()) {
                event.preventDefault();
                alert('Passwords do not match. Please correct.');
            }
        });
    </script>
</body>
</html> 