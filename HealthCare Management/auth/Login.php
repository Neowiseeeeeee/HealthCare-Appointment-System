<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Your Healthcare, Simplified</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex; /* Enable flexbox for the body */
            flex-direction: column; /* Stack items vertically */
            min-height: 100vh; /* Ensure body is at least the height of the viewport */
        }
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #3b82f6;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 10px -2px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease-in-out;
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
                position: fixed; /*make it fixed*/
                top: 0;
                right: 0;
                height: 100vh;
                background-color: #3b82f6;
                z-index: 20;
                transform: translateX(100%); /*start off screen*/
                transition: transform 0.3s ease-in-out; /*add smooth transition*/
                overflow-y: auto; /*in case of many links*/
            }
            #mobile-menu.active {
                transform: translateX(0); /*slide in*/
            }
            #mobile-menu-close {
                position: absolute;
                top: 16px;
                left: 16px;
                color: white;
                font-size: 24px;
                cursor: pointer;
            }
            .mobile-menu-header{
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
            flex: 1; /* Allow main to take up available space */
        }
        footer {
            margin-top: auto; /* Push footer to the bottom */
        }
        #about, #contact {
            scroll-margin-top: 80px; /* Add scroll margin to handle sticky header */
            padding-top: 20px;
        }

    </style>
</head>
<body class="bg-gray-100">
    <?php
    session_start();
    ?>
    <header class="bg-blue-500 shadow-md rounded-b-lg sticky-header">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center flex-wrap">
            <a href="#" class="flex items-center text-white text-xl font-semibold mr-4">
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
                    <li><a href="Login.php" class="nav-link">Login</a></li>
                </ul>
            </nav>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-gray-200 hover:text-white focus:outline-none focus:shadow-outline w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div id="mobile-menu" class="hidden  nav-links-container">
                    <div class="mobile-menu-header">
                        <h3 class="text-white text-xl font-semibold">Menu</h3>
                        <span id="mobile-menu-close" >&times;</span>
                    </div>

                    <a href="../index.php" class="block text-gray-200 hover:text-white transition-colors duration-300">Home</a>
                    <a href="#" id="mobile-doctors-link" class="block text-gray-200 hover:text-white transition-colors duration-300">Doctors</a>
                    <a href="#" id="mobile-booking-link" class="block text-gray-200 hover:text-white transition-colors duration-300">Booking</a>
                    <a href="../index.php#contact" class="block text-gray-200 hover:text-white transition-colors duration-300">Contacts</a>
                    <a href="../index.php#about" class="block text-gray-200 hover:text-white transition-colors duration-300">About</a>
                    <a href="Login.php" class="block text-white hover:bg-blue-600 px-4 py-2 rounded-md transition-colors duration-300">Login</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex flex-col items-center">
        <div class="text-center mb-8">
            <img src="../assets/images/logo.jpg" alt="DocNow Logo" class="rounded-md mx-auto mb-4" width="100" height="100">
            <h1 class="text-3xl font-semibold text-blue-700">DocNow</h1>
        </div>

        <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Login</h2>
            
            <?php if (isset($_SESSION['errors'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <?php 
                    foreach ($_SESSION['errors'] as $error) {
                        echo "<p>$error</p>";
                    }
                    unset($_SESSION['errors']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $_SESSION['error_message']; ?></p>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form class="space-y-4" action="../processes/process_login.php" method="POST">
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Login
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="forgot_password.php" class="inline-block text-sm text-blue-500 hover:text-blue-800">Forgot Password?</a>
            </div>
            <div class="mt-4 text-center border-t border-gray-200 pt-4">
                <p class="text-gray-700 text-sm">Don't have an account? <a href="signup.php" class="text-blue-500 hover:text-blue-800 font-semibold">Register</a></p>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 rounded-t-lg shadow-md">
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
        const navLinks = document.querySelectorAll('.nav-link');
        const currentPage = window.location.pathname;


        function redirectToLogin() {
            alert('Please log in to access this feature.');
            window.location.href = '../index.php';
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

        navLinks.forEach(link => {
            let href = link.getAttribute('href');
             if (href === "#") {
                href = "/";
            }
            // Reset all active states first
            navLinks.forEach(navLink => navLink.classList.remove('active'));

            // Determine active link based on current page
            let activeFound = false; // Add a flag
             if (currentPage === "/") {
                document.querySelector('a[href="index.php"]').classList.add('active');
                activeFound = true;
            } else if (href === "#about") {
                document.querySelector('a[href="#about"]').classList.add('active');
                activeFound = true;
            } else if (href === "#contact") {
                document.querySelector('a[href="#contact"]').classList.add('active');
                activeFound = true;
            }  else if (href === "Login.php") {
                document.querySelector('a[href="Login.php"]').classList.add('active');
                activeFound = true;
            }

            if (!activeFound) {
                 document.querySelector('a[href="index.php"]').classList.add('active');
            }
        });

    </script>
</body>
</html>
