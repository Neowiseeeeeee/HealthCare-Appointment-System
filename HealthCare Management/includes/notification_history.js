/**
 * Notification History JavaScript Functions
 * Handles fetching and displaying notification history in admin panel
 */

// Wait until DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize notification history
    loadNotificationHistory();
    
    // Add event listener for history button if it exists
    const viewHistoryBtn = document.getElementById('viewHistoryBtn');
    if (viewHistoryBtn) {
        viewHistoryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // The modal in the admin page has ID 'historyModal' not 'notificationHistoryModal'
            const historyModal = document.getElementById('historyModal');
            if (historyModal) {
                // Check if Bootstrap modal functionality is available
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modalInstance = new bootstrap.Modal(historyModal);
                    modalInstance.show();
                } else {
                    historyModal.style.display = 'block';
                    historyModal.classList.add('show');
                }
                // Reload notification history when modal is opened
                loadNotificationHistory();
            } else {
                console.error('History modal not found with ID: historyModal');
            }
        });
    }
});

/**
 * Load notification history from the database
 */
function loadNotificationHistory() {
    // Get history container
    const historyContainer = document.getElementById('historyModalBody');
    if (!historyContainer) {
        console.error('Notification history container not found with ID: historyModalBody');
        return;
    }
    
    // Show loading state
    historyContainer.innerHTML = '<p class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading notification history...</p>';
    
    // Fetch notification history from API
    fetch('../api/fetch_notification_history.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Display notification history
                displayNotificationHistory(data.notifications);
            } else {
                throw new Error(data.message || 'Failed to load notification history');
            }
        })
        .catch(error => {
            console.error('Error fetching notification history:', error);
            historyContainer.innerHTML = 
                '<p class="text-center py-4 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> ' + 
                'Could not load notification history. Please try again later.</p>';
        });
}

/**
 * Display notification history in the modal
 */
function displayNotificationHistory(notifications) {
    // Get history container - this is the modal body in the admin page
    const historyContainer = document.getElementById('historyModalBody');
    
    if (historyContainer && notifications && notifications.length > 0) {
        let html = '<div class="overflow-x-auto">';
        html += '<table class="w-full text-left">';
        html += `
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Message</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Sent By</th>
                </tr>
            </thead>
            <tbody>`;
        
        // Loop through notifications and create table rows
        notifications.forEach(notification => {
            // Format date
            const notifDate = new Date(notification.created_at);
            const formattedDate = notifDate.toLocaleDateString('en-US', { 
                year: 'numeric',
                month: 'short', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit'
            });
            
            // Create notification row
            html += `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm">${formattedDate}</td>
                    <td class="px-4 py-3">${notification.title || 'No Title'}</td>
                    <td class="px-4 py-3 text-sm max-w-xs truncate">${notification.content}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full ${notification.type === 'system' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}">${notification.type}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">${notification.sender_name || 'System'}</td>
                </tr>`;
        });
        
        html += '</tbody></table></div>';
        historyContainer.innerHTML = html;
    } else {
        historyContainer.innerHTML = '<p class="text-center py-4 text-gray-500">No notification history found</p>';
    }
}