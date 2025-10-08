<?php
// File: includes/footer.php
// Common footer file for JM Animal Feeds ERP System
// Contains closing HTML tags, JavaScript includes, and footer content
?>

<?php if (isset($_SESSION['user_id'])): ?>
                </div> <!-- End pt-3 -->
            </main> <!-- End main content -->
        </div> <!-- End row -->
    </div> <!-- End container-fluid -->
<?php else: ?>
    </div> <!-- End container-fluid -->
<?php endif; ?>

<!-- Footer -->
<footer class="mt-auto" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: white;">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div style="background: rgba(255,255,255,0.2); padding: 0.75rem; border-radius: 0.5rem; margin-right: 1rem;">
                        <i class="bi bi-grain" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 fw-bold">JM Animal Feeds ERP</h6>
                        <p class="mb-0 small opacity-75">Complete business management solution</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="d-flex justify-content-md-end justify-content-center align-items-center flex-wrap">
                    <span class="me-3 small opacity-75">&copy; <?php echo date('Y'); ?> All rights reserved</span>
                    <div class="btn-group">
                        <button class="btn btn-link text-white p-1" data-bs-toggle="modal" data-bs-target="#supportModal" title="Support">
                            <i class="bi bi-headset"></i>
                        </button>
                        <button class="btn btn-link text-white p-1" onclick="window.open('<?php echo BASE_URL; ?>/help.php', '_blank')" title="Help">
                            <i class="bi bi-question-circle"></i>
                        </button>
                        <button class="btn btn-link text-white p-1" onclick="window.print()" title="Print">
                            <i class="bi bi-printer"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Support Modal -->
