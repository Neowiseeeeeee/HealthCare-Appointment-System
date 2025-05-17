/**
 * Dashboard Update JavaScript Functions
 * Handles dynamic functionality for patient dashboard data containers
 */

// Wait until DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard data
    fetchAppointments();
    fetchNotificationsAndMessages();
});

/**
 * Fetch patient appointments from the database
 */
function fetchAppointments() {
    // Show loading state
    document.getElementById('appointmentsContainer').innerHTML = '<p class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading appointments...</p>';
    
    // Fetch appointment data from API
    fetch('api/fetch_appointments.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Check if there are upcoming appointments
                if (data.upcoming && data.upcoming.length > 0) {
                    displayUpcomingAppointments(data.upcoming);
                } else {
                    document.getElementById('appointmentsContainer').innerHTML = '<p class="text-center py-4 text-gray-500">No upcoming appointments</p>';
                }
                
                // Check if there is appointment history
                if (data.history && data.history.length > 0) {
                    displayAppointmentHistory(data.history);
                } else {
                    document.getElementById('appointmentHistoryContainer').innerHTML = '<p class="text-center py-4 text-gray-500">No appointment history</p>';
                }
            } else {
                throw new Error(data.message || 'Failed to load appointments');
            }
        })
        .catch(error => {
            console.error('Error fetching appointments:', error);
            document.getElementById('appointmentsContainer').innerHTML = 
                '<p class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> ' + 
                'Could not load appointments. Please try again later.</p>';
        });
}

/**
 * Display upcoming appointments in the dashboard
 */
function displayUpcomingAppointments(appointments) {
    const container = document.getElementById('appointmentsContainer');
    let html = '';
    
    // Loop through upcoming appointments and create HTML
    appointments.forEach(apt => {
        // Format date
        const aptDate = new Date(apt.appointment_date);
        const formattedDate = aptDate.toLocaleDateString('en-US', { 
            weekday: 'short', 
            month: 'short', 
            day: 'numeric',
            year: 'numeric'
        });
        
        // Format time
        const formattedTime = aptDate.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit'
        });
        
        // Get status color class
        const statusClass = getStatusColorClass(apt.status);
        
        // Create appointment card
        html += `
        <div class="bg-white shadow rounded-lg p-4 mb-4">
            <div class="flex justify-between items-start">
                <div>
                    <h4 class="font-medium text-lg">${apt.service || 'Consultation'}</h4>
                    <p class="text-gray-600">Dr. ${apt.doctor_name}</p>
                    <div class="flex items-center mt-2">
                        <i class="far fa-calendar-alt text-indigo-500 mr-2"></i>
                        <span>${formattedDate} at ${formattedTime}</span>
                    </div>
                    <div class="mt-2">
                        <span class="px-2 py-1 text-xs rounded-full ${statusClass}">${apt.status}</span>
                    </div>
                </div>
                <div class="flex flex-col space-y-2">
                    <button onclick="cancelAppointment(${apt.appointment_id})" 
                        class="text-xs text-red-500 hover:text-red-700 bg-red-100 hover:bg-red-200 px-3 py-1 rounded-full transition-colors">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button onclick="rescheduleAppointment(${apt.appointment_id})" 
                        class="text-xs text-indigo-500 hover:text-indigo-700 bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition-colors">
                        <i class="fas fa-calendar-alt mr-1"></i> Reschedule
                    </button>
                </div>
            </div>
        </div>`;
    });
    
    // Update container with appointment cards
    container.innerHTML = html;
}

/**
 * Display appointment history in the dashboard
 */
function displayAppointmentHistory(appointments) {
    const container = document.getElementById('appointmentHistoryContainer');
    
    if (!container) {
        console.error('Appointment history container not found');
        return;
    }
    
    let html = '<div class="space-y-3">';
    
    // Loop through appointment history and create HTML
    appointments.forEach(apt => {
        // Format date
        const aptDate = new Date(apt.appointment_date);
        const formattedDate = aptDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: 'numeric'
        });
        
        // Get status color class
        const statusClass = getStatusColorClass(apt.status);
        
        // Create appointment history item
        html += `
        <div class="flex items-center justify-between p-3 bg-white rounded-lg shadow">
            <div class="flex items-center">
                <div class="mr-3">
                    <i class="fas fa-stethoscope text-xl text-gray-400"></i>
                </div>
                <div>
                    <h4 class="font-medium">${apt.service || 'Consultation'} with Dr. ${apt.doctor_name}</h4>
                    <p class="text-sm text-gray-500">${formattedDate}</p>
                </div>
            </div>
            <span class="px-3 py-1 text-xs rounded-full ${statusClass}">${apt.status}</span>
        </div>`;
    });
    
    html += '</div>';
    
    // Update container with appointment history
    container.innerHTML = html;
}

/**
 * Get CSS color class based on appointment status
 */
