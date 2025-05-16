<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session data
error_log("Session data in dashboard: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header('Location: auth/login.php');
    exit();
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Name not available';
$user_email = $_SESSION['email'] ?? 'Email not available';
$user_role = $_SESSION['role'] ?? 'patient';

// Debug output
error_log("User data from session:");
error_log("User ID: " . $user_id);
error_log("Name: " . $user_name);
error_log("Email: " . $user_email);
error_log("Role: " . $user_role);

// Database connection for appointments
$conn = new mysqli("localhost", "root", "", "docnow_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch upcoming appointments
$upcomingQuery = "SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, d.specialty 
                 FROM appointments a
                 JOIN users u ON a.doctor_id = u.user_id
                 LEFT JOIN doctors d ON u.user_id = d.doctor_id
                 WHERE a.patient_id = ? 
                   AND a.appointment_datetime >= NOW()
                   AND a.status NOT IN ('cancelled', 'completed')
                 ORDER BY a.appointment_datetime ASC
                 LIMIT 5";
$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->bind_param("i", $user_id);
$upcomingStmt->execute();
$upcomingAppointments = $upcomingStmt->get_result();
$upcomingStmt->close();

// Fetch past appointments (appointment history) - now includes both completed and cancelled
$historyQuery = "SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, d.specialty 
                FROM appointments a
                JOIN users u ON a.doctor_id = u.user_id
                LEFT JOIN doctors d ON u.user_id = d.doctor_id
                WHERE a.patient_id = ? 
                  AND (a.appointment_datetime < NOW() OR a.status IN ('cancelled', 'completed'))
                ORDER BY a.appointment_datetime DESC
                LIMIT 5";
$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("i", $user_id);
$historyStmt->execute();
$appointmentHistory = $historyStmt->get_result();
$historyStmt->close();

// Close database connection
$conn->close();

?>



<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>DocNow Patient Profile Editable</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
   href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
   rel="stylesheet"
  />
  <script>
        // Toggle between edit and view mode
        function toggleEdit() {
            const editBtn = document.getElementById("editProfileBtn");
            const isEditing = editBtn.dataset.editing === "true";
            const container = document.getElementById("profileContainer");

            if (!isEditing) {
            // Enable editing: replace spans with inputs/selects/textarea
            const spans = container.querySelectorAll("span.editable-field");
            spans.forEach((span) => {
            const name = span.dataset.name;
            const inputType = span.dataset.type || "text";
            let input;
            if (name === "gender") {
            input = document.createElement("select");
            input.className = "border border-gray-300 rounded px-2 py-1 text-sm w-full bg-white";
            input.name = name;
            input.id = span.id;
            ["Male", "Female", "Other"].forEach((optionText) => {
                const option = document.createElement("option");
                option.value = optionText;
                option.textContent = optionText;
                if (span.textContent.trim() === optionText) option.selected = true;
                input.appendChild(option);
            });
            } else if (name === "maritalStatus") {
            input = document.createElement("select");
            input.className = "border border-gray-300 rounded px-2 py-1 text-sm w-full bg-white";
            input.name = name;
            input.id = span.id;
            ["Single", "Married", "Divorced", "Widowed"].forEach((optionText) => {
                const option = document.createElement("option");
                option.value = optionText;
                option.textContent = optionText;
                if (span.textContent.trim() === optionText) option.selected = true;
                input.appendChild(option);
            });
            } else if (inputType === "textarea") {
            input = document.createElement("textarea");
            input.rows = 3;
            input.className = "border border-gray-300 rounded px-2 py-1 text-sm w-full";
            input.value = span.textContent.trim();
            input.name = name;
            input.id = span.id;
            } else {
            input = document.createElement("input");
            input.type = inputType;
            input.className = "border border-gray-300 rounded px-2 py-1 text-sm w-full";
            input.value = span.textContent.trim();
            input.name = name;
            input.id = span.id;
            }
            span.replaceWith(input);
            });
            // Show profile picture input
            document.getElementById("profilePicInput").classList.remove("hidden");
            editBtn.textContent = "Save Profile";
            editBtn.dataset.editing = "true";
            } else {
            // Save editing: gather all form data
            const formData = new FormData();
            
            // Get all input values
            const inputs = container.querySelectorAll("input[type=text], input[type=number], textarea, select");
            inputs.forEach((input) => {
                if (input.value.trim() !== '') {
                    formData.append(input.name, input.value.trim());
                }
            });

            // Show loading state
            editBtn.textContent = "Saving...";
            editBtn.disabled = true;

            // Send data to server
            fetch('processes/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successMessage = document.createElement('div');
                    successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
                    successMessage.textContent = data.message || 'Profile updated successfully';
                    document.body.appendChild(successMessage);
                    setTimeout(() => successMessage.remove(), 3000);

                    // Replace inputs with spans
                    inputs.forEach((input) => {
                        const span = document.createElement("span");
                        span.className = "editable-field";
                        span.dataset.name = input.name;
                        span.id = input.id;
                        span.dataset.type = input.dataset.type || "text";
                        span.textContent = input.value.trim() || getDefaultText(input.name);
                        input.replaceWith(span);
                    });

                    // Hide profile picture input
                    document.getElementById("profilePicInput").classList.add("hidden");
                    
                    // Reset button state
                    editBtn.textContent = "Edit Profile";
                    editBtn.dataset.editing = "false";
                    editBtn.disabled = false;

                    // Refresh the profile data
                    displayProfileData();
                } else {
                    throw new Error(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message
                const errorMessage = document.createElement('div');
                errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                errorMessage.textContent = error.message || 'Failed to update profile';
                document.body.appendChild(errorMessage);
                setTimeout(() => errorMessage.remove(), 3000);
                
                // Reset button state
                editBtn.textContent = "Save Profile";
                editBtn.disabled = false;
            });
            }
        }

        // Helper function to get default text for fields
        function getDefaultText(fieldName) {
            const defaults = {
                'age': 'Not specified',
                'gender': 'Not specified',
                'maritalStatus': 'Not specified',
                'bio': 'No bio provided',
                'address': 'No address provided',
                'emergencyContactName': 'Not provided',
                'emergencyContactPhone': 'Not provided',
                'emergencyContactRelationship': 'Not specified',
                'blood_type': 'Not specified',
                'allergies': 'None reported',
                'current_medications': 'None reported',
                'medical_history': 'No history provided'
            };
            return defaults[fieldName] || 'Not specified';
        }

        // Function to display profile data
        async function displayProfileData() {
            try {
                const response = await fetch('api/get_patient_data.php');
                if (!response.ok) {
                    throw new Error('Failed to fetch profile data');
                }
                
                let data;
                try {
                    const text = await response.text();
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Invalid JSON response: ' + e.message);
                }

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load profile data');
                }

                const profileData = data.data;
                
                // Update all editable fields
                document.querySelectorAll('.editable-field').forEach(field => {
                    const fieldName = field.dataset.name;
                    if (!field || !fieldName) return; // Skip if element or data attribute is missing
                    
                    const value = profileData[fieldName] || getDefaultText(fieldName);
                    field.textContent = value;
                });

                // Update profile picture if available
                const profilePic = document.getElementById('profilePic');
                if (profilePic) {
                    const picturePath = profileData.picture_path || 'Pictures/Logo.jpg';
                    // Add timestamp to prevent caching
                    profilePic.src = picturePath + '?t=' + new Date().getTime();
                    // Add error handler for image load failure
                    profilePic.onerror = function() {
                        this.src = 'Pictures/Logo.jpg';
                    };
                }

                // Update name and email in header if elements exist
                const nameElement = document.getElementById('nameDisplay');
                const emailElement = document.getElementById('emailDisplay');
                if (nameElement) nameElement.textContent = profileData.name || 'Name not available';
                if (emailElement) emailElement.textContent = profileData.email || 'Email not available';

            } catch (error) {
                console.error('Error loading profile data:', error);
                // Show error message to user
                const errorMessage = document.createElement('div');
                errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                errorMessage.textContent = error.message || 'Failed to load profile data';
                document.body.appendChild(errorMessage);
                setTimeout(() => errorMessage.remove(), 3000);
            }
        }

        // Add DOM content loaded event listener
        document.addEventListener('DOMContentLoaded', () => {
            // Initial load of profile data
            displayProfileData();

            // Add error handling for profile picture upload
            const profilePicInput = document.getElementById('profilePicInput');
            if (profilePicInput) {
                profilePicInput.addEventListener('change', async (e) => {
                    const file = e.target.files[0];
                    if (!file) return;

                    // Validate file type
                    if (!file.type.startsWith('image/')) {
                        alert('Please select an image file');
                        return;
                    }

                    // Validate file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size should be less than 5MB');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('profile_picture', file);

                    try {
                        const response = await fetch('processes/upload_profile_picture.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }

                        let data;
                        try {
                            const text = await response.text();
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error('Raw response:', text);
                            throw new Error('Invalid JSON response: ' + e.message);
                        }

                        if (data.success) {
                            // Refresh profile data to show new picture
                            displayProfileData();
                            
                            // Show success message
                            const successMessage = document.createElement('div');
                            successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
                            successMessage.textContent = 'Profile picture updated successfully';
                            document.body.appendChild(successMessage);
                            setTimeout(() => successMessage.remove(), 3000);
                        } else {
                            throw new Error(data.message || 'Failed to upload profile picture');
                        }
                    } catch (error) {
                        console.error('Error uploading profile picture:', error);
                        const errorMessage = document.createElement('div');
                        errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                        errorMessage.textContent = error.message || 'Failed to upload profile picture';
                        document.body.appendChild(errorMessage);
                        setTimeout(() => errorMessage.remove(), 3000);
                    } finally {
                        // Reset file input
                        e.target.value = '';
                    }
                });
            }
        });

        // Handle profile picture change and preview
        function handleProfilePicChange(event) {
            const file = event.target.files[0];
            if (file) {
                // Show loading state
                const profilePic = document.getElementById('profilePic');
                const loadingDiv = document.getElementById('profilePicLoading');
                loadingDiv.classList.remove('hidden');
                
                // Create form data
                const formData = new FormData();
                formData.append('profile_picture', file);
                
                // Upload the file
                fetch('processes/upload_profile_picture.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        // Update the image
                        profilePic.src = data.picture_path;
                        
                        // Show success message
                        const successMessage = document.createElement('div');
                        successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
                        successMessage.textContent = 'Profile picture updated successfully';
                        document.body.appendChild(successMessage);
                        setTimeout(() => successMessage.remove(), 3000);
                    } else {
                        throw new Error(data.message || 'Failed to update profile picture');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                    errorMessage.textContent = error.message || 'Failed to update profile picture';
                    document.body.appendChild(errorMessage);
                    setTimeout(() => errorMessage.remove(), 3000);
                })
                .finally(() => {
                    loadingDiv.classList.add('hidden');
                    // Reset file input
                    event.target.value = '';
                });
            }
        }

        // Toggle settings menu visibility
        function toggleMenu() {
            const menu = document.getElementById("slideMenu");
            if (menu.classList.contains("hidden")) {
            menu.classList.remove("hidden");
            } else {
            menu.classList.add("hidden");
            }
        }

        // Close settings menu
        function closeMenu() {
            const menu = document.getElementById("slideMenu");
            menu.classList.add("hidden");
        }

        // Show notification or message modal
        function showNotification(content, isMessage = false, replyId = "") {
            const modal = document.getElementById("modal");
            const modalContent = document.getElementById("modalContent");
            const modalReply = document.getElementById("modalReply");
            const modalReplyTextarea = document.getElementById("modalReplyTextarea");
            const modalReplySend = document.getElementById("modalReplySend");

            modalContent.textContent = content;

            if (isMessage) {
            modalReply.classList.remove("hidden");
            modalReplyTextarea.value = "";
            modalReplySend.onclick = () => {
            if (modalReplyTextarea.value.trim() === "") {
            alert("Please enter a reply message.");
            return;
            }
            alert("Reply sent: " + modalReplyTextarea.value.trim());
            modalReplyTextarea.value = "";
            closeModal();
            };
            } else {
            modalReply.classList.add("hidden");
            }

            modal.classList.remove("hidden");
        }

        // Close modal
        function closeModal() {
            const modal = document.getElementById("modal");
            modal.classList.add("hidden");
        }

        // Toggle mobile menu
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const isOpen = !menu.classList.contains('translate-x-full');
            
            if (isOpen) {
            // Close menu
            menu.classList.add('translate-x-full');
            } else {
            // Open menu
            menu.classList.remove('translate-x-full');
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobileMenu');
            const menuButton = document.getElementById('mobileMenuButton');
            
            if (!menu.contains(event.target) && !menuButton.contains(event.target) && !menu.classList.contains('translate-x-full')) {
            menu.classList.add('translate-x-full');
            }
        });

        // Appointment Modal Functions
        function showAppointmentDetails(appointment) {
            const modal = document.getElementById('appointmentModal');
            const detailsContainer = document.getElementById('appointmentDetails');
            
            // Example of appointment details HTML
            detailsContainer.innerHTML = `
            <div class="space-y-4">
            <div class="text-center mb-4">
            <img src="Pictures/appointment-details.jpg" alt="Appointment Details" class="rounded-lg mx-auto mb-4" style="max-height: 200px;">
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="font-semibold">Date & Time:</div>
            <div>${appointment.datetime}</div>
            <div class="font-semibold">Doctor:</div>
            <div>${appointment.doctor}</div>
            <div class="font-semibold">Type:</div>
            <div>${appointment.type}</div>
            <div class="font-semibold">Status:</div>
            <div>${appointment.status}</div>
            <div class="font-semibold">Location:</div>
            <div>${appointment.location}</div>
            </div>
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <div class="font-semibold mb-2">Notes:</div>
            <p class="text-sm text-gray-600">${appointment.notes}</p>
            </div>
            </div>
            `;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAppointmentModal() {
            const modal = document.getElementById('appointmentModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Message Popup
        function showMessageDetails(message) {
            const modal = document.getElementById('messageModal');
            const contentContainer = document.getElementById('messageContent');
            
            contentContainer.innerHTML = `
            <h3 class="text-lg font-semibold text-gray-900 mb-2">${message.title}</h3>
            <p class="text-gray-800 mb-2">${message.content}</p>
            <p class="text-sm text-gray-500">${message.time}</p>
            `;
            
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.getElementById('messageReply').value = ''; // Clear previous reply
        }

        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }

        function sendReply() {
            const replyText = document.getElementById('messageReply').value;
            if (replyText.trim()) {
            // Here you would typically send the reply to your backend
            alert('Reply sent successfully!');
            closeMessageModal();
            }
        }

        // Add click handlers to appointments
        function initializeAppointmentHandlers() {
            // For upcoming appointments
            document.querySelectorAll('#upcomingAppointmentsList .bg-gray-50.rounded-lg').forEach(appointment => {
            appointment.addEventListener('click', () => {
                const appointmentData = {
                datetime: appointment.querySelector('p:first-child').textContent,
                doctor: appointment.querySelector('p:nth-child(2)').textContent,
                type: 'Regular Checkup',
                status: 'Scheduled',
                location: 'Main Clinic, Room 204',
                notes: 'Regular follow-up appointment. Please bring your medical records and any recent test results.'
                };
                showAppointmentDetails(appointmentData);
            });
            });

            // For appointment history
            document.querySelectorAll('#appointmentHistoryList .bg-gray-50.rounded-lg').forEach(appointment => {
            appointment.addEventListener('click', () => {
                const appointmentData = {
                datetime: appointment.querySelector('p:first-child').textContent,
                doctor: appointment.querySelector('p:nth-child(2)').textContent,
                type: 'Regular Checkup',
                status: appointment.querySelector('p:nth-child(3)').textContent,
                location: 'Main Clinic, Room 204',
                notes: 'Past appointment record. Click for more details.'
                };
                showAppointmentDetails(appointmentData);
            });
            });
        }

        // Initialize message handlers
        function initializeMessageHandlers() {
            // Add click handlers to message containers
            document.querySelectorAll('#messagesNotifications .rounded-lg').forEach(messageContainer => {
            messageContainer.classList.add('cursor-pointer');
            messageContainer.addEventListener('click', (e) => {
                // Don't trigger if clicking the reply button
                if (!e.target.classList.contains('text-blue-600')) {
                const messageData = {
                    title: messageContainer.querySelector('p:first-child').textContent,
                    content: messageContainer.querySelector('.text-sm').textContent,
                    time: messageContainer.querySelector('.text-xs').textContent
                };
                showMessageDetails(messageData);
                }
            });
            });

            // Add click handlers to Reply buttons
            document.querySelectorAll('.text-blue-600').forEach(replyButton => {
            replyButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Get the parent message container
                const messageContainer = e.target.closest('.rounded-lg');
                if (messageContainer) {
                const messageData = {
                    title: messageContainer.querySelector('p:first-child').textContent,
                    content: messageContainer.querySelector('.text-sm').textContent,
                    time: messageContainer.querySelector('.text-xs').textContent
                };
                showMessageDetails(messageData);
                }
            });
            });
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Get session data safely
            const sessionData = {
                userName: '<?php echo addslashes($user_name); ?>',
                userEmail: '<?php echo addslashes($user_email); ?>',
                userRole: '<?php echo addslashes($user_role); ?>',
                userId: '<?php echo addslashes($user_id); ?>'
            };
            
            // Set initial values from session
            if (document.getElementById('nameDisplay')) {
                document.getElementById('nameDisplay').textContent = sessionData.userName;
            }
            if (document.getElementById('emailDisplay')) {
                document.getElementById('emailDisplay').textContent = sessionData.userEmail;
            }
            if (document.getElementById('roleDisplay')) {
                document.getElementById('roleDisplay').textContent = 
                    sessionData.userRole.charAt(0).toUpperCase() + sessionData.userRole.slice(1);
            }
            if (document.getElementById('userIdDisplay')) {
                document.getElementById('userIdDisplay').textContent = `ID: ${sessionData.userId}`;
            }

            // Then fetch fresh data from the server
            displayProfileData();
            
            // Initialize handlers
            initializeAppointmentHandlers();
            initializeMessageHandlers();
        });

        // Add global variable for patient ID from session
        const PATIENT_ID = <?php echo json_encode($user_id); ?>;

        // Update database functions to include patient ID
        async function fetchPatientProfile() {
            const response = await fetch(`/api/patient/profile.php?patient_id=${PATIENT_ID}`);
            if (!response.ok) throw new Error('Failed to fetch profile');
            return response.json();
        }

        // Debug log - remove in production
        console.log('Session data passed to JavaScript:', {
            name: <?php echo json_encode($user_name); ?>,
            email: <?php echo json_encode($user_email); ?>,
            role: <?php echo json_encode($user_role); ?>,
            patient_id: <?php echo json_encode($user_id); ?>
        });

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdown = document.getElementById('userDropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');

            function toggleDropdown(event) {
                event.stopPropagation();
                userDropdownMenu.classList.toggle('show');
                userDropdown.classList.toggle('active');
            }

            function closeDropdown(event) {
                if (!userDropdown.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.remove('show');
                    userDropdown.classList.remove('active');
                }
            }

            userDropdown.addEventListener('click', toggleDropdown);
            document.addEventListener('click', closeDropdown);
        });

        // Function to cancel an appointment
        function cancelAppointment(appointmentId) {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
            return;
        }
        
        // Show loading state
        const loadingMessage = document.createElement('div');
        loadingMessage.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded shadow-lg z-50';
        loadingMessage.textContent = 'Cancelling appointment...';
        document.body.appendChild(loadingMessage);
        
        // Send cancel request to server
        fetch('processes/cancel_appointment.php', {
            method: 'POST',
            headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `appointment_id=${appointmentId}`
        })
        .then(response => {
            if (!response.ok) {
            throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Remove loading message
            loadingMessage.remove();
            
            if (data.success) {
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMessage.textContent = data.message || 'Appointment cancelled successfully';
            document.body.appendChild(successMessage);
            setTimeout(() => successMessage.remove(), 3000);
            
            // Reload the page to refresh appointment lists
            window.location.reload();
            } else {
            throw new Error(data.message || 'Failed to cancel appointment');
            }
        })
        .catch(error => {
            // Remove loading message
            loadingMessage.remove();
            
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
            errorMessage.textContent = error.message || 'Failed to cancel appointment';
            document.body.appendChild(errorMessage);
            setTimeout(() => errorMessage.remove(), 3000);
        });
        }
      
      // Function to load notifications from database
      function loadNotifications() {
        // Get the notifications container
        const container = document.getElementById('messagesNotificationsList');
        if (!container) return;
        
        // Show loading message (already in your HTML)
        
        // Fetch notifications from database
        fetch('api/get_notifications.php')
          .then(response => response.json())
          .then(data => {
            // Clear loading message
            container.innerHTML = '';
            
            if (!data.success) {
              container.innerHTML = '<p class="text-gray-500 text-center py-4">Could not load notifications</p>';
              return;
            }
            
            const notifications = data.data;
            
            // If no notifications
            if (notifications.length === 0) {
              container.innerHTML = '<p class="text-gray-500 text-center py-4">No notifications</p>';
              return;
            }
            
            // Add each notification to the container
            notifications.forEach(notification => {
              // Create notification item
              const item = document.createElement('div');
              item.className = 'notification-item bg-white rounded-lg p-3 cursor-pointer transition-all hover:shadow-md';
              
              // Style based on read status
              if (!notification.is_read) {
                item.classList.add('border-l-4', 'border-blue-500', 'bg-blue-50');
              }
              
              // Select icon based on notification type
              let icon = 'fa-bell';
              let iconClass = 'text-gray-500';
              
              if (notification.type === 'message') {
                icon = 'fa-envelope';
                iconClass = 'text-blue-500';
              } else if (notification.type === 'appointment') {
                icon = 'fa-calendar-check';
                iconClass = 'text-green-500';
              } else if (notification.type === 'system') {
                icon = 'fa-cog';
                iconClass = 'text-purple-500';
              }
              
              // Set notification content
              item.innerHTML = `
                <div class="flex">
                  <div class="mr-3">
                    <i class="fas ${icon} ${iconClass}"></i>
                  </div>
                  <div class="flex-1">
                    <div class="flex justify-between items-start">
                      <h4 class="font-semibold text-sm">${notification.title}</h4>
                      <span class="text-xs text-gray-500">${notification.date}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">${notification.content.length > 60 ? notification.content.substring(0, 60) + '...' : notification.content}</p>
                  </div>
                </div>
              `;
              
              // Add click handler
              item.addEventListener('click', () => {
                // Mark as read
                fetch('processes/mark_notification_read.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: `notification_id=${notification.id}`
                });
                
                // Show notification content
                alert(`${notification.title}\n\n${notification.content}`);
                
                // Update UI to show as read
                item.classList.remove('border-l-4', 'border-blue-500', 'bg-blue-50');
              });
              
              // Add to container
              container.appendChild(item);
            });
          })
          .catch(error => {
            console.error('Error fetching notifications:', error);
            container.innerHTML = '<p class="text-gray-500 text-center py-4">Failed to load notifications</p>';
          });
      }
      
      // Load notifications when page loads
      document.addEventListener('DOMContentLoaded', function() {
        // Load notifications
        loadNotifications();
        
        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
      });
      </script>
  <style>
   @media (min-width: 1024px) {
    #profileContainer {
     max-height: none !important;
    }
    #appointmentsHistoryContainer {
     display: flex;
     flex-direction: column;
     gap: 1.5rem;
     height: 40%;
     min-height: 240px;
     max-height: 280px;
     justify-content: flex-start;
    }
    #upcomingAppointments,
    #appointmentHistory {
     flex: 1 1 0;
     display: flex;
     flex-direction: column;
     overflow: hidden;
    }
    #upcomingAppointments > h3,
    #appointmentHistory > h3 {
     position: sticky;
     top: 0;
     background: white;
     padding-top: 0.5rem;
     padding-bottom: 0.5rem;
     z-index: 10;
     border-bottom: 1px solid #e5e7eb; /* Tailwind gray-200 */
     margin-bottom: 0.5rem;
    }
    #upcomingAppointments > div,
    #appointmentHistory > div {
     overflow-y: auto;
     flex-grow: 1;
     padding-right: 0.25rem; /* for scrollbar space */
    }
    #upcomingAppointments > div button,
    #appointmentHistory > div button {
     min-height: 3.5rem; /* enough to show at least 2 lines comfortably */
     display: flex;
     align-items: center;
     padding-top: 0.5rem;
     padding-bottom: 0.5rem;
    }
   }
   /* Move Settings button slightly right near edge */
   #settingsButton {
    margin-left: 2rem !important;
   }

   .navbar {
       background-color: #2563eb;
       padding: 0.5rem 1rem;
       color: white;
       height: 3.5rem;
       display: flex;
       align-items: center;
       justify-content: space-between;
       position: relative;
       z-index: 50;
   }

   .logo-section {
       display: flex;
       align-items: center;
       gap: 0.5rem;
   }

   .logo-section img {
       width: 2.5rem;
       height: 2.5rem;
       border-radius: 50%;
       object-fit: cover;
       border: 2px solid white;
       background-color: white;
   }

   .nav-links {
       display: flex;
       align-items: center;
       gap: 1.5rem;
       position: relative;
       z-index: 9999;
   }

   .nav-link {
       color: white;
       text-decoration: none;
       padding: 0.5rem 1rem;
       border-radius: 0.375rem;
       transition: background-color 0.2s;
       font-size: 0.875rem;
       font-weight: 600;
   }

   .nav-link:hover {
       background-color: rgba(255, 255, 255, 0.1);
   }

   .dropdown {
       position: relative;
       display: inline-block;
       z-index: 9999;
   }

   .dropdown-toggle {
       color: white;
       text-decoration: none;
       padding: 0.5rem 1rem;
       border-radius: 0.375rem;
       transition: all 0.2s;
       cursor: pointer;
       display: flex;
       align-items: center;
       gap: 0.5rem;
       background: rgba(255, 255, 255, 0.1);
   }

   .dropdown-toggle:hover {
       background: rgba(255, 255, 255, 0.2);
   }

   .dropdown-toggle i {
       transition: transform 0.2s;
   }

   .dropdown-toggle.active i {
       transform: rotate(180deg);
   }

   .dropdown-menu {
       position: absolute;
       right: 0;
       top: 100%;
       margin-top: 0.5rem;
       background: #FFFFFF;
       border-radius: 0.5rem;
       box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
       min-width: 200px;
       z-index: 9999;
       display: none;
       border: 1px solid #e5e7eb;
       opacity: 1;
       visibility: visible;
   }

   .dropdown-menu.show {
       display: block !important;
       opacity: 1 !important;
       visibility: visible !important;
   }

   .dropdown-item {
       display: flex;
       align-items: center;
       gap: 0.75rem;
       padding: 0.75rem 1rem;
       color: #000000;
       text-decoration: none;
       font-size: 0.875rem;
       transition: background-color 0.2s;
       cursor: pointer;
       width: 100%;
       background-color: #FFFFFF;
   }

   .dropdown-item:hover {
       background-color: #f3f4f6;
       color: #000000;
   }

   .dropdown-item i {
       color: #000000;
       width: 1rem;
       text-align: center;
   }

   .dropdown-item span {
       color: #000000;
   }

   .dropdown-divider {
       height: 1px;
       background-color: #e5e7eb;
       margin: 0.5rem 0;
   }
  </style>
 </head>
 <body class="bg-gray-100 font-sans flex flex-col min-h-screen relative overflow-x-hidden">
  <header class="bg-blue-600 shadow-md">
    <nav class="w-full flex items-center justify-between h-16">
     <div class="logo-section">
      <img src="assets/images/logo.jpg" alt="DocNow Logo" class="h-8 w-8 rounded ml-4">
      <span class="text-white font-semibold text-base select-none">DocNow</span>
     </div>
     <div class="nav-links">
      <a href="patient_dashboard.php" class="nav-link active">Dashboard</a>
      <a href="doctors.php" class="nav-link">Doctors</a>
      <a href="booking.php" class="nav-link">Booking</a>
      <div class="dropdown">
       <div class="dropdown-toggle" id="userDropdown">
        <span><?php echo htmlspecialchars($user_name); ?></span>
        <i class="fas fa-chevron-down"></i>
       </div>
       <div class="dropdown-menu" id="userDropdownMenu">
        <a href="change_password.php" class="dropdown-item">
         <i class="fas fa-key"></i>
         <span>Change Password</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="auth/logout.php" class="dropdown-item">
         <i class="fas fa-sign-out-alt"></i>
         <span>Logout</span>
        </a>
       </div>
      </div>
     </div>
    </nav>
   </header>

   <!-- Mobile Menu -->
   <div id="mobileMenu" class="fixed inset-y-0 right-0 w-64 bg-blue-800 transform translate-x-full transition-transform duration-200 ease-in-out md:hidden">
    <div class="flex flex-col h-full">
     <div class="flex justify-end p-4">
      <button
       aria-label="Close mobile menu"
       class="text-white text-xl p-2 hover:text-blue-300 transition-colors"
       onclick="toggleMobileMenu()"
       type="button"
      >
       <i class="fas fa-times"></i>
      </button>
     </div>
     <nav class="flex flex-col space-y-4 p-4">
      <a href="patient_dashboard.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors bg-blue-900">Dashboard</a>
      <a href="doctors.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Doctors</a>
      <a href="booking.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Booking</a>
      <hr class="border-blue-700">
      <a href="profile.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Account</a>
      <a href="logout.php" class="text-white text-sm font-semibold hover:text-blue-300 transition-colors">Sign Out</a>
     </nav>
    </div>
   </div>

   <!-- Slide menu -->
   <div
    aria-label="Slide menu"
    class="fixed top-16 right-4 h-auto w-52 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50"
    id="slideMenu"
   >
    <nav class="mt-8 flex flex-col space-y-3 px-6 pb-6">
     <a
      class="text-gray-800 font-semibold hover:text-blue-600 select-none rounded-md px-3 py-2 transition-colors"
      href="profile.php"
      >Account</a
     >
     <a
      class="text-gray-800 font-semibold hover:text-blue-600 select-none rounded-md px-3 py-2 transition-colors"
      href="logout.php"
      >Logout</a
     >
    </nav>
   </div>

  <main class="w-full px-4 sm:px-6 lg:px-8 mt-8 mb-8 flex-grow">
   <h2 class="text-gray-900 font-semibold text-lg mb-6 select-none">Patient Profile</h2>
   <div class="flex flex-col lg:flex-row lg:space-x-6">
    <!-- Left Column - Patient Profile -->
    <div class="lg:w-[30%]">
     <section
      aria-label="Patient Profile Information"
      class="bg-white rounded-lg shadow-md h-[calc(100vh-180px)]"
      id="profileContainer"
     >
      <div class="p-6 h-full overflow-y-auto">
       <div class="flex flex-col space-y-1 mb-6">
        <div class="flex items-center space-x-4">
         <div class="relative">
          <img
           id="profilePic"
           alt="Profile picture"
           class="rounded-full border border-gray-300 w-20 h-20 object-cover cursor-pointer"
           src="Pictures/Logo.jpg"
           onclick="document.getElementById('profilePicInput').click()"
          />
          <div id="profilePicLoading" class="absolute inset-0 bg-gray-200 rounded-full flex items-center justify-center hidden">
           <span class="text-gray-500 text-sm">Uploading...</span>
          </div>
          <input
           type="file"
           accept="image/*"
           id="profilePicInput"
           class="hidden"
           onchange="handleProfilePicChange(event)"
          />
          <div class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-1 cursor-pointer"
               onclick="document.getElementById('profilePicInput').click()">
           <i class="fas fa-camera text-white text-xs"></i>
          </div>
         </div>
         <div class="flex flex-col">
          <h4 id="nameDisplay" class="text-lg font-semibold text-gray-900">
            <?php echo htmlspecialchars($user_name); ?>
          </h4>
          <span id="emailDisplay" class="text-sm text-gray-600">
            <?php echo htmlspecialchars($user_email); ?>
          </span>
          <span id="roleDisplay" class="text-xs text-gray-500 mt-1">
            <?php echo htmlspecialchars(ucfirst($user_role)); ?>
          </span>
          <span id="userIdDisplay" class="text-xs text-gray-500">
            ID: <?php echo htmlspecialchars($user_id); ?>
          </span>
         </div>
        </div>
       </div>
       <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 mb-4 text-sm text-gray-900">
        <div>
         <span class="font-semibold select-none">Age:</span>
         <span class="editable-field select-text" data-name="age" id="age" data-type="number">Not specified</span>
        </div>
        <div>
         <span class="font-semibold select-none">Gender:</span>
         <span class="editable-field select-text" data-name="gender" id="gender" data-type="text">Not specified</span>
        </div>
        <div>
         <span class="font-semibold select-none">Marital Status:</span>
         <span class="editable-field select-text" data-name="maritalStatus" id="maritalStatus" data-type="text">Not specified</span>
        </div>
       </div>
       <p class="mb-4 text-sm text-gray-900">
        <span class="font-semibold select-none">Bio:</span>
        <span class="editable-field select-text" data-name="bio" id="bio" data-type="textarea">No bio provided</span>
       </p>
       <p class="mb-2 text-sm text-gray-900">
        <span class="font-semibold select-none">Address:</span>
        <span class="editable-field select-text" data-name="address" id="address" data-type="text">No address provided</span>
       </p>

       <!-- Medical Information Section -->
       <div class="mt-6 mb-4">
           <h3 class="text-lg font-semibold text-gray-900 mb-4">Medical Information</h3>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Blood Type:</span>
               <span class="editable-field select-text" data-name="blood_type" id="blood_type" data-type="text">Not specified</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Allergies:</span>
               <span class="editable-field select-text" data-name="allergies" id="allergies" data-type="textarea">None reported</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Current Medications:</span>
               <span class="editable-field select-text" data-name="current_medications" id="current_medications" data-type="textarea">None reported</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Medical History:</span>
               <span class="editable-field select-text" data-name="medical_history" id="medical_history" data-type="textarea">No history provided</span>
           </p>
       </div>

       <!-- Emergency Contact Information -->
       <div class="mt-6 mb-4">
           <h3 class="text-lg font-semibold text-gray-900 mb-4">Emergency Contact</h3>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Name:</span>
               <span class="editable-field select-text" data-name="emergencyContactName" id="emergencyContactName" data-type="text">Not provided</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Phone Number:</span>
               <span class="editable-field select-text" data-name="emergencyContactPhone" id="emergencyContactPhone" data-type="text">Not provided</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Relationship:</span>
               <span class="editable-field select-text" data-name="emergencyContactRelationship" id="emergencyContactRelationship" data-type="text">Not specified</span>
           </p>
       </div>

       <button
        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-4 py-2 rounded select-none"
        data-editing="false"
        id="editProfileBtn"
        onclick="toggleEdit()"
        type="button"
       >
        Edit Profile
       </button>
      </div>
     </section>
    </div>

    <!-- Middle Column - Appointments -->
    <div class="lg:w-[40%]">
     <div class="bg-white rounded-lg shadow-md h-[calc(100vh-180px)] flex flex-col">
    <!-- Upcoming Appointments -->
        <section
            id="upcomingAppointments"
            aria-label="Upcoming Appointments"
            class="p-6 flex-1 flex flex-col"
            >
            <h3 class="text-gray-900 font-semibold text-base mb-4 border-b border-gray-200 py-2 sticky top-0 bg-white z-10">
            Upcoming Appointments
            </h3>
            <div class="overflow-y-auto flex-1 space-y-3 pr-2">
            <?php if ($upcomingAppointments->num_rows === 0): ?>
                <p class="text-gray-500 text-center py-4">No upcoming appointments found.</p>
                <div class="text-center">
                <a href="booking.php" class="mt-2 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded font-medium text-sm transition-colors duration-200">
                    Book an Appointment
                </a>
                </div>
            <?php else: ?>
                <?php while ($appointment = $upcomingAppointments->fetch_assoc()): ?>
                <div class="border-b border-gray-200 pb-3 last:border-0 hover:bg-gray-50 transition-colors duration-200 rounded p-2">
                    <h4 class="font-medium">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h4>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['specialty'] ?? 'General'); ?></p>
                    <div class="flex items-center mt-1 text-sm text-gray-500">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <span><?php echo date('F j, Y', strtotime($appointment['appointment_datetime'])); ?></span>
                    <span class="mx-2">|</span>
                    <i class="far fa-clock mr-2"></i>
                    <span><?php echo date('g:i A', strtotime($appointment['appointment_datetime'])); ?></span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                    <div class="flex items-center gap-2">
                        <?php if (!empty($appointment['appointment_type'])): ?>
                        <span class="px-2 py-1 bg-blue-50 rounded-full text-xs text-blue-600"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                        <?php endif; ?>
                        <span class="px-3 py-1 rounded-full text-xs 
                        <?php 
                            switch($appointment['status']) {
                            case 'confirmed': echo 'bg-green-100 text-green-800'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                            case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                        </span>
                    </div>
                    <button 
                        onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)" 
                        class="text-xs text-red-600 hover:text-red-800 hover:underline transition-colors duration-200"
                        title="Cancel appointment">
                        Cancel
                    </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </section>

      <hr class="border-gray-200 mx-6 my-0">

            <!-- Appointment History -->
        <section
            id="appointmentHistory"
            aria-label="Appointment History"
            class="p-6 flex-1 flex flex-col"
            >
            <h3 class="text-gray-900 font-semibold text-base mb-4 border-b border-gray-200 py-2 sticky top-0 bg-white z-10">
            Appointment History
            </h3>
            <div class="overflow-y-auto flex-1 space-y-3 pr-2">
            <?php if ($appointmentHistory->num_rows === 0): ?>
                <p class="text-gray-500 text-center py-4">No appointment history found.</p>
            <?php else: ?>
                <?php while ($appointment = $appointmentHistory->fetch_assoc()): ?>
                <div class="border-b border-gray-200 pb-3 last:border-0 hover:bg-gray-50 transition-colors duration-200 rounded p-2">
                    <h4 class="font-medium">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h4>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['specialty'] ?? 'General'); ?></p>
                    <div class="flex items-center mt-1 text-sm text-gray-500">
                    <i class="far fa-calendar-alt mr-2"></i>
                    <span><?php echo date('F j, Y', strtotime($appointment['appointment_datetime'])); ?></span>
                    <span class="mx-2">|</span>
                    <i class="far fa-clock mr-2"></i>
                    <span><?php echo date('g:i A', strtotime($appointment['appointment_datetime'])); ?></span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                    <?php if (!empty($appointment['appointment_type'])): ?>
                        <span class="px-2 py-1 bg-gray-50 rounded-full text-xs text-gray-600"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                    <?php endif; ?>
                    <span class="px-3 py-1 rounded-full text-xs 
                        <?php 
                        switch($appointment['status']) {
                            case 'confirmed': echo 'bg-green-100 text-green-800'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                            case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
            </div>
        </section>


     </div>
    </div>

    <!-- Right Column - Messages & Notifications -->
    <div class="lg:w-[30%]">
     <section
      id="messagesNotifications"
      class="bg-white rounded-lg shadow-md h-[calc(100vh-180px)]"
     >
      <div class="p-6 h-full flex flex-col">
       <h3 class="text-gray-900 font-semibold text-base mb-4 border-b border-gray-200 py-2 sticky top-0 bg-white">
        Messages & Notifications
       </h3>
       <div id="messagesNotificationsList" class="space-y-3 overflow-y-auto flex-1 pr-2">
        <p class="text-gray-500 text-center py-4" id="notificationsLoading">Loading notifications...</p>
       </div>
      </div>
     </section>
    </div>
   </div>
  </main>

  <!-- Modal for notification and message content -->
  <div
   class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-60 hidden"
   id="modal"
  >
   <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">
    <button
     aria-label="Close modal"
     class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl focus:outline-none"
     onclick="closeModal()"
    >
     <i class="fas fa-times"></i>
    </button>
    <div class="text-gray-900 text-sm mb-4" id="modalContent"></div>
    <div class="hidden" id="modalReply">
     <textarea
      class="w-full border border-gray-300 rounded px-2 py-1 text-sm resize-none"
      id="modalReplyTextarea"
      rows="4"
      placeholder="Type your reply..."
     ></textarea>
     <button
      class="mt-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded"
      id="modalReplySend"
      type="button"
     >
      Send
     </button>
    </div> 
   </div>
  </div>

  <!-- Appointment Details Modal -->
  <div id="appointmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
   <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
    <div class="flex justify-between items-center p-4 border-b border-gray-200">
     <h3 class="text-lg font-semibold text-gray-900">Appointment Details</h3>
     <button onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600">
      <i class="fas fa-times"></i>
     </button>
    </div>
    <div class="p-4">
     <div id="appointmentDetails"></div>
    </div>
   </div>
  </div>

  <!-- Message Popup -->
  <div id="messageModal" class="fixed inset-0 hidden items-center justify-center z-50">
   <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeMessageModal()"></div>
   <div class="bg-white rounded-lg shadow-lg w-[500px] relative z-10">
    <div class="flex justify-end p-2">
     <button onclick="closeMessageModal()" class="text-gray-400 hover:text-gray-600">
      <i class="fas fa-times"></i>
     </button>
    </div>
    <div class="px-6 pb-6">
     <div id="messageContent" class="mb-6 border-b border-gray-200 pb-4"></div>
     <div class="bg-white rounded-lg">
      <textarea
       id="messageReply"
       class="w-full border border-gray-200 rounded-lg p-4 focus:outline-none focus:border-blue-500 resize-none"
       rows="4"
       placeholder="Type your reply..."
      ></textarea>
      <div class="flex justify-end mt-4">
       <button
        onclick="sendReply()"
        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold"
       >
        Send
       </button>
      </div>
     </div>
    </div>
   </div>
  </div>

  <footer class="bg-gray-800 text-white py-2 rounded-t-lg shadow-md text-center mt-8">
   <p class="text-sm">© 2023 DocNow. All rights reserved.</p>
  </footer>
 </body>
</html>