<?php
// This script returns the contents of the appointment reminders log file
header('Content-Type: text/plain');

$logFile = '../logs/appointment_reminders.log';
if (file_exists($logFile)) {
    readfile($logFile);
} else {
    echo 'No logs found.';
}
?>
