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
    echo "Form submitted via POST.<br>";
    echo "Database connection successful.<br>";
    echo "Form data retrieved.<br>";

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
    echo "Email existence check performed.<br>";

    // If there are no validation errors, proceed with registration
    if (empty($errors)) {
        // Hash the password securely
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        echo "Password hashed.<br>";

        // Insert user data into the users table
        $stmt = $conn->prepare("INSERT INTO users (role, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $role, $firstName, $lastName, $email, $hashedPassword);

        if ($stmt->execute()) {
            $userId = $conn->insert_id; // Get the ID of the newly inserted user
            echo "User data inserted into users table (ID: " . $userId . ").<br>";

            // Insert role-specific data into doctors or patients table
            if ($role === 'doctor') {
                $stmt_doctor = $conn->prepare("INSERT INTO doctors (doctor_id) VALUES (?)");
                $stmt_doctor->bind_param("i", $userId);
                if ($stmt_doctor->execute()) {
                    echo "Doctor data inserted.<br>";
                    $_SESSION['success_message'] = "Account created successfully! You can now log in.";
                    header("Location: ../auth/Login.php");
                    exit();
                } else {
                    echo "Error inserting doctor data: " . $stmt_doctor->error . "<br>";
                }
                $stmt_doctor->close();
            } elseif ($role === 'patient') {
                $stmt_patient = $conn->prepare("INSERT INTO patients (patient_id) VALUES (?)");
                $stmt_patient->bind_param("i", $userId);
                if ($stmt_patient->execute()) {
                    echo "Patient data inserted.<br>";
                    $_SESSION['success_message'] = "Account created successfully! You can now log in.";
                    header("Location: ../auth/Login.php");
                    exit();
                } else {
                    echo "Error inserting patient data: " . $stmt_patient->error . "<br>";
                }
                $stmt_patient->close();
            } else {
                // Should not happen due to the dropdown, but handle just in case
                $_SESSION['error_message'] = "Invalid role selected.";
                header("Location: ../auth/signup.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Error creating account: " . $stmt->error;
            header("Location: ../auth/signup.php");
            exit();
        }

        $stmt->close();
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