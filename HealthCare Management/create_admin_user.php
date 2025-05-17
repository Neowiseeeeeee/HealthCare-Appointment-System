<?php
$plainTextPassword = "chaelvin"; // Change this to your desired password
$hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

echo "Hashed Password: " . $hashedPassword . "<br>";
echo "SQL Insert Statement (to copy and paste into your database tool):<br><br>";
echo "INSERT INTO Users (role, first_name, last_name, email, password) VALUES (";
echo "'admin', "; // Role
echo "'Admin', ";   // First Name
echo "'User', ";    // Last Name
echo "'admin@docnow.com', "; // Change to a unique email address
echo "'" . $hashedPassword . "');"; // The hashed password
?>
