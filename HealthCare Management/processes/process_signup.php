<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "docnow_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data and sanitize it
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // --- Validation ---
    $errors = [];
    if (empty($role) || empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email address already exists.";
    }
    $stmt->close();

    // If there are no validation errors, proceed with registration
    if (empty($errors)) {
        // Hash the password securely
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert user data into the users table
            $stmt = $conn->prepare("INSERT INTO users (role, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $role, $firstName, $lastName, $email, $hashedPassword);

            if (!$stmt->execute()) {
                throw new Exception("Error creating user account: " . $stmt->error);
            }

            $userId = $conn->insert_id;
            $stmt->close();

            // Insert role-specific data into doctors or patients table
            if ($role === 'doctor') {
                $stmt = $conn->prepare("INSERT INTO doctors (doctor_id) VALUES (?)");
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Error creating doctor profile: " . $stmt->error);
                }
            } elseif ($role === 'patient') {
                $stmt = $conn->prepare("INSERT INTO patients (patient_id) VALUES (?)");
                $stmt->bind_param("i", $userId);
                if (!$stmt->execute()) {
                    throw new Exception("Error creating patient profile: " . $stmt->error);
                }
            } else {
                throw new Exception("Invalid role selected.");
            }

            // If we got here, everything was successful
            $conn->commit();
            $_SESSION['success_message'] = "Account created successfully! You can now log in.";
            header("Location: ../auth/Login.php");
            exit();
            
        } catch (Exception $e) {
            // Something went wrong, rollback the transaction
            $conn->rollback();
            $_SESSION['error_message'] = "Error creating account: " . $e->getMessage();
            header("Location: ../auth/signup.php");
            exit();
        }
    } else {
        // If there are errors, store them in the session and redirect back to the signup form
        $_SESSION['errors'] = $errors;
        header("Location: ../auth/signup.php");
        exit();
    }
} else {
    // If the form was not submitted via POST
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Method Not Allowed";
    exit();
}

$conn->close();
?>