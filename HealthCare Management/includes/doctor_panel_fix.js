/**
 * Enhanced notification panel fix for doctor dashboard
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get notification panel and move it to the end of body for proper z-indexing
    const notificationPanel = document.getElementById('notificationPanel');
    if (notificationPanel) {
        // Clone the panel
        const panelClone = notificationPanel.cloneNode(true);
        
        // Remove the original panel
        notificationPanel.remove();
        
        // Add the cloned panel to the end of body
        document.body.appendChild(panelClone);
        
        // Ensure proper styling
        panelClone.style.display = 'none';
        panelClone.style.position = 'fixed';
        panelClone.style.zIndex = '9999';
        panelClone.style.top = '60px'; // Position below navbar
        panelClone.style.right = '20px';
        
        // Update notification toggle to work with the cloned panel
        window.toggleNotificationPanel = function() {
            if (panelClone.style.display === 'none') {
                panelClone.style.display = 'block';
                // Load fresh notifications when opening
                if (typeof loadNotifications === 'function') {
                    loadNotifications();
                }
            } else {
                panelClone.style.display = 'none';
            }
        };
        
        // Fix notification list selector
        const notificationsList = panelClone.querySelector('#notificationsList');
        if (notificationsList) {
            // Add an ID for targeting notifications in the clone
            notificationsList.id = 'notificationsListClone';
            
            // Override loadNotifications function to target the clone
            const originalLoadNotifications = window.loadNotifications;
            if (typeof originalLoadNotifications === 'function') {
                window.loadNotifications = function() {
                    // Update container ID
                    const container = document.getElementById('notificationsListClone');
                    if (!container) return;
                    
                    // Show loading message
                    container.innerHTML = '<p class="text-gray-500 text-center py-4">Loading notifications...</p>';
                    
                    // Use existing fetch code from original function...
                    fetch('api/fetch_doctor_notifications.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update notification count
                                const notificationCountElement = document.getElementById('notificationCount');
                                if (notificationCountElement) {
                                    notificationCountElement.textContent = data.counts.unread;
                                }
                                
                                // Display notifications
                                const items = data.data;
                                if (items.length === 0) {
                                    container.innerHTML = '<p class="text-center py-4">No notifications</p>';
                                } else {
                                    container.innerHTML = '';
                                    items.forEach(item => {
                                        if (item.type === 'notification') {
                                            // Create notification item
                                            const notificationItem = document.createElement('div');
                                            notificationItem.className = `p-3 border-b border-gray-100 ${!item.is_read ? 'bg-blue-50' : ''}`;
                                            
                                            notificationItem.innerHTML = `
                                                <div class="flex items-start">
                                                    <div class="flex-grow">
                                                        <h4 class="font-medium text-gray-900">${item.title}</h4>
                                                        <p class="text-sm text-gray-600">${item.content}</p>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            <span>${item.sender} • ${item.formatted_date}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
                                            
                                            container.appendChild(notificationItem);
                                        }
                                    });
                                }
                            } else {
                                container.innerHTML = '<p class="text-center py-4 text-red-500">Failed to load notifications</p>';
                            }
                        })
                        .catch(() => {
                            container.innerHTML = '<p class="text-center py-4 text-red-500">Error loading notifications</p>';
                        });
                };
            }
        }
        
        // Fix mark all as read button
        const markAllReadBtn = panelClone.querySelector('button[onclick="markAllNotificationsAsRead()"]');
        if (markAllReadBtn) {
            markAllReadBtn.onclick = function() {
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
                        alert('All notifications marked as read');
                    } else {
                        throw new Error(data.message || 'Failed to mark notifications as read');
                    }
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                    alert('Failed to mark notifications as read');
                });
            };
        }
        
        // Ensure click outside closes the panel
        document.addEventListener('click', function(e) {
            const icon = document.getElementById('notificationIcon');
            
            if (panelClone.style.display === 'block' && 
                !panelClone.contains(e.target) && 
                e.target !== icon && 
                (!icon || !icon.contains(e.target))) {
                panelClone.style.display = 'none';
            }
        });
        
        // Fix close button
        const closeBtn = panelClone.querySelector('button[onclick="toggleNotificationPanel()"]');
        if (closeBtn) {
            closeBtn.onclick = function() {
                panelClone.style.display = 'none';
            };
        }
    }
});