function getStatusColorClass(status) {
    switch(status.toLowerCase()) {
        case 'confirmed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'completed':
            return 'bg-blue-100 text-blue-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'rescheduled':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Fetch notifications and messages from the database
 */
function fetchNotificationsAndMessages() {
    // Show loading state for notifications
    const notificationsContainer = document.getElementById('messagesNotificationsList');
    if (notificationsContainer) {
        notificationsContainer.innerHTML = '<p class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading notifications...</p>';
    } else {
        console.error('Notifications container not found with ID: messagesNotificationsList');
    }
    
    // Show loading state for messages if container exists
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.innerHTML = '<p class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading messages...</p>';
    }
    
    // Fetch notifications and messages from API
    fetch('api/fetch_notifications.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Process notifications and messages
                const notifications = data.notifications || [];
                const messages = data.messages || [];
                
                displayNotificationsAndMessages(notifications, messages);
            } else {
                throw new Error(data.message || 'Failed to load notifications');
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            document.getElementById('notificationsContainer').innerHTML = 
                '<p class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> ' + 
                'Could not load notifications. Please try again later.</p>';
            
            if (messagesContainer) {
                messagesContainer.innerHTML = 
                    '<p class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> ' + 
                    'Could not load messages. Please try again later.</p>';
            }
        });
}

/**
 * Display notifications and messages in the dashboard
 */
function displayNotificationsAndMessages(notifications, messages) {
    // Handle notifications
    const notificationsContainer = document.getElementById('messagesNotificationsList');
    
    if (notificationsContainer && notifications.length > 0) {
        let notificationsHtml = '<div class="space-y-3">';
        
        // Loop through notifications and create HTML
        notifications.forEach(notification => {
            // Format date
            const notifDate = new Date(notification.created_at);
            const formattedDate = notifDate.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric'
            });
            
            // Format time
            const formattedTime = notifDate.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            
            // Get notification type color class
            const typeClass = getNotificationTypeColorClass(notification.type);
            
            // Create notification item
            notificationsHtml += `
            <div class="flex items-start p-3 bg-white rounded-lg shadow ${notification.is_read ? 'opacity-75' : ''}">
                <div class="flex-shrink-0 mr-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center ${typeClass.bg}">
                        <i class="${typeClass.icon} ${typeClass.text}"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-medium">${notification.title || 'Notification'}</h4>
                    <p class="text-sm text-gray-600">${notification.content}</p>
                    <p class="text-xs text-gray-500 mt-1">${formattedDate} at ${formattedTime}</p>
                </div>
            </div>`;
        });
        
        notificationsHtml += '</div>';
        notificationsContainer.innerHTML = notificationsHtml;
    } else if (notificationsContainer) {
        notificationsContainer.innerHTML = '<p class="text-center py-4 text-gray-500">No notifications</p>';
    }
    
    // Handle messages if container exists
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        if (messages.length > 0) {
            let messagesHtml = '<div class="space-y-3">';
            
            // Loop through messages and create HTML
            messages.forEach(message => {
                // Format date
                const msgDate = new Date(message.created_at);
                const formattedDate = msgDate.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric'
                });
                
                // Create message item
                messagesHtml += `
                <div class="bg-white shadow rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="viewMessage(${message.message_id})">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <i class="fas fa-envelope text-indigo-500"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <h4 class="font-medium">${message.sender_name || 'Doctor'}</h4>
                                <span class="text-xs text-gray-500">${formattedDate}</span>
                            </div>
                            <p class="text-sm text-gray-600 line-clamp-2">${message.content}</p>
                        </div>
                    </div>
                    ${message.reply ? `
                    <div class="mt-2 ml-10 pl-3 border-l-2 border-gray-200">
                        <p class="text-xs text-gray-500">Your reply:</p>
                        <p class="text-sm text-gray-600 line-clamp-1">${message.reply}</p>
                    </div>` : ''}
                </div>`;
            });
            
            messagesHtml += '</div>';
            messagesContainer.innerHTML = messagesHtml;
        } else {
            messagesContainer.innerHTML = '<p class="text-center py-4 text-gray-500">No messages</p>';
        }
    }
}

/**
 * Get CSS color class based on notification type
 */
function getNotificationTypeColorClass(type) {
    switch(type.toLowerCase()) {
        case 'appointment':
            return {
                bg: 'bg-blue-100',
                text: 'text-blue-500',
                icon: 'fas fa-calendar-check'
            };
        case 'message':
            return {
                bg: 'bg-indigo-100',
                text: 'text-indigo-500',
                icon: 'fas fa-envelope'
            };
        case 'lab':
            return {
                bg: 'bg-purple-100',
                text: 'text-purple-500',
                icon: 'fas fa-flask'
            };
        case 'medication':
            return {
                bg: 'bg-green-100',
                text: 'text-green-500',
                icon: 'fas fa-pills'
            };
        case 'system':
            return {
                bg: 'bg-yellow-100',
                text: 'text-yellow-600',
                icon: 'fas fa-bell'
            };
        default:
            return {
                bg: 'bg-gray-100',
                text: 'text-gray-500',
                icon: 'fas fa-info-circle'
            };
    }
}

/**
 * Cancel an appointment
 */