<div class="modal fade" id="supportModal" tabindex="-1" aria-labelledby="supportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supportModalLabel">Contact Support</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <h6><i class="bi bi-headset"></i> Technical Support</h6>
                        <p class="mb-2">
                            <strong>Email:</strong> <a href="mailto:rogerwambewa@gmail.com">rogerwambewa@gmail.com</a><br>
                            <strong>Phone:</strong> <a href="tel:+255757100063">+255 757 100 063</a><br>
                            <strong>Hours:</strong> Monday - Friday, 8:00 AM - 6:00 PM (EAT)
                        </p>
                    </div>
                    <div class="col-12 mt-3">
                        <h6><i class="bi bi-geo-alt"></i> Head Office</h6>
                        <p class="mb-2">
                            JM Animal Feeds Ltd.<br>
                            Tegeta A, Goba Road<br>
                            Dar es Salaam, Tanzania<br>
                            P.O. Box 12345
                        </p>
                    </div>
                    <div class="col-12 mt-3">
                        <h6><i class="bi bi-exclamation-triangle"></i> Emergency Support</h6>
                        <p class="mb-0">
                            For production line emergencies:<br>
                            <strong>Emergency Hotline:</strong> <a href="tel:+255757100063">+255 757 100 063</a><br>
                            <small class="text-muted">Available 24/7 for critical production issues</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="mailto:rogerwambewa@gmail.com" class="btn btn-primary">Send Email</a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
    // Base URL for JavaScript
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });

    // Confirm logout
    document.addEventListener('DOMContentLoaded', function() {
        const logoutLinks = document.querySelectorAll(`a[href="${BASE_URL}/logout.php"]`);
        logoutLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });
    });

    // Add active class to current navigation item
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(function(link) {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    });

    // Mobile sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarNav');
        const navbarToggler = document.querySelector('.navbar-toggler');

        if (sidebar && navbarToggler) {
            // Create overlay element
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);

            // Toggle sidebar
            navbarToggler.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            });

            // Close sidebar when overlay is clicked
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });

            // Close sidebar when nav link is clicked on mobile
            const sidebarLinks = sidebar.querySelectorAll('.nav-link');
            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        }
    });

    // Enhanced loading states for buttons
    function showLoading(button, text = 'Loading...') {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>' + text;
        button.disabled = true;
        button.setAttribute('data-original-text', originalText);
    }

    function hideLoading(button) {
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
            button.disabled = false;
            button.removeAttribute('data-original-text');
        }
    }

    // Add smooth scrolling to anchor links
    document.addEventListener('DOMContentLoaded', function() {
        const anchorLinks = document.querySelectorAll('a[href^="#"]');
        anchorLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });

    // Notification System Functions
    function handleNotificationClick(notificationId, actionUrl) {
        // Mark notification as read
        fetch(`${BASE_URL}/notifications/mark_read.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('unread');
                    const indicator = notificationItem.querySelector('.unread-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
                // Update badge count
                updateNotificationBadge();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));

        // Navigate to action URL if provided
        if (actionUrl && actionUrl !== 'null' && actionUrl.trim() !== '') {
            window.location.href = actionUrl;
        }
    }

    function markAllNotificationsRead() {
        fetch(`${BASE_URL}/notifications/mark_all_read.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI - remove all unread indicators
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const indicator = item.querySelector('.unread-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                });
                // Update badge
                updateNotificationBadge();
                // Refresh notifications
                refreshNotifications();
            }
        })
        .catch(error => console.error('Error marking all notifications as read:', error));
    }

    function refreshNotifications() {
        fetch(`${BASE_URL}/notifications/get_notifications.php`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationList(data.notifications);
                updateNotificationBadge(data.unread_count);
            }
        })
        .catch(error => console.error('Error refreshing notifications:', error));
    }

    function updateNotificationList(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-bell-slash" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-2">No notifications</p>
                    <p class="small">You're all caught up!</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notification => {
            const iconClass = getNotificationIcon(notification.type).class;
            const icon = getNotificationIcon(notification.type).icon;

            html += `
                <div class="notification-item ${!notification.is_read ? 'unread' : ''}" data-id="${notification.id}">
                    <div class="d-flex align-items-start p-3 border-bottom notification-content"
                         onclick="handleNotificationClick(${notification.id}, '${notification.action_url}')">
                        <div class="notification-icon me-3">
                            <div class="badge bg-${iconClass} rounded-circle p-2">
                                <i class="bi bi-${icon}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 fw-bold notification-title">
                                        ${notification.title}
                                        ${notification.is_urgent ? '<i class="bi bi-exclamation-circle text-danger ms-1" title="Urgent"></i>' : ''}
                                    </h6>
                                    <p class="mb-1 small text-muted notification-message">${notification.message}</p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> ${formatDate(notification.created_at)}
                                        <span class="ms-2">
                                            <i class="bi bi-tag"></i> ${notification.module}
                                        </span>
                                    </small>
                                </div>
                                ${!notification.is_read ? '<div class="unread-indicator"><div class="badge bg-primary rounded-pill">&nbsp;</div></div>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        list.innerHTML = html;
    }

    function updateNotificationBadge(count = null) {
        const badge = document.querySelector('.notification-badge');
        if (count === null) {
            // Count unread notifications in current list
            count = document.querySelectorAll('.notification-item.unread').length;
        }

        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'SUCCESS': return { class: 'success', icon: 'check-circle' };
            case 'WARNING': return { class: 'warning', icon: 'exclamation-triangle' };
            case 'ERROR': return { class: 'danger', icon: 'x-circle' };
            case 'APPROVAL_REQUIRED': return { class: 'warning', icon: 'clock' };
            default: return { class: 'info', icon: 'info-circle' };
        }
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Auto-refresh notifications every 30 seconds
    setInterval(refreshNotifications, 30000);

    // Activity Feed Functions for Admin Dashboard
    function refreshActivities() {
        fetch(`${BASE_URL}/admin/ajax/get_recent_activities.php`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateActivityFeed(data.activities);
            }
        })
        .catch(error => console.error('Error refreshing activities:', error));
    }

    function viewActivityDetails(activityId) {
        // Open modal or navigate to detailed view
        window.open(`${BASE_URL}/admin/activity_detail.php?id=${activityId}`, '_blank', 'width=800,height=600');
    }

    function updateActivityFeed(activities) {
        const feed = document.getElementById('activityFeed');
        if (!feed || !activities) return;

        if (activities.length === 0) {
            feed.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-activity" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-2">No recent activities</p>
                    <p class="small">System activities will appear here as they happen</p>
                </div>
            `;
            return;
        }

        let html = '';
        activities.forEach(activity => {
            const moduleInfo = getModuleInfo(activity.module);
            html += `
                <div class="list-group-item activity-item" data-severity="${activity.severity}">
                    <div class="d-flex align-items-start">
                        <div class="activity-icon me-3">
                            <div class="badge bg-${moduleInfo.class} rounded-circle p-2">
                                <i class="bi bi-${moduleInfo.icon}"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        ${activity.user_name}
                                        <span class="badge bg-${moduleInfo.class} ms-2" style="font-size: 0.7rem;">
                                            ${activity.module}
                                        </span>
                                    </h6>
                                    <p class="mb-1 small">${activity.description}</p>
                                    <small class="text-muted">
                                        <i class="bi bi-building"></i> ${activity.branch_name}
                                        <span class="ms-2">
                                            <i class="bi bi-person-badge"></i> ${activity.role_name}
                                        </span>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">${formatDate(activity.created_at)}</small>
                                    ${activity.entity_id ? `<a href="#" onclick="viewActivityDetails(${activity.id})" class="btn btn-xs btn-outline-secondary mt-1"><i class="bi bi-eye"></i></a>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        feed.innerHTML = html;
    }

    function getModuleInfo(module) {
        const moduleMap = {
            'SALES': { class: 'success', icon: 'cart-fill' },
            'PRODUCTION': { class: 'primary', icon: 'gear-fill' },
            'INVENTORY': { class: 'warning', icon: 'boxes' },
            'TRANSFERS': { class: 'info', icon: 'arrow-left-right' },
            'PURCHASES': { class: 'secondary', icon: 'cart-plus-fill' },
            'EXPENSES': { class: 'danger', icon: 'receipt-cutoff' },
            'FLEET': { class: 'dark', icon: 'truck' },
            'ORDERS': { class: 'warning', icon: 'clipboard-check' },
            'USER_MANAGEMENT': { class: 'primary', icon: 'people-fill' }
        };
        return moduleMap[module] || { class: 'info', icon: 'info-circle' };
    }
</script>

</body>
</html>