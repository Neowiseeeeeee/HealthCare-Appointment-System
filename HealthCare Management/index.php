<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Your Healthcare, Simplified</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-link {
            color: white;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
            border-radius: 0.5rem;
        }
        .nav-link:hover {
            color: #1e40af;
            background-color: white;
        }
        .nav-button {
            background-color: white;
            color: #3b82f6;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-button:hover {
            background-color: #1e40af;
            color: white;
            transform: translateY(-1px);
        }
        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('assets/images/hero.jpg');
            background-size: cover;
            background-position: center 30%;
            background-repeat: no-repeat;
            min-height: 400px;
            height: 50vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .hero-section {
                min-height: 300px;
                height: 40vh;
                background-position: center 25%;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <header class="bg-blue-500 shadow-md rounded-b-lg sticky-header">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center flex-wrap">
            <a href="#" class="flex items-center text-white text-xl font-semibold mr-4">
                <img src="assets/images/logo.jpg" alt="DocNow Logo" class="mr-2 rounded-full h-10 w-10">
                DocNow
            </a>
            
            <nav class="hidden md:block">
                <ul class="flex space-x-4">
                    <li><a href="#" class="nav-link">Home</a></li>
                    <li><a href="auth/login.php" class="nav-link">Doctors</a></li>
                    <li><a href="auth/login.php" class="nav-link">Booking</a></li>
                    <li><a href="#contact" class="nav-link">Contacts</a></li>
                    <li><a href="#about" class="nav-link">About</a></li>
                </ul>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="auth/login.php" class="nav-button">Login</a>
                <button id="mobile-menu-button" class="md:hidden text-white hover:text-blue-100 transition-colors">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden bg-blue-500 w-full absolute z-50">
        <div class="container mx-auto px-4 py-3">
            <ul class="space-y-2">
                <li><a href="#" class="nav-link block">Home</a></li>
                <li><a href="auth/login.php" class="nav-link block">Doctors</a></li>
                <li><a href="auth/login.php" class="nav-link block">Booking</a></li>
                <li><a href="#contact" class="nav-link block">Contacts</a></li>
                <li><a href="#about" class="nav-link block">About</a></li>
            </ul>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container mx-auto px-4 text-center relative z-10 text-white">
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4">Your Health, Our Priority</h1>
            <p class="text-base md:text-lg mb-6 max-w-2xl mx-auto">Experience healthcare that puts you first with DocNow's innovative appointment system</p>
            <a href="auth/signup.php" class="bg-blue-500 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-600 transition duration-300 inline-block">Get Started</a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Why Choose DocNow?</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center p-8 bg-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 border border-gray-100">
                    <div class="text-blue-500 text-4xl mb-6 bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">24/7 Availability</h3>
                    <p class="text-gray-600">Book appointments anytime, anywhere with our easy-to-use platform</p>
                </div>
                <div class="text-center p-8 bg-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 border border-gray-100">
                    <div class="text-blue-500 text-4xl mb-6 bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">Expert Doctors</h3>
                    <p class="text-gray-600">Access to a network of qualified and experienced healthcare professionals</p>
                </div>
                <div class="text-center p-8 bg-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 border border-gray-100">
                    <div class="text-blue-500 text-4xl mb-6 bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">Easy Management</h3>
                    <p class="text-gray-600">Manage your appointments and medical history with just a few clicks</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold mb-6">About Our Facility</h2>
                    <p class="text-gray-600 mb-6">At DocNow, we combine cutting-edge technology with compassionate care to provide you with the best healthcare experience. Our state-of-the-art facilities and dedicated team of professionals ensure that you receive the highest quality of medical care.</p>
                    <ul class="space-y-4">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                            <span>Modern Medical Equipment</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                            <span>Experienced Medical Staff</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-blue-500 mr-3"></i>
                            <span>Comfortable Environment</span>
                        </li>
                    </ul>
                </div>
                <div class="rounded-lg overflow-hidden shadow-xl">
                    <img src="assets/images/facility.jpg" alt="Our Facility" class="w-full h-auto">
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Contact Us</h2>
            <div class="grid md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-xl font-semibold mb-4">Get in Touch</h3>
                    <p class="text-gray-600 mb-6">Have questions? We're here to help. Send us a message and we'll respond as soon as possible.</p>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-blue-500 w-6"></i>
                            <span class="ml-3">123 Healthcare Ave, Medical City, MC 12345</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-blue-500 w-6"></i>
                            <span class="ml-3">+1 (555) 123-4567</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-blue-500 w-6"></i>
                            <span class="ml-3">contact@docnow.com</span>
                        </div>
                    </div>
                </div>
                <div>
                    <form id="contactForm" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" id="name" name="name" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea id="message" name="message" rows="4" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <div id="formMessage" class="hidden rounded-lg p-4 mb-4 text-sm"></div>
                        <button type="submit" class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-600 transition duration-300">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-semibold mb-4">DocNow</h3>
                    <p class="text-gray-400">Your trusted healthcare partner, making medical appointments simple and efficient.</p>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white transition-colors">Features</a></li>
                        <li><a href="#doctors" class="text-gray-400 hover:text-white transition-colors">Doctors</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition-colors">About</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                        <li><a href="auth/login.php" class="text-gray-400 hover:text-white transition-colors">Login</a></li>
                        <li><a href="auth/signup.php" class="text-gray-400 hover:text-white transition-colors">Sign Up</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-4">Follow Us</h3>
                    <div class="flex space-x-4">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition-colors" title="Follow us on Facebook">
                            <i class="fab fa-facebook fa-lg"></i>
                        </a>
                        <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition-colors" title="Follow us on Twitter">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition-colors" title="Follow us on Instagram">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition-colors" title="Follow us on LinkedIn">
                            <i class="fab fa-linkedin fa-lg"></i>
                        </a>
                    </div>
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold mb-2">Contact Info</h4>
                        <div class="text-gray-400 space-y-2">
                            <p><i class="fas fa-phone mr-2"></i>+1 (555) 123-4567</p>
                            <p><i class="fas fa-envelope mr-2"></i>contact@docnow.com</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p class="text-gray-400">&copy; 2024 DocNow. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
        });

        // Contact form handling
        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('formMessage');
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Disable form fields and button during submission
            const formFields = this.querySelectorAll('input, textarea');
            formFields.forEach(field => field.disabled = true);
            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';
            
            try {
                const response = await fetch('processes/process_contact.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                // Show message
                messageDiv.classList.remove('hidden');
                if (result.status === 'success') {
                    messageDiv.className = 'rounded-lg p-4 mb-4 text-sm bg-green-100 text-green-700';
                    this.reset(); // Clear form on success
                } else {
                    messageDiv.className = 'rounded-lg p-4 mb-4 text-sm bg-red-100 text-red-700';
                }
                messageDiv.textContent = result.message;
                
                // Scroll message into view
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (error) {
                console.error('Error:', error);
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'rounded-lg p-4 mb-4 text-sm bg-red-100 text-red-700';
                messageDiv.textContent = 'An error occurred while sending your message. Please try again later.';
            } finally {
                // Re-enable form fields and button
                formFields.forEach(field => field.disabled = false);
                submitButton.disabled = false;
                submitButton.textContent = 'Send Message';
            }
        });
    </script>
</body>
</html> 