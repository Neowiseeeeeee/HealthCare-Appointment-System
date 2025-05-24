<?php
// This is an admin script to manually send appointment reminders
session_start();

// Check if user is admin (you might want to add proper authentication)
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}
*/

// Include the appointment reminder script
require_once '../api/check_upcoming_appointments.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Appointment Reminders</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Appointment Reminders</h1>
        
        <div id="result" class="mb-6 p-4 rounded hidden">
            <!-- Results will be shown here -->
        </div>
        
        <button 
            id="sendRemindersBtn" 
            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
            onclick="sendReminders()">
            Send Appointment Reminders
        </button>
        
        <div class="mt-6">
            <h2 class="text-lg font-semibold mb-2">Logs</h2>
            <div class="bg-gray-800 text-green-400 p-4 rounded font-mono text-sm overflow-auto max-h-64">
                <pre id="logOutput"><?php 
                    $logFile = '../logs/appointment_reminders.log';
                    echo file_exists($logFile) ? htmlspecialchars(file_get_contents($logFile)) : 'No logs found.'; 
                ?></pre>
            </div>
        </div>
    </div>
    
    <script>
    function sendReminders() {
        const btn = document.getElementById('sendRemindersBtn');
        const resultDiv = document.getElementById('result');
        const logOutput = document.getElementById('logOutput');
        
        btn.disabled = true;
        btn.innerHTML = 'Sending...';
        resultDiv.className = 'mb-6 p-4 bg-blue-100 text-blue-800 rounded hidden';
        resultDiv.textContent = '';
        
        fetch('../api/check_upcoming_appointments.php')
            .then(response => response.json())
            .then(data => {
                resultDiv.className = 'mb-6 p-4 bg-green-100 text-green-800 rounded';
                resultDiv.textContent = data.message || 'Reminders sent successfully';
                
                // Reload logs
                return fetch('get_logs.php');
            })
            .then(response => response.text())
            .then(logs => {
                logOutput.textContent = logs;
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.className = 'mb-6 p-4 bg-red-100 text-red-800 rounded';
                resultDiv.textContent = 'Error sending reminders: ' + error.message;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = 'Send Appointment Reminders';
            });
    }
    
    // Auto-scroll log output to bottom
    document.addEventListener('DOMContentLoaded', () => {
        const logOutput = document.getElementById('logOutput');
        logOutput.scrollTop = logOutput.scrollHeight;
    });
    </script>
</body>
</html>
