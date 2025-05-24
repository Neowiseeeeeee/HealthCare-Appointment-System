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
    $_SESSION['error_message'] = "Connection failed: " . $conn->connect_error;
    header("Location: ../auth/Login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validation
    $errors = [];
    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        // First, get user details
        $stmt = $conn->prepare("SELECT u.user_id, u.role, u.password, u.first_name, u.last_name FROM users u WHERE u.email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['email'] = $email;
                $_SESSION['login_success'] = true;

                // If user is a patient, get their patient_id
                if ($user['role'] === 'patient') {
                    $patient_stmt = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
                    $patient_stmt->bind_param("i", $user['user_id']);
                    $patient_stmt->execute();
                    $patient_result = $patient_stmt->get_result();
                    
                    if ($patient_result->num_rows == 1) {
                        $patient = $patient_result->fetch_assoc();
                        $_SESSION['patient_id'] = $patient['patient_id'];
                    }
                    $patient_stmt->close();
                }

                // Close the first statement
                $stmt->close();

                // Redirect based on role with absolute paths
                switch ($user['role']) {
                    case 'patient':
                        header("Location: ../patient_dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: ../doctor_dashboard.php");
                        break;
                    case 'admin':
                        header("Location: ../admin_dashboard.php");
                        break;
                    default:
                        $_SESSION['error_message'] = "Invalid user role.";
                        header("Location: ../auth/Login.php");
                }
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header("Location: ../auth/Login.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        header("Location: ../auth/Login.php");
        exit();
    }
}

$conn->close();
?>
