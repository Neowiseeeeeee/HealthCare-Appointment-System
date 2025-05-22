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
  <script src="modal-fix.js" defer></script>
  <script>
   // Function to close notification modal
   function closeModal() {
     const modal = document.getElementById('modal');
     if (modal) {
       modal.classList.add('hidden');
       document.body.style.overflow = 'auto';
     }
   }
   
   // Function to close message modal with animation
   window.closeMessageModal = function() {
     const modal = document.getElementById('messageModal');
     if (!modal) return;
     
     // Set aria-hidden and restore body scroll
     modal.setAttribute('aria-hidden', 'true');
     document.body.style.overflow = 'auto';
     
     // Move focus back to the button that opened the modal if available
     const lastFocusedElement = document.activeElement;
     if (lastFocusedElement && lastFocusedElement.hasAttribute('data-message-id')) {
       lastFocusedElement.focus();
     }
     
     // Start fade out animation
     modal.classList.remove('opacity-100');
     modal.classList.add('opacity-0');
     
     // After animation completes, hide the modal
     setTimeout(() => {
       modal.classList.add('hidden');
       modal.classList.remove('flex');
       modal.style.display = 'none';
       modal.style.pointerEvents = 'none';
       
       // Reset modal state and restore scrolling
       document.body.style.overflow = 'auto';
       
       // Return focus to the element that opened the modal
       const activeElement = document.activeElement;
       if (activeElement && activeElement.closest('.message-item')) {
         activeElement.focus();
       }
       
       console.log('Modal closed successfully');
     }, 300); // Match this with your CSS transition duration
   }
   
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
                const response = await fetch('api/get_patient_data.php', {
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format');
                }
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load profile data');
                }

                const profileData = data.data;
                
                // Map of field IDs to their corresponding data keys
                const fieldMappings = {
                    'age': 'age',
                    'gender': 'gender',
                    'maritalStatus': 'marital_status',
                    'bio': 'bio',
                    'address': 'address',
                    'blood_type': 'blood_type',
                    'allergies': 'allergies',
                    'current_medications': 'current_medications',
                    'medical_history': 'medical_history',
                    'emergencyContactName': 'emergency_contact_name',
                    'emergencyContactPhone': 'emergency_contact_phone',
                    'emergencyContactRelationship': 'emergency_contact_relationship'
                };
                
                // Update all editable fields
                document.querySelectorAll('.editable-field').forEach(field => {
                    const fieldId = field.id;
                    if (!fieldId || !fieldMappings[fieldId]) return;
                    
                    const dataKey = fieldMappings[fieldId];
                    const value = profileData[dataKey] || getDefaultText(fieldId);
                    field.textContent = value;
                    // Field value set
                });

                // Update profile picture if available
                const profilePic = document.getElementById('profilePic');
                if (profilePic) {
                    if (profileData.picture_path) {
                        // Add timestamp to prevent caching issues
                        const timestamp = new Date().getTime();
                        const imageUrl = profileData.picture_path + (profileData.picture_path.includes('?') ? '&' : '?') + 't=' + timestamp;
                        // Setting profile picture URL
                        profilePic.src = imageUrl;
                    } else {
                        // No profile picture available
                        // Use an inline SVG data URL as a fallback
                        profilePic.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'150\' viewBox=\'0 0 150 150\'%3E%3Crect width=\'150\' height=\'150\' fill=\'%23e5e7eb\'/%3E%3Cpath d=\'M75 85 C91 85 105 71 105 55 C105 39 91 25 75 25 C59 25 45 39 45 55 C45 71 59 85 75 85 Z M35 125 L35 115 C35 99 57 90 75 90 C93 90 115 99 115 115 L115 125 Z\' fill=\'%239ca3af\'/%3E%3C/svg%3E';
                    }
                    
                    // Add error handler for image load failure
                    profilePic.onerror = function() {
                        // Failed to load profile image
                        this.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'150\' viewBox=\'0 0 150 150\'%3E%3Crect width=\'150\' height=\'150\' fill=\'%23e5e7eb\'/%3E%3Cpath d=\'M75 85 C91 85 105 71 105 55 C105 39 91 25 75 25 C59 25 45 39 45 55 C45 71 59 85 75 85 Z M35 125 L35 115 C35 99 57 90 75 90 C93 90 115 99 115 115 L115 125 Z\' fill=\'%239ca3af\'/%3E%3C/svg%3E';
                    };
                }

                // Update name and email in header if elements exist
                const nameElement = document.getElementById('nameDisplay');
                const emailElement = document.getElementById('emailDisplay');
                if (nameElement) nameElement.textContent = profileData.first_name || 'Name not available';
                if (emailElement) emailElement.textContent = profileData.email || 'Email not available';

            } catch (error) {
                // Show error message to user
                const errorMessage = document.createElement('div');
                errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                errorMessage.textContent = 'Failed to load profile data. Please try again.';
                document.body.appendChild(errorMessage);
                setTimeout(() => errorMessage.remove(), 3000);
                // Re-throw the error to be caught by the caller
                throw error;
            }
        }

        // Add DOM content loaded event listener
        document.addEventListener('DOMContentLoaded', () => {
            // Initial load of profile data with error handling
            displayProfileData().catch(error => {
                // Handle any uncaught errors from displayProfileData
                const errorMessage = document.createElement('div');
                errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                errorMessage.textContent = 'Error loading profile data';
                document.body.appendChild(errorMessage);
                setTimeout(() => errorMessage.remove(), 3000);
            });

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
            try {
                const response = await fetch(`/api/get_patient_data.php`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                // Profile data fetched
                return data;
            } catch (error) {
                console.error('Error fetching profile:', error);
                throw error;
            }
        }

        // Initialize error handling for unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            // Prevent the default handler
            event.preventDefault();
            
            // Show user-friendly error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
            errorMessage.textContent = 'An unexpected error occurred. Please refresh the page.';
            document.body.appendChild(errorMessage);
            setTimeout(() => errorMessage.remove(), 3000);
        });
        
        // Session data is passed to JavaScript

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
      
      // Function to toggle notification panel
      function toggleNotificationPanel() {
        const panel = document.getElementById('notificationPanel');
        if (panel) {
          const isHidden = panel.classList.contains('hidden');
          
          // Toggle panel visibility
          if (isHidden) {
            // Show panel
            panel.classList.remove('hidden');
            panel.style.display = 'block';
            // Load fresh notifications when opening
            loadNotifications();
          } else {
            // Hide panel
            panel.classList.add('hidden');
            panel.style.display = 'none';
          }
          
          // Close user dropdown if open
          const userDropdown = document.getElementById('userDropdownMenu');
          if (userDropdown && userDropdown.classList.contains('show')) {
            userDropdown.classList.remove('show');
          }
          
          // If we click outside the panel, close it
          if (!isHidden) {
            const closeClickHandler = function(event) {
              if (!panel.contains(event.target) && 
                  event.target.id !== 'notificationIcon' && 
                  !event.target.closest('#notificationIcon')) {
                panel.classList.add('hidden');
                panel.style.display = 'none';
                document.removeEventListener('click', closeClickHandler);
              }
            };
            
            // Add delayed click handler to avoid immediate triggering
            setTimeout(() => {
              document.addEventListener('click', closeClickHandler);
            }, 100);
          }
        }
      }
      
      // Function to mark all notifications as read
      function markAllNotificationsAsRead() {
        // Show loading state on the button
        const button = document.querySelector('#notificationPanel button');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
        button.disabled = true;
        
        // Send request to mark all as read
        fetch('processes/mark_all_notifications_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Reset notification count
            const notificationCountElement = document.getElementById('notificationCount');
            if (notificationCountElement) {
              notificationCountElement.textContent = '0';
            }
            
            // Reload notifications to show updated status
            loadNotifications();
            
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMessage.textContent = 'All notifications marked as read';
            document.body.appendChild(successMessage);
            setTimeout(() => successMessage.remove(), 3000);
          } else {
            throw new Error(data.message || 'Failed to mark notifications as read');
          }
        })
        .catch(error => {
          console.error('Error marking notifications as read:', error);
          // Show error message
          const errorMessage = document.createElement('div');
          errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
          errorMessage.textContent = error.message || 'Failed to mark notifications as read';
          document.body.appendChild(errorMessage);
          setTimeout(() => errorMessage.remove(), 3000);
        })
        .finally(() => {
          // Reset button state
          button.innerHTML = originalText;
          button.disabled = false;
        });
      }
      
      // Function to load notifications from database
      function loadNotifications() {
        // Get the notifications container
        const container = document.getElementById('notificationsList');
        const notificationCountElement = document.getElementById('notificationCount');
        
        if (!container) return;
        
        // Show loading message
        container.innerHTML = '<p class="text-gray-500 text-center py-4">Loading notifications...</p>';
        
        // Fetch notifications from database
        fetch('api/get_notifications.php')
          .then(response => response.json())
          .then(data => {
            // Clear loading message
            container.innerHTML = '';
            
            // Process notification data
            if (!data.success) {
              container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load notifications</p>';
              return;
            }
            
            const items = data.notifications || [];
            let unreadCount = 0;
            
            // Count unread notifications
            items.forEach(item => {
              if (!item.is_read) {
                unreadCount++;
              }
            });
            
            // Update notification count in the navbar
            if (notificationCountElement) {
              notificationCountElement.textContent = unreadCount;
            }
            
            // If no notifications
            if (items.length === 0) {
              container.innerHTML = '<p class="text-center py-4">No notifications</p>';
              return;
            }
            
            // Add each notification to the container
            items.forEach(notification => {
              // Create notification item
              const item = document.createElement('div');
              item.className = 'notification-item bg-white rounded-lg p-3 cursor-pointer transition-all hover:shadow-md mb-2';
              
              // Style based on read status
              if (!notification.is_read) {
                item.classList.add('border-l-4', 'border-blue-500', 'bg-blue-50');
              }
              
              // Add click event to mark as read when clicked
              item.addEventListener('click', () => {
                // If already read, do nothing
                if (notification.is_read) return;
                
                fetch('processes/mark_notification_read.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: `notification_id=${notification.id}`
                })
                .then(response => response.json())
                .then(data => {
                  if (data.success) {
                    // Mark as read in the UI
                    item.classList.remove('border-l-4', 'border-blue-500', 'bg-blue-50');
                    notification.is_read = true;
                    
                    // Update notification count
                    if (notificationCountElement) {
                      const currentCount = parseInt(notificationCountElement.textContent);
                      notificationCountElement.textContent = Math.max(0, currentCount - 1);
                    }
                  }
                })
                .catch(error => console.error('Error marking notification as read:', error));
              });
              
              // Create notification content
              const content = document.createElement('div');
              
              // Create title
              const title = document.createElement('p');
              title.className = 'font-semibold text-sm';
              title.textContent = notification.title || 'Notification';
              
              // Create message
              const message = document.createElement('p');
              message.className = 'text-sm text-gray-700 mt-1';
              message.textContent = notification.content || notification.message || '';
              
              // Create timestamp
              const timestamp = document.createElement('p');
              timestamp.className = 'text-xs text-gray-500 mt-1';
              timestamp.textContent = notification.date || notification.created_at || '';
              
              // Append elements
              content.appendChild(title);
              content.appendChild(message);
              content.appendChild(timestamp);
              item.appendChild(content);
              
              // Add to container
              container.appendChild(item);
            });
          })
          .catch(error => {
            console.error('Error loading notifications:', error);
            container.innerHTML = '<p class="text-center py-4 text-red-500">Error loading notifications</p>';
          });
      }
      
      // Function to process notifications data
      function processNotifications(data) {
        const container = document.getElementById('notificationsList');
        const notificationCountElement = document.getElementById('notificationCount');
        
        if (!container) return;
        
        // Clear loading message
        container.innerHTML = '';
        
        if (!data.success) {
          container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load notifications</p>';
          return;
        }
        
        const notifications = data.notifications || [];
        let unreadCount = 0;
        
        // Count unread notifications
        notifications.forEach(notification => {
          if (!notification.is_read) {
            unreadCount++;
          }
        });
        
        // Update notification count in the navbar
        if (notificationCountElement) {
          notificationCountElement.textContent = unreadCount;
        }
        
        // If no notifications
        if (notifications.length === 0) {
          container.innerHTML = '<p class="text-center py-4">No notifications</p>';
          return;
        }
        
        // Add each notification to the container
        notifications.forEach(notification => {
          // Create notification item
          const item = document.createElement('div');
          item.className = 'notification-item bg-white rounded-lg p-3 cursor-pointer transition-all hover:shadow-md mb-2';
          
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
                  <h4 class="font-semibold text-sm">${notification.title || 'Notification'}</h4>
                  <span class="text-xs text-gray-500">${notification.date || notification.created_at || ''}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">${notification.content || notification.message || ''}</p>
              </div>
            </div>
          `;
          
          // Add click handler
          item.addEventListener('click', () => {
            // If already read, do nothing
            if (notification.is_read) return;
            
            // Mark as read
            fetch('processes/mark_notification_read.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `notification_id=${notification.id}`
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Update UI to show as read
                item.classList.remove('border-l-4', 'border-blue-500', 'bg-blue-50');
                notification.is_read = true;
                
                // Update notification count
                if (notificationCountElement) {
                  const currentCount = parseInt(notificationCountElement.textContent);
                  notificationCountElement.textContent = Math.max(0, currentCount - 1);
                }
              }
            })
            .catch(error => console.error('Error marking notification as read:', error));
          });
          
          // Add to container
          container.appendChild(item);
        });
      }
    
      // Function to load and display messages
      function loadMessages() {
        const container = document.getElementById('messagesList');
        const messageCountElement = document.getElementById('messageCount');
        
        if (!container) return;
        
        // Show loading message
        container.innerHTML = '<p class="text-center py-4 text-gray-500">Loading messages...</p>';
        
        // Fetch messages from database
        fetch('api/fetch_all_messages.php')
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(data => {
            // Clear loading message
            container.innerHTML = '';
            
            if (!data.success) {
              showError('Failed to load messages. Please try again.');
              container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load messages</p>';
              return;
            }
            
            const messages = data.messages || [];
            let unreadCount = 0;
            
            // If no messages
            if (messages.length === 0) {
              container.innerHTML = '<p class="text-center py-4">No messages</p>';
              if (messageCountElement) messageCountElement.textContent = '0';
              return;
            }
            
            // Sort messages by date (newest first)
            messages.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            
            // Add each message to the container
            messages.forEach(message => {
              const isSent = message.direction === 'sent';
              const isRead = message.is_read;
              const messageId = message.id;
              
              if (!isRead) unreadCount++;
              
              // Create message item
              const item = document.createElement('div');
              item.className = `message-item p-3 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors ${!isRead ? 'bg-blue-50' : ''}`;
              item.dataset.messageId = messageId;
              
              // Store the full message data on the element
              item._messageData = message;
              
              // Format date
              const messageDate = new Date(message.created_at);
              const formattedDate = messageDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              });
              
              // Set message content
              item.innerHTML = `
                <div class="flex justify-between items-start">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center">
                      <h4 class="font-medium text-gray-900 truncate">
                        ${isSent ? 'To: ' + (message.receiver_name || 'Recipient') : message.sender_name || 'System'}
                      </h4>
                    </div>
                    <p class="text-sm font-medium text-gray-900 truncate">${message.subject || 'No subject'}</p>
                    <p class="text-sm text-gray-600 truncate">
                      ${message.content.length > 60 ? message.content.substring(0, 60) + '...' : message.content}
                    </p>
                  </div>
                  <div class="ml-2 flex-shrink-0">
                    <span class="text-xs text-gray-500">${formattedDate}</span>
                    ${!isRead ? '<span class="ml-1 inline-block w-2 h-2 rounded-full bg-blue-600"></span>' : ''}
                  </div>
                </div>
              `;
              
              // Add click handler
              item.addEventListener('click', (e) => {
                try {
                  e.preventDefault();
                  e.stopPropagation();
                  
                  // Get the message data from the stored object
                  const messageData = item._messageData || message || {};
                  
                  // Ensure we have a valid message object
                  if (!messageData || typeof messageData !== 'object') {
                    // Create a minimal valid message object to prevent errors
                    const fallbackMessage = {
                      id: 'error_' + Date.now(),
                      direction: 'received',
                      is_read: true,
                      subject: 'Error Loading Message',
                      content: 'Could not load message content. Please try again.',
                      sender_name: 'System',
                      receiver_name: 'User',
                      created_at: new Date().toISOString()
                    };
                    return showMessageModal(fallbackMessage);
                  }
                  
                  // Ensure required fields exist
                  const safeMessage = {
                    id: messageData.id || 'msg_' + Date.now(),
                    direction: messageData.direction || 'received',
                    is_read: messageData.is_read || false,
                    subject: messageData.subject || '(No subject)',
                    content: messageData.content || 'No content available',
                    sender_name: messageData.sender_name || 'Unknown Sender',
                    receiver_name: messageData.receiver_name || 'You',
                    created_at: messageData.created_at || new Date().toISOString(),
                    sender_id: messageData.sender_id,
                    receiver_id: messageData.receiver_id
                  };
                  
                  // Show the message in the modal with the safe message data
                  showMessageModal(safeMessage);
                  
                  // Only mark as read if it's a received message that's unread
                  if (!isSent && !isRead) {
                    fetch('api/mark_message_read.php', {
                      method: 'POST',
                      headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                      },
                      body: `message_id=${messageId}`
                    })
                    .then(response => {
                      if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                      }
                      return response.json();
                    })
                    .then(data => {
                      if (data && data.success) {
                        // Update UI
                        item.classList.remove('bg-blue-50');
                        message.is_read = true;
                        
                        // Update message count
                        if (messageCountElement) {
                          const currentCount = parseInt(messageCountElement.textContent) || 0;
                          messageCountElement.textContent = Math.max(0, currentCount - 1);
                        }
                      }
                    })
                    .catch(error => {
                      console.error('Error marking message as read:', error);
                    });
                  }
                } catch (error) {
                  console.error('Error in message click handler:', error);
                  showError('An error occurred while loading the message.');
                }
              });
              
              // Add to container
              container.appendChild(item);
            });
            
            // Update message count in the navbar
            if (messageCountElement) {
              messageCountElement.textContent = unreadCount;
            }
            
            // Apply current filter if any
            const activeFilter = document.querySelector('#messagesPanel button.bg-blue-600');
            if (activeFilter) {
              filterMessages(activeFilter.dataset.filter);
            }
          })
          .catch(error => {
            console.error('Error loading messages:', error);
            showError('Failed to load messages. Please try again.');
            container.innerHTML = '<p class="text-center py-4 text-red-500">Error loading messages</p>';
          });
      }
      
      // Function to filter messages
      function filterMessages(filter) {
        // Update filter button states
        document.querySelectorAll('.filter-btn').forEach(btn => {
          if (btn.dataset.filter === filter) {
            btn.classList.add('bg-blue-600', 'text-white');
            btn.classList.remove('bg-gray-200', 'hover:bg-gray-300');
          } else {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200', 'hover:bg-gray-300');
          }
        });
        
        // Filter messages based on read status
        const messageItems = document.querySelectorAll('.message-item');
        if (filter === 'all') {
          messageItems.forEach(item => item.style.display = 'block');
        } else if (filter === 'unread') {
          messageItems.forEach(item => {
            if (item.classList.contains('border-l-4')) {
              item.style.display = 'block';
            } else {
              item.style.display = 'none';
            }
          });
        }
      }
      
      // Function to toggle messages panel
      function toggleMessagesPanel() {
        const panel = document.getElementById('messagesPanel');
        const notificationPanel = document.getElementById('notificationPanel');
        
        if (panel) {
          // Hide notification panel if it's open
          if (notificationPanel && notificationPanel.style.display === 'block') {
            notificationPanel.style.display = 'none';
          }
          
          // Toggle messages panel
          if (panel.style.display === 'none' || panel.style.display === '') {
            panel.style.display = 'block';
            // Load fresh messages when opening
            loadMessages();
          } else {
            panel.style.display = 'none';
          }
        }
      }
      
      // Function to load messages from the server
      function loadMessages() {
        const container = document.getElementById('messagesList');
        const messageCountElement = document.getElementById('messageCount');
        
        if (!container) {
          console.error('Messages container not found');
          return;
        }
        
        // Show loading message
        container.innerHTML = '<p class="text-gray-500 text-center py-4">Loading messages...</p>';
        
        console.log('Fetching messages...');
        
        // Add a timestamp to prevent caching
        const timestamp = new Date().getTime();
        const url = `api/fetch_all_messages.php?t=${timestamp}`;
        
        // Fetch messages from the messages endpoint
        fetch(url, {
          method: 'GET',
          headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
          },
          credentials: 'same-origin'
        })
        .then(response => {
          console.log('Response status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
          }
          return response.json().catch(error => {
            console.error('Failed to parse JSON response:', error);
            throw new Error('Invalid JSON response from server');
          });
        })
        .then(data => {
          console.log('API Response:', data);
          
          // Clear loading message
          container.innerHTML = '';
          
          // Check if data is valid
          if (!data) {
            throw new Error('No data received from server');
          }
          
          // Process message data
          if (!data.success) {
            console.error('API returned error:', data.message || 'Unknown error');
            container.innerHTML = `
              <div class="text-center py-4">
                <p class="text-red-500">${data.message || 'Failed to load messages'}</p>
                <button onclick="loadMessages()" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                  Retry
                </button>
              </div>`;
            return;
          }
          
          // The API returns messages in the 'messages' field
          const messages = Array.isArray(data.messages) ? data.messages : [];
          console.log(`Loaded ${messages.length} messages`);
          
          // Get counts from the API response
          const counts = data.counts || {};
          const unreadCount = parseInt(counts.unread) || 0;
          
          console.log('Message counts:', counts);
          console.log('Unread count:', unreadCount);
          
          // Debug: Log first message if available
          if (messages.length > 0) {
            console.log('First message:', messages[0]);
          }
            
            // Update message count in the navbar
            if (messageCountElement) {
              messageCountElement.textContent = unreadCount;
            }
            
            // If no messages
            if (messages.length === 0) {
              container.innerHTML = `
                <div class="text-center py-8">
                  <div class="text-gray-400 mb-2">
                    <i class="fas fa-inbox text-4xl"></i>
                  </div>
                  <p class="text-gray-500">No messages yet</p>
                </div>`;
              return;
            }
            
            // Clear container before adding messages
            container.innerHTML = '';
            
            // Add each message to the container
            messages.forEach(message => {
              const isSent = message.direction === 'sent';
              const isRead = message.is_read;
              const messageId = message.id;
              
              // Create message item
              const item = document.createElement('div');
              item.className = 'message-item bg-white p-4 cursor-pointer transition-all hover:shadow-md mb-3 border-l-4 border-t border-b border-gray-200';
              item.style.borderLeftColor = isSent ? '#10B981' : '#3B82F6'; // Green for sent, blue for received
              item.dataset.id = messageId;
              item.dataset.direction = isSent ? 'sent' : 'received';
              
              // Store the full message data on the element
              item._messageData = {
                id: message.id,
                direction: message.direction,
                is_read: message.is_read,
                subject: message.subject || '(No subject)',
                content: message.content,
                sender_name: message.sender_name,
                receiver_name: message.receiver_name,
                created_at: message.created_at || message.date
              };
              
              // Style based on read status (only for received messages)
              if (!isRead && !isSent) {
                item.classList.add('bg-blue-50', 'unread');
              }
              
              // Add click event to mark as read and show message
              item.addEventListener('click', (e) => {
                try {
                  e.preventDefault();
                  e.stopPropagation();
                  
                  // Get the message data from the stored object
                  const messageData = item._messageData || message || {};
                  console.log('Message clicked:', messageData);
                  
                  // Ensure we have a valid message object
                  if (!messageData || typeof messageData !== 'object') {
                    console.error('Invalid message object:', messageData);
                    // Create a minimal valid message object to prevent errors
                    const fallbackMessage = {
                      id: 'error_' + Date.now(),
                      direction: 'received',
                      is_read: true,
                      subject: 'Error Loading Message',
                      content: 'Could not load message content. Please try again.',
                      sender_name: 'System',
                      receiver_name: 'User',
                      created_at: new Date().toISOString()
                    };
                    return showMessageModal(fallbackMessage);
                  }
                  
                  // Ensure required fields exist with proper fallbacks
                  const safeMessage = {
                    id: messageData.id || 'msg_' + Date.now(),
                    message_id: messageData.id || messageData.message_id || 'msg_' + Date.now(),
                    direction: messageData.direction || (isSent ? 'sent' : 'received'),
                    is_read: messageData.is_read || isRead || false,
                    subject: messageData.subject || message.title || '(No subject)',
                    content: messageData.content || message.message || 'No content available',
                    sender_name: messageData.sender_name || message.sender || 'Unknown Sender',
                    receiver_name: messageData.receiver_name || message.receiver || 'You',
                    created_at: messageData.created_at || message.date || new Date().toISOString(),
                    date: messageData.date || message.created_at || new Date().toISOString()
                  };
                  
                  console.log('Safe message data:', safeMessage);
                  
                  console.log('Processing message click - ID:', safeMessage.id, 'isSent:', safeMessage.direction === 'sent', 'isRead:', safeMessage.is_read);
                  
                  // Show the message in the modal with the safe message data
                  showMessageModal(safeMessage);
                  
                  // Only mark as read if it's a received message that's unread
                  if (!isSent && !isRead) {
                    console.log('Marking message as read:', messageId);
                    fetch('api/mark_message_read.php', {
                      method: 'POST',
                      headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                      },
                      body: `message_id=${messageId}`
                    })
                    .then(response => {
                      if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                      }
                      return response.json();
                    })
                    .then(data => {
                      console.log('Mark as read response:', data);
                      if (data && data.success) {
                        // Update UI
                        item.classList.remove('bg-blue-50', 'unread');
                        message.is_read = true;
                        
                        // Update message count
                        if (messageCountElement) {
                          const currentCount = parseInt(messageCountElement.textContent) || 0;
                          messageCountElement.textContent = Math.max(0, currentCount - 1);
                        }
                      } else {
                        console.error('Failed to mark message as read:', data);
                      }
                    })
                    .catch(error => {
                      // Error handled by showError function
                    });
                  }
                  
                  // Show message modal with the safe message data
                  console.log('Calling showMessageModal with message:', safeMessage);
                  showMessageModal(safeMessage);
                } catch (error) {
                  console.error('Error in message click handler:', error);
                }
              });
              
              // Determine the other party's ID and name for reply
              const otherPartyId = isSent ? message.receiver_id : message.sender_id;
              const otherPartyName = isSent ? 
                (message.receiver || 'Recipient') : 
                (message.sender || 'Sender');
              
              // Format the date for display
              const messageDate = new Date(message.created_at);
              const formattedDate = messageDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              });
              
              // Process and truncate message content for preview
              const escapeHtml = (text) => {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
              };
              
              const processContent = (content) => {
                if (!content) return 'No content';
                // Convert line breaks to <br> and escape HTML
                const withBreaks = escapeHtml(content).replace(/\n/g, '<br>');
                // Truncate if needed
                return content.length > 100 
                  ? withBreaks.substring(0, 100) + '...' 
                  : withBreaks;
              };
              
              const previewContent = processContent(message.content);
              
              // Add data attributes to the message item
              item.setAttribute('data-message-id', message.id || '');
              item.setAttribute('data-direction', isSent ? 'sent' : 'received');
              item.setAttribute('data-read', isRead ? 'true' : 'false');
              
              item.innerHTML = `
                <div class="w-full h-full flex flex-col" data-message-id="${message.id || ''}">
                  <div class="flex justify-between items-start">
                    <div class="flex-1 min-w-0">
                      <div class="flex justify-between items-center mb-1">
                        <h4 class="font-medium text-gray-900 truncate text-sm">
                          ${message.title || 'No Subject'}
                        </h4>
                        <span class="text-xs text-gray-500 whitespace-nowrap ml-2">
                          ${formattedDate}
                        </span>
                      </div>
                      <div class="flex items-center text-xs text-gray-600 mb-1">
                        <span class="font-medium">${isSent ? 'To:' : 'From:'}</span>
                        <span class="ml-1 truncate">
                          ${isSent ? (message.receiver_name || 'Recipient') : (message.sender_name || 'System')}
                        </span>
                      </div>
                      <p class="text-sm text-gray-700 line-clamp-2 mt-1">
                        ${previewContent}
                      </p>
                    </div>
                    ${!isRead && !isSent ? `
                      <span class="ml-2 flex-shrink-0 h-2 w-2 rounded-full bg-blue-500"></span>
                    ` : ''}
                    ${!isSent ? `
                    <div class="text-gray-400 ml-2 flex-shrink-0">
                      <button onclick="event.stopPropagation(); window.showComposeModal(${otherPartyId || 0}, 'Re: ${(message.subject || '').replace(/"/g, '&quot;')}');" 
                              class="p-1 rounded-full hover:bg-gray-100 hover:text-blue-500" 
                              title="Reply">
                        <i class="fas fa-reply"></i>
                      </button>
                    </div>
                    ` : ''}
                  </div>
                </div>`;
                
              // Store the full message object on the element
              item._messageData = message;
              
              container.appendChild(item);
            });
            
            // Apply current filter
            const activeBtn = document.querySelector('#messagesPanel button.bg-blue-500');
            if (activeBtn) {
              filterMessages(activeBtn.id === 'allMessagesBtn' ? 'all' : 'unread');
            }
          })
          .catch(error => {
            console.error('Error loading messages:', error);
            container.innerHTML = '<p class="text-center py-4 text-red-500">Error loading messages</p>';
          });
      }
      
      // Function to filter messages by read/unread status
      function filterMessages(filter) {
        const allBtn = document.getElementById('allMessagesBtn');
        const unreadBtn = document.getElementById('unreadMessagesBtn');
        
        // Update button styles
        if (filter === 'all') {
          allBtn.classList.remove('bg-gray-200', 'text-gray-700');
          allBtn.classList.add('bg-blue-500', 'text-white');
          unreadBtn.classList.remove('bg-blue-500', 'text-white');
          unreadBtn.classList.add('bg-gray-200', 'text-gray-700');
        } else {
          unreadBtn.classList.remove('bg-gray-200', 'text-gray-700');
          unreadBtn.classList.add('bg-blue-500', 'text-white');
          allBtn.classList.remove('bg-blue-500', 'text-white');
          allBtn.classList.add('bg-gray-200', 'text-gray-700');
        }
        
        // Filter messages in the list
        const messageItems = document.querySelectorAll('.message-item');
        if (filter === 'all') {
          messageItems.forEach(item => item.style.display = 'block');
        } else if (filter === 'unread') {
          messageItems.forEach(item => {
            if (item.classList.contains('unread')) {
              item.style.display = 'block';
            } else {
              item.style.display = 'none';
            }
          });
        }
      }
      
      // Function to show message modal with full message content
    function showMessageModal(message) {
        // Create a default test message if none provided or if message is null/undefined
        if (message === null || message === undefined) {
          message = {
            id: 'test_' + Date.now(),
            direction: 'received',
            is_read: false,
            subject: 'Test Message',
            content: 'This is a test message. The message content could not be loaded.',
            sender_name: 'System',
            receiver_name: 'User',
            created_at: new Date().toISOString()
          };
        } else if (typeof message !== 'object') {
          showError('Invalid message format');
          return; // Exit if message is not an object
        }
        
        const modal = document.getElementById('messageModal');
        if (!modal) {
          showError('Error loading message');
          return;
        }
        
        try {
          // Make the modal visible
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          modal.style.display = 'flex';
          modal.style.pointerEvents = 'auto';
          
          // Set aria-hidden to false when modal is shown
          modal.setAttribute('aria-hidden', 'false');
          
          // Force reflow to ensure the browser processes the display change
          void modal.offsetHeight;
          
          // Start the opacity transition
          modal.classList.remove('opacity-0');
          modal.classList.add('opacity-100');
          
          // Prevent body scrolling when modal is open
          document.body.style.overflow = 'hidden';
          
          // Set focus to the modal dialog
          const modalDialog = modal.querySelector('.modal-content');
          if (modalDialog) {
            modalDialog.setAttribute('tabindex', '-1');
            modalDialog.focus();
          }
          
          // Trap focus inside the modal
          trapFocus(modal);
          
        } catch (error) {
          console.error('Error in showMessageModal:', error);
        }
        
        const isSent = message.direction === 'sent';
        
        // Get all modal elements
        const modalTitle = modal.querySelector('#messageModalTitle');
        const modalContent = modal.querySelector('#messageModalContent');
        const modalFrom = modal.querySelector('#messageModalFrom');
        const modalTo = modal.querySelector('#messageModalTo');
        const modalDate = modal.querySelector('#messageModalDate');
        const modalSubject = modal.querySelector('#messageModalSubject');
        const modalReplyBtn = modal.querySelector('#replyButton');
        const modalAttachments = modal.querySelector('#messageAttachments');
        
        // Format the date
        const messageDate = new Date(message.created_at || message.date || new Date());
        const formattedDate = messageDate.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
        
        // Set message details
        if (modalTitle) modalTitle.textContent = message.subject || '(No Subject)';
        if (modalFrom) modalFrom.textContent = isSent ? 'You' : (message.sender_name || 'Unknown Sender');
        if (modalTo) modalTo.textContent = isSent ? (message.receiver_name || 'Unknown Recipient') : 'You';
        if (modalDate) modalDate.textContent = formattedDate;
        if (modalSubject) modalSubject.textContent = message.subject || '(No Subject)';
        if (modalContent) modalContent.innerHTML = message.content || 'No content';
        
        // Store message ID for reply functionality
        if (modalReplyBtn) {
            modalReplyBtn.dataset.messageId = message.id || message.message_id;
            modalReplyBtn.onclick = function() {
                const messageId = this.dataset.messageId;
                const replyText = document.getElementById('messageReply').value.trim();
                
                if (!replyText) {
                    alert('Please enter a reply message');
                    return;
                }
                
                if (!messageId) {
                    console.error('No message ID found');
                    alert('Error: Unable to identify the message to reply to.');
                    return;
                }
                
                // Get the reply button and show loading state
                const replyButton = document.getElementById('replyButton');
                const originalButtonText = replyButton.innerHTML;
                replyButton.disabled = true;
                replyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                
                // Create form data for the reply
                const formData = new FormData();
                formData.append('parent_id', messageId);
                formData.append('content', replyText);
                
                // Send the reply as form data
                fetch('processes/send_reply.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        const successMsg = document.createElement('div');
                        successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
                        successMsg.textContent = 'Reply sent successfully!';
                        document.body.appendChild(successMsg);
                        
                        // Remove success message after 3 seconds
                        setTimeout(() => {
                            if (successMsg.parentNode) {
                                successMsg.remove();
                            }
                        }, 3000);
                        
                        // Clear the reply textarea
                        document.getElementById('messageReply').value = '';
                        
                        // Close the modal
                        closeMessageModal();
                        
                        // Reload messages to show the new reply
                        loadMessages();
                    } else {
                        throw new Error(data.message || 'Failed to send reply');
                    }
                })
                .catch(error => {
                    console.error('Error sending reply:', error);
                    
                    // Show error message
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
                    errorMsg.textContent = 'Failed to send reply: ' + (error.message || 'Please try again later.');
                    document.body.appendChild(errorMsg);
                    
                    // Remove error message after 5 seconds
                    setTimeout(() => {
                        if (errorMsg.parentNode) {
                            errorMsg.remove();
                        }
                    }, 5000);
                })
                .finally(() => {
                    // Restore button state
                    replyButton.disabled = false;
                    replyButton.innerHTML = originalButtonText;
                });
            };
        }
        
        // Clear any existing attachments
        if (modalAttachments) modalAttachments.innerHTML = '';
        
        // Show the modal
        modal.classList.remove('hidden');
        // Force reflow to ensure the browser registers the display change
        void modal.offsetHeight;
        // Trigger the transition
        setTimeout(() => {
          modal.classList.remove('opacity-0', 'pointer-events-none');
          modal.classList.add('opacity-100', 'visible');
        }, 10);
        
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
        
        // Set focus on the modal for accessibility
        modal.setAttribute('aria-hidden', 'false');
        modal.focus();
        
        // Mark message as read if it's not sent by the current user
        if (!isSent && !message.is_read) {
          const messageId = message.message_id || message.id;
          if (messageId) {
            markMessageAsRead(messageId);
          } else {
            console.warn('Cannot mark message as read: No message ID found', message);
          }
        }
        
        // Set up reply button
        if (modalReplyBtn) {
          // Remove any existing click handlers to prevent duplicates
          const newReplyBtn = modalReplyBtn.cloneNode(true);
          modalReplyBtn.parentNode.replaceChild(newReplyBtn, modalReplyBtn);
          
          // Store the message ID in the modal for the reply
          // Use message_id or id, whichever is available
          const messageId = message.message_id || message.id;
          if (messageId) {
            modal.dataset.messageId = messageId;
          } else {
            console.error('No message ID found in message object:', message);
            alert('Error: Unable to identify the message to reply to.');
            return;
          }
          
          // Add click handler for the reply button
          newReplyBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const replyText = document.getElementById('messageReply').value.trim();
            if (!replyText) {
              alert('Please enter your reply message');
              return;
            }
            
            // Call the sendReply function
            sendReply();
          };
        }
        
        // Clear any previous attachments
        if (modalAttachments) modalAttachments.innerHTML = '';
        
        // Set message content with proper formatting
        if (modalContent) {
          let content = message.content || 'No content';
          // Convert newlines to <br> and preserve other HTML
          content = content
            .replace(/\n/g, '<br>')
            .replace(/<\/p>\s*<p[^>]*>/g, '</p><p>')
            .replace(/<p[^>]*>/g, '<p class="mb-4">')
            .replace(/<br\s*\/?>\s*<br\s*\/?>/g, '<br>');
          modalContent.innerHTML = content;
        }
        
        // Store message ID and recipient for reply
        modal.dataset.messageId = message.id;
        modal.dataset.recipientId = isSent ? message.receiver_id : message.sender_id;
        modal.dataset.recipientName = isSent ? 
          (message.receiver_name || 'Recipient') : 
          (message.sender_name || 'Sender');
          
        // Ensure the reply textarea is cleared when showing a message
        const replyTextarea = document.getElementById('messageReply');
        if (replyTextarea) {
          replyTextarea.value = '';
        }
        
        // Show the modal with animation
        modal.style.display = 'flex';
        // Force reflow to ensure the display property is applied
        void modal.offsetHeight;
        // Add the 'show' class to trigger the opacity transition
        modal.style.opacity = '1';
        
        // Focus trap for accessibility
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        // Focus the close button when modal opens
        const closeButton = modal.querySelector('[onclick="closeMessageModal()"]');
        if (closeButton) {
          setTimeout(() => closeButton.focus(), 10);
        }
        
        // Mark as read if this is a received message that's unread
        if (!isSent && !message.is_read) {
          const messageId = message.id;
          fetch('api/mark_message_read.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `message_id=${messageId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update the message item in the list
              const messageItem = document.querySelector(`.message-item[data-id="${messageId}"]`);
              if (messageItem) {
                messageItem.classList.remove('bg-blue-50', 'unread');
                const unreadDot = messageItem.querySelector('.bg-blue-500');
                if (unreadDot) {
                  unreadDot.remove();
                }
              }
              
              // Update unread count
              const messageCountElement = document.getElementById('messageCount');
              if (messageCountElement) {
                const currentCount = parseInt(messageCountElement.textContent);
                messageCountElement.textContent = Math.max(0, currentCount - 1);
              }
            }
          })
          .catch(error => console.error('Error marking message as read:', error));
        }
      }
      
      // Function to close message modal with animation
      window.closeMessageModal = function() {
        const modal = document.getElementById('messageModal');
        if (!modal) {
          console.warn('Modal element not found when trying to close');
          return;
        }
        
        console.log('Closing message modal');
        
        // Start fade out animation
        modal.classList.remove('opacity-100', 'visible');
        modal.classList.add('opacity-0');
        
        // After animation completes, hide the modal
        setTimeout(() => {
          modal.classList.add('hidden');
          modal.classList.remove('flex', 'active');
          modal.style.display = 'none';
          modal.style.pointerEvents = 'none';
          
          // Reset modal state and restore scrolling
          modal.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = 'auto';
          
          // Return focus to the element that opened the modal
          const activeElement = document.activeElement;
          if (activeElement && activeElement.closest('.message-item')) {
            activeElement.focus();
          }
          
          console.log('Modal closed successfully');
        }, 300); // Match this with your CSS transition duration
      }
      
      // Initialize panels when the page loads
      document.addEventListener('DOMContentLoaded', function() {
        // Make sure notification panel is hidden by default
        const notificationPanel = document.getElementById('notificationPanel');
        if (notificationPanel) {
          notificationPanel.classList.add('hidden');
          notificationPanel.style.display = 'none';
        }
        
        // Make sure messages panel is hidden by default
        const messagesPanel = document.getElementById('messagesPanel');
        if (messagesPanel) {
          messagesPanel.classList.add('hidden');
          messagesPanel.style.display = 'none';
        }
        
        // Load messages when page loads
        loadMessages();
        
        // Load initial notification count
        const notificationCountElement = document.getElementById('notificationCount');
        if (notificationCountElement) {
          // Initially set to 0, will be updated when API call completes
          notificationCountElement.textContent = '0';
          
          // Fetch count from server
          fetch('api/get_notification_count.php')
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                notificationCountElement.textContent = data.count || '0';
              }
            })
            .catch(error => console.error('Error loading notification count:', error));
        }
        
        // Load initial message count
        const messageCountElement = document.getElementById('messageCount');
        if (messageCountElement) {
          // Initially set to 0, will be updated when API call completes
          messageCountElement.textContent = '0';
          
          // Fetch messages to count unread
          fetch('api/get_patient_messages.php')
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                const unreadCount = data.messages.filter(m => !m.is_read).length;
                messageCountElement.textContent = unreadCount;
              }
            })
            .catch(error => console.error('Error loading message count:', error));
        }
        loadMessages();
      });
      </script>
  <style>
    
    /* Message Modal Styles */
    #messageModal {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      margin: 0 !important;
      padding: 20px !important;
      z-index: 9999 !important;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1000;
      display: flex; /* Changed from none to flex */
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease-in-out;
      background-color: rgba(0, 0, 0, 0.5);
      overflow-y: auto;
      padding: 1rem;
      pointer-events: none;
    }
    
    #messageModal.hidden {
      display: none;
    }
    
    #messageModal.visible {
      pointer-events: auto;
    }
    
    #messageModal.opacity-100 {
      opacity: 1;
    }
    
    #messageModal.opacity-0 {
      opacity: 0;
    }
    
    #messageModal > div {
      transform: translateY(-20px);
      transition: transform 0.3s ease-in-out;
    }
    
    #messageModal.visible > div {
      transform: translateY(0);
    }
    
    #messageModal .modal-content {
      background: white;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      width: 90%;
      max-width: 600px;
      margin: 20px auto;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      transform: translateY(-20px);
      opacity: 0;
      transition: transform 0.3s ease, opacity 0.3s ease;
      overflow: hidden;
    }
    
    #messageModal:not(.hidden) .modal-content {
      transform: translateY(0);
    }
    
    /* Scrollbar styling for message content */
    .message-content {
      scrollbar-width: thin;
      scrollbar-color: #cbd5e0 #f7fafc;
    }
    
    .message-content::-webkit-scrollbar {
      width: 6px;
    }
    
    .message-content::-webkit-scrollbar-track {
      background: #f7fafc;
      border-radius: 3px;
    }
    
    .message-content::-webkit-scrollbar-thumb {
      background-color: #cbd5e0;
      border-radius: 3px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
      .message-modal-content {
        width: 95%;
        margin: 0 10px;
      }
    }
    
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
      
      <!-- Notification Icon -->
      <div class="relative cursor-pointer p-2 mr-2" id="notificationIcon" onclick="toggleNotificationPanel()">
        <i class="fas fa-bell text-white"></i>
        <span id="notificationCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">0</span>
      </div>
      
      
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
   
   <!-- Notification Panel -->
   <div id="notificationPanel" class="fixed right-4 top-16 bg-white rounded-lg shadow-lg border border-gray-200 w-80 z-50 hidden" style="display: none;">
    <div class="p-3 border-b border-gray-200 flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
     <h3 class="font-semibold">Notifications</h3>
     <button onclick="toggleNotificationPanel()" class="text-white hover:text-gray-200">
      <i class="fas fa-times"></i>
     </button>
    </div>
    <div id="notificationsList" class="overflow-y-auto max-h-96 p-2">
     <p class="text-gray-500 text-center py-4" id="notificationsLoadingPanel">Loading notifications...</p>
    </div>
    <div class="p-3 border-t border-gray-200 text-center bg-gray-50 rounded-b-lg">
     <button onclick="markAllNotificationsAsRead()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
      <i class="fas fa-check-double mr-1"></i> Mark all as read
     </button>
    </div>
   </div>
   

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
           src="assets/images/logo.jpg"
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
         <span class="editable-field select-text" id="age" data-type="number">Not specified</span>
        </div>
        <div>
         <span class="font-semibold select-none">Gender:</span>
         <span class="editable-field select-text" id="gender" data-type="select" data-options="Male,Female,Other,Prefer not to say">Not specified</span>
        </div>
        <div>
         <span class="font-semibold select-none">Marital Status:</span>
         <span class="editable-field select-text" id="maritalStatus" data-type="select" data-options="Single,Married,Divorced,Widowed,Separated,Other">Not specified</span>
        </div>
       </div>
       <p class="mb-4 text-sm text-gray-900">
        <span class="font-semibold select-none">Bio:</span>
        <span class="editable-field select-text" id="bio" data-type="textarea">No bio provided</span>
       </p>
       <p class="mb-2 text-sm text-gray-900">
        <span class="font-semibold select-none">Address:</span>
        <span class="editable-field select-text" id="address" data-type="text">No address provided</span>
       </p>

       <!-- Medical Information Section -->
       <div class="mt-6 mb-4">
           <h3 class="text-lg font-semibold text-gray-900 mb-4">Medical Information</h3>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Blood Type:</span>
               <span class="editable-field select-text" id="blood_type" data-type="select" data-options="A+,A-,B+,B-,AB+,AB-,O+,O-,Unknown">Not specified</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Allergies:</span>
               <span class="editable-field select-text" id="allergies" data-type="textarea">None reported</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Current Medications:</span>
               <span class="editable-field select-text" id="current_medications" data-type="textarea">None reported</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Medical History:</span>
               <span class="editable-field select-text" id="medical_history" data-type="textarea">No history provided</span>
           </p>
       </div>

       <!-- Emergency Contact Information -->
       <div class="mt-6 mb-4">
           <h3 class="text-lg font-semibold text-gray-900 mb-4">Emergency Contact</h3>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Name:</span>
               <span class="editable-field select-text" id="emergencyContactName" data-type="text">Not provided</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Phone Number:</span>
               <span class="editable-field select-text" id="emergencyContactPhone" data-type="tel" data-pattern="[0-9+\-() ]+">Not provided</span>
           </p>
           
           <p class="mb-2 text-sm text-gray-900">
               <span class="font-semibold select-none">Relationship:</span>
               <span class="editable-field select-text" id="emergencyContactRelationship" data-type="select" data-options="Parent,Spouse,Child,Sibling,Relative,Friend,Other">Not specified</span>
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
      id="messages"
      class="bg-white rounded-lg shadow-md flex flex-col"
      style="height: 80vh; min-height: 400px; max-height: 90vh; display: flex; flex-direction: column;"
     >
      <div class="p-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex justify-between items-center">
          <h3 class="text-gray-900 font-semibold text-base">Messages</h3>
          <button id="composeMessageBtn" onclick="window.showComposeModal()" class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
            <i class="fas fa-pen mr-1"></i> Compose
          </button>
        </div>
      </div>
      <div class="flex-1 overflow-y-auto" style="min-height: 200px;">
        <div id="messagesList" class="p-4 space-y-3">
          <p class="text-gray-500 text-center py-4" id="messagesLoading">Loading messages...</p>
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
  
  <!-- Message Details Modal -->
  <div id="messageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 transition-opacity duration-300 opacity-0 pointer-events-none hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-transform duration-300 -translate-y-4">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-4 border-b border-gray-200">
        <div>
          <h3 id="messageModalTitle" class="text-xl font-semibold text-gray-900">Message Details</h3>
          <div class="flex items-center text-sm text-gray-500 mt-1">
            <span id="messageModalDate"></span>
          </div>
        </div>
        <button 
          onclick="closeMessageModal()" 
          class="text-gray-400 hover:text-gray-600 focus:outline-none"
          aria-label="Close message"
        >
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Message Details -->
      <div class="overflow-y-auto flex-1 p-6">
        <!-- Sender and Recipient Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <p class="text-sm font-medium text-gray-500">From:</p>
            <p id="messageModalFrom" class="text-gray-900">Loading...</p>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500">To:</p>
            <p id="messageModalTo" class="text-gray-900">Loading...</p>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500">Subject:</p>
            <p id="messageModalSubject" class="text-gray-900 font-medium">Loading...</p>
          </div>
        </div>
        
        <!-- Message Content -->
        <div class="bg-gray-50 rounded-lg p-4">
          <div id="messageModalContent" class="prose max-w-none text-gray-800">
            Loading message content...
          </div>
        </div>
        
        <!-- Attachments if any -->
        <div id="messageAttachments" class="mt-4">
          <!-- Attachments will be shown here if available -->
        </div>
      </div>
      
      <!-- Reply Section -->
      <div class="border-t border-gray-200 p-4 bg-gray-50">
        <textarea
          id="messageReply"
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
          rows="3"
          placeholder="Type your reply..."
        ></textarea>
        <div class="flex justify-end mt-3 space-x-2">
          <button
            onclick="closeMessageModal()"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Close
          </button>
          <button
            onclick="sendReply()"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            id="replyButton"
          >
            <i class="fas fa-reply mr-2"></i> Reply
          </button>
        </div>
      </div>
    </div>
  </div>

  <footer class="bg-gray-800 text-white py-2 rounded-t-lg shadow-md text-center mt-8">
   <p class="text-sm"> 2023 DocNow. All rights reserved.</p>
  </footer>

  <!-- Compose Message Modal -->
  <div id="composeMessageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
      <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">Compose Message</h3>
        <button type="button" onclick="hideComposeModal()" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="composeMessageForm" class="px-6 py-4">
        <div class="mb-4">
          <label for="recipient" class="block text-sm font-medium text-gray-700 mb-1">Recipient</label>
          <select id="recipient" name="receiver_id" class="w-full rounded-md border-gray-300 shadow-sm p-2 border" required>
            <option value="">Select recipient...</option>
            <?php
            // Get list of doctors
            $conn = new mysqli("localhost", "root", "", "docnow_db");
            if (!$conn->connect_error) {
              $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE role = 'doctor'");
              $stmt->execute();
              $result = $stmt->get_result();
              while ($row = $result->fetch_assoc()) {
                echo "<option value=\"" . $row['user_id'] . "\">" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</option>";
              }
              $conn->close();
            }
            ?>
          </select>
        </div>
        <div class="mb-4">
          <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
          <input type="text" id="subject" name="subject" class="w-full rounded-md border-gray-300 shadow-sm p-2 border" placeholder="Enter subject">
        </div>
        <div class="mb-4">
          <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
          <textarea id="message_content" name="content" rows="5" class="w-full rounded-md border-gray-300 shadow-sm p-2 border" placeholder="Type your message here..." required></textarea>
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-lg">
          <button type="button" onclick="hideComposeModal()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 transition-colors mr-2">
            Cancel
          </button>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
            <span id="sendButtonText">Send Message</span>
            <span id="sendButtonSpinner" class="hidden ml-2">
              <i class="fas fa-spinner fa-spin"></i>
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Global functions for compose modal
    window.showComposeModal = function(recipientId, replySubject) {
      const modal = document.getElementById('composeMessageModal');
      if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        
        // If a recipient ID was provided, select it in the dropdown
        if (recipientId) {
          const recipientSelect = document.getElementById('recipient');
          if (recipientSelect) {
            // Try to find and select the option
            const option = Array.from(recipientSelect.options).find(opt => opt.value === recipientId.toString());
            if (option) {
              recipientSelect.value = recipientId.toString();
            }
          }
        }
        
        // If a subject for reply was provided, set it in the subject field
        if (replySubject) {
          const subjectField = document.getElementById('subject');
          if (subjectField) {
            subjectField.value = replySubject;
          }
        }
        
        // Clear message field each time modal is opened
        const messageField = document.getElementById('message_content');
        if (messageField) {
          messageField.value = '';
        }
      }
    };
    
    // Function to hide compose modal in global scope
    window.hideComposeModal = function() {
      const modal = document.getElementById('composeMessageModal');
      if (modal) {
        modal.style.display = 'none';
      }
    };

    // Function to show error message to user
    function showError(message) {
        // Remove any existing error messages
        const existingError = document.querySelector('.global-error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Create and show new error message
        const errorMsg = document.createElement('div');
        errorMsg.className = 'global-error-message fixed top-4 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
        errorMsg.textContent = message;
        document.body.appendChild(errorMsg);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorMsg.parentNode) {
                errorMsg.remove();
            }
        }, 5000);
    }
    
    // Helper function to trap focus inside modal
    function trapFocus(modal) {
      const focusableElements = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
      const focusableContent = modal.querySelectorAll(focusableElements);
      const firstFocusableElement = focusableContent[0];
      const lastFocusableElement = focusableContent[focusableContent.length - 1];

      // Focus first element by default
      firstFocusableElement.focus();

      // Handle tab key
      modal.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab') return;

        if (e.shiftKey) {
          if (document.activeElement === firstFocusableElement) {
            e.preventDefault();
            lastFocusableElement.focus();
          }
        } else {
          if (document.activeElement === lastFocusableElement) {
            e.preventDefault();
            firstFocusableElement.focus();
          }
        }
      });
    }

    // Global error handler - only show errors for unhandled errors
    window.addEventListener('error', function(event) {
        // Log the error to console for debugging
        console.error('Global error:', event.error || event.message, event);
        
        
    });
    
    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled rejection:', event.reason);
        // Don't show UI errors for unhandled promise rejections
        event.preventDefault();
    });
    
    // Function to send a reply to a message
    function sendReply() {
      const modal = document.getElementById('messageModal');
      const replyText = document.getElementById('messageReply').value.trim();
      const messageId = modal.dataset.messageId;
      
      if (!replyText) {
        alert('Please enter a reply message');
        return;
      }
      
      if (!messageId) {
        console.error('No message ID found');
        alert('Error: Unable to identify the message to reply to.');
        return;
      }
      
      // Get the reply button and show loading state
      const replyButton = document.getElementById('replyButton');
      const originalButtonText = replyButton.innerHTML;
      replyButton.disabled = true;
      replyButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
      
      // Create form data for the reply
      const formData = new FormData();
      formData.append('parent_id', messageId);
      formData.append('content', replyText);
      
      // Send the reply as form data
      fetch('processes/send_reply.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          // Show success message
          const successMsg = document.createElement('div');
          successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
          successMsg.textContent = 'Reply sent successfully!';
          document.body.appendChild(successMsg);
          
          // Remove success message after 3 seconds
          setTimeout(() => {
            if (successMsg.parentNode) {
              successMsg.remove();
            }
          }, 3000);
          
          // Clear the reply textarea
          document.getElementById('messageReply').value = '';
          
          // Close the modal
          closeMessageModal();
          
          // Reload messages to show the new reply
          loadMessages();
        } else {
          throw new Error(data.error || 'Failed to send reply');
        }
      })
      .catch(error => {
        console.error('Error sending reply:', error);
        
        // Show error message
        const errorMsg = document.createElement('div');
        errorMsg.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
        errorMsg.textContent = 'Failed to send reply: ' + (error.message || 'Please try again later.');
        document.body.appendChild(errorMsg);
        
        // Remove error message after 5 seconds
        setTimeout(() => {
          if (errorMsg.parentNode) {
            errorMsg.remove();
          }
        }, 5000);
      })
      .finally(() => {
        // Restore button state
        replyButton.disabled = false;
        replyButton.innerHTML = originalButtonText;
      });
    }
    
    // Click handler for message items
    function handleMessageItemClick(e) {
        // Only proceed if the click was on a message item
        const messageItem = e.target.closest('.message-item');
        if (!messageItem) return true; // Allow the click to propagate
        
        try {
            e.preventDefault();
            e.stopPropagation();
            
            // Don't prevent default for interactive elements
            if (e.target.closest('button, [role="button"], [tabindex], a[href]')) {
                return true;
            }
            
            // Get the message data from the stored object
            const messageData = messageItem._messageData;
            if (!messageData) {
                console.error('No message data found for clicked message');
                return false;
            }
            
            // Show the message in the modal
            showMessageModal(messageData);
            return false;
            
        } catch (error) {
            console.error('Error handling message click:', error);
            return false;
        }
    }
    
    // Add click handler to the messages container instead of the whole document
    const messagesContainer = document.getElementById('messagesList');
    if (messagesContainer) {
        messagesContainer.addEventListener('click', handleMessageItemClick);
    }
    
    // Add event listener to the compose form
    function initializeComposeForm() {
      const composeForm = document.getElementById('composeMessageForm');
      if (composeForm) {
        // Add debug info before setting up the event listener
        console.log('Compose message form found:', composeForm);
        
        composeForm.addEventListener('submit', function(e) {
          e.preventDefault();
          console.log('Form submitted');
          
          // Display loading indicator
          const loadingMsg = document.createElement('div');
          loadingMsg.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded shadow-lg z-50';
          loadingMsg.textContent = 'Sending message...';
          document.body.appendChild(loadingMsg);
          
          const formData = new FormData(this);
          
          // Make sure we have required fields
          const receiver = document.getElementById('recipient');
          const subject = document.getElementById('subject');
          const messageContent = document.getElementById('message_content');
          
          if (!receiver.value) {
            alert('Please select a recipient');
            loadingMsg.remove();
            return;
          }
          
          if (!subject.value) {
            alert('Please enter a subject');
            loadingMsg.remove();
            return;
          }
          
          if (!messageContent.value) {
            alert('Please enter a message');
            loadingMsg.remove();
            return;
          }
          
          // Log form data for debugging
          const formDataObj = {};
          for (let [key, value] of formData.entries()) {
            formDataObj[key] = value;
          }
          console.log('Sending message with data:', formDataObj);
          
          // Send the form data
          fetch('processes/send_message.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            // First log the raw response
            return response.text().then(text => {
              console.log('Raw server response:', text);
              
              // Try to parse as JSON if possible
              try {
                const data = JSON.parse(text);
                // Check for error even if response.ok is true
                if (!response.ok) {
                  console.error('Server error:', data);
                  throw new Error('HTTP error! Status: ' + response.status);
                }
                return data;
              } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error(response.ok ? 'Invalid JSON response' : 'HTTP error! Status: ' + response.status);
              }
            });
          })
          .then(data => {
            // Remove loading message
            loadingMsg.remove();
            
            if (data.success) {
              // Show success message
              const successMsg = document.createElement('div');
              successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
              successMsg.textContent = 'Message sent successfully!';
              document.body.appendChild(successMsg);
              setTimeout(() => successMsg.remove(), 3000);
              
              // Clear form
              composeForm.reset();
              
              // Hide modal
              hideComposeModal();
              
              // Reload messages
              loadMessages();
            } else {
              const errorMsg = document.createElement('div');
              errorMsg.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
              errorMsg.textContent = 'Failed to send message: ' + (data.message || 'Unknown error');
              document.body.appendChild(errorMsg);
              setTimeout(() => errorMsg.remove(), 3000);
            }
          })
          .catch(error => {
            console.error('Error sending message:', error);
            const errorMsg = document.createElement('div');
            errorMsg.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
            errorMsg.textContent = 'Failed to send message. Please try again later.';
            document.body.appendChild(errorMsg);
            setTimeout(() => errorMsg.remove(), 3000);
          });
        });
      }
    }
    
    // Initialize the compose form when the DOM is loaded
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializeComposeForm);
    } else {
      initializeComposeForm();
    }
  </script>
 </body>
</html>