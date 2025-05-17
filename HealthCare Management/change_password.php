<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Change Password</title>
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
        main {
            flex: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="bg-blue-500 shadow-md rounded-b-lg sticky-header">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="#" class="flex items-center text-white text-xl font-semibold">
                <img src="assets/images/logo.jpg" alt="DocNow Logo" class="mr-2 rounded-md" width="40" height="40">
                DocNow
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex justify-center">
        <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-semibold text-blue-600 mb-6 text-center">Change Password</h2>
            
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

            <form id="changePasswordForm" action="processes/process_change_password.php" method="POST" class="space-y-4">
                <div class="form-group">
                    <label for="currentPassword" class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                    <input type="password" id="currentPassword" name="currentPassword" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Enter your current password">
                </div>
                <div class="form-group">
                    <label for="newPassword" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                    <input type="password" id="newPassword" name="newPassword" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Enter your new password">
                </div>
                <div class="form-group">
                    <label for="confirmPassword" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                        placeholder="Confirm your new password">
                    <p id="passwordMatch" class="text-red-500 text-sm mt-1" style="display: none;">Passwords do not match.</p>
                </div>
                <button type="submit" 
                    class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition-colors">
                    Change Password
                </button>
            </form>
            <div class="text-center mt-4 text-sm">
                <a href="<?php echo $_SESSION['role'] === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'; ?>" 
                   class="text-blue-500 hover:text-blue-700">Back to Dashboard</a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 rounded-t-lg shadow-md mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>© 2023 DocNow. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMatchError = document.getElementById('passwordMatch');
        const changePasswordForm = document.getElementById('changePasswordForm');

        function checkPasswordMatch() {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                passwordMatchError.style.display = 'block';
                return false;
            } else {
                passwordMatchError.style.display = 'none';
                return true;
            }
        }

        confirmPasswordInput.addEventListener('keyup', checkPasswordMatch);

        changePasswordForm.addEventListener('submit', function(event) {
            if (!checkPasswordMatch()) {
                event.preventDefault();
                alert('Passwords do not match. Please correct.');
            }
        });
    </script>
</body>
</html> 