<?php
session_start();

// Check if token is provided
if (!isset($_GET['token'])) {
    header("Location: Login.php");
    exit();
}

$token = $_GET['token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocNow - Set New Password</title>
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
    <header class="bg-blue-600 shadow-md">
        <nav class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <a href="index.php" class="flex items-center text-white text-xl font-semibold">
                    <img src="../Pictures/Logo.jpg" alt="DocNow Logo" class="h-8 w-8 mr-2 rounded">
                    DocNow
                </a>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4 py-8 flex justify-center">
        <div class="bg-white rounded-lg shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-semibold text-blue-600 mb-6 text-center">Set New Password</h2>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $_SESSION['error_message']; ?></p>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6 text-center text-gray-600">
                <p>Please enter your new password below.</p>
            </div>

            <form id="resetPasswordForm" action="process_new_password.php" method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
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
                    Set New Password
                </button>
            </form>
            <div class="text-center mt-4 text-sm">
                <a href="Login.php" class="text-blue-500 hover:text-blue-700">Back to Login</a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 rounded-t-lg shadow-md mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>© 2023 DocNow. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMatchError = document.getElementById('passwordMatch');
        const resetPasswordForm = document.getElementById('resetPasswordForm');

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

        resetPasswordForm.addEventListener('submit', function(event) {
            if (!checkPasswordMatch()) {
                event.preventDefault();
                alert('Passwords do not match. Please correct.');
            }
        });
    </script>
</body>
</html> 