function cancelAppointment(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        // Show loading state
        const buttons = document.querySelectorAll(`button[onclick="cancelAppointment(${appointmentId})"]`);
        buttons.forEach(button => {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            button.disabled = true;
        });
        
        // Send cancel request to API
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
            if (data.success) {
                // Show success message
                const successMessage = document.createElement('div');
                successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
                successMessage.textContent = data.message || 'Appointment cancelled successfully';
                document.body.appendChild(successMessage);
                setTimeout(() => successMessage.remove(), 3000);
                
                // Refresh appointments
                fetchAppointments();
            } else {
                throw new Error(data.message || 'Failed to cancel appointment');
            }
        })
        .catch(error => {
            console.error('Error cancelling appointment:', error);
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
            errorMessage.textContent = error.message || 'Failed to cancel appointment';
            document.body.appendChild(errorMessage);
            setTimeout(() => errorMessage.remove(), 3000);
            
            // Reset button state
            buttons.forEach(button => {
                button.innerHTML = '<i class="fas fa-times mr-1"></i> Cancel';
                button.disabled = false;
            });
        });
    }
}

/**
 * Reschedule an appointment
 */
function rescheduleAppointment(appointmentId) {
    // Redirect to booking page with appointment ID
    window.location.href = `booking.php?reschedule=${appointmentId}`;
}

/**
 * View a message
 */
function viewMessage(messageId) {
    // Fetch message details from API
    fetch(`api/get_message.php?id=${messageId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Create and show modal
                showMessageModal(data.message);
            } else {
                throw new Error(data.message || 'Failed to load message');
            }
        })
        .catch(error => {
            console.error('Error loading message:', error);
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
            errorMessage.textContent = error.message || 'Failed to load message';
            document.body.appendChild(errorMessage);
            setTimeout(() => errorMessage.remove(), 3000);
        });
}

/**
 * Show message modal with reply functionality
 */
function showMessageModal(message) {
    // Check if modal container exists, create if not
    let modalContainer = document.getElementById('messageModalContainer');
    if (!modalContainer) {
        modalContainer = document.createElement('div');
        modalContainer.id = 'messageModalContainer';
        document.body.appendChild(modalContainer);
    }
    
    // Format date
    const msgDate = new Date(message.created_at);
    const formattedDate = msgDate.toLocaleDateString('en-US', { 
        weekday: 'short', 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
    
    // Format time
    const formattedTime = msgDate.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit'
    });
    
    // Create modal HTML
    const modalHtml = `
    <div id="messageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal header -->
            <div class="bg-indigo-50 p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-indigo-700">Message from Dr. ${message.sender_name || 'Doctor'}</h3>
                    <button onclick="closeMessageModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-500">${formattedDate} at ${formattedTime}</p>
            </div>
            
            <!-- Message content -->
            <div class="flex-1 overflow-y-auto p-4">
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <p>${message.content}</p>
                </div>
                
                ${message.reply ? `
                <div class="mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Your Reply:</h4>
                    <div class="bg-indigo-50 rounded-lg p-4">
                        <p>${message.reply}</p>
                    </div>
                </div>` : `
                <div class="mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Reply:</h4>
                    <textarea id="messageReply" class="w-full h-24 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Type your reply here..."></textarea>
                </div>
                <div class="mt-2 text-right">
                    <button onclick="sendReply(${message.message_id})" class="bg-indigo-500 text-white px-4 py-2 rounded-lg hover:bg-indigo-600 transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reply
                    </button>
                </div>`}
            </div>
        </div>
    </div>`;
    
    // Add modal to container
    modalContainer.innerHTML = modalHtml;
}

/**
 * Close message modal
 */
function closeMessageModal() {
    const modal = document.getElementById('messageModal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Send reply to message
 */
function sendReply(messageId) {
    const replyText = document.getElementById('messageReply').value.trim();
    
    if (!replyText) {
        alert('Please enter a reply');
        return;
    }
    
    // Show loading state
    const sendButton = document.querySelector(`button[onclick="sendReply(${messageId})"]`);
    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
    sendButton.disabled = true;
    
    // Send reply to API
    fetch('api/send_reply.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message_id=${messageId}&reply=${encodeURIComponent(replyText)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded shadow-lg z-50';
            successMessage.textContent = data.message || 'Reply sent successfully';
            document.body.appendChild(successMessage);
            setTimeout(() => successMessage.remove(), 3000);
            
            // Close modal
            closeMessageModal();
            
            // Refresh messages
            fetchNotificationsAndMessages();
        } else {
            throw new Error(data.message || 'Failed to send reply');
        }
    })
    .catch(error => {
        console.error('Error sending reply:', error);
        // Show error message
        const errorMessage = document.createElement('div');
        errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded shadow-lg z-50';
        errorMessage.textContent = error.message || 'Failed to send reply';
        document.body.appendChild(errorMessage);
        setTimeout(() => errorMessage.remove(), 3000);
        
        // Reset button state
        sendButton.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Reply';
        sendButton.disabled = false;
    });
}