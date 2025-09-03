class NotificationManager {
    constructor() {
        this.isLoading = false;
        this.retryCount = 0;
        this.maxRetries = 3;
        this.retryDelay = 5000;
        this.fetchInterval = null;
        
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        this.csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;
        
        this.init();
    }

    init() {
        this.fetchNotifications();
        
        this.fetchInterval = setInterval(() => {
            this.fetchNotifications();
        }, 30000);
        
        window.addEventListener('beforeunload', () => {
            if (this.fetchInterval) {
                clearInterval(this.fetchInterval);
            }
        });
        
        this.setupEventListeners();
    }

    setupEventListeners() {
        const markAllBtn = document.getElementById('mark-all-btn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
        
        const retryBtn = document.getElementById('retry-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.retry());
        }
        
        const container = document.getElementById('notification-container');
        if (container) {
            container.addEventListener('click', (event) => {
                const button = event.target.closest('.mark-read-btn');
                if (button) {
                    event.preventDefault();
                    const notificationItem = button.closest('.notification-item');
                    const notificationId = notificationItem ? notificationItem.dataset.id : null;
                    if (notificationId) {
                        this.markAsRead(parseInt(notificationId, 10));
                    }
                }

                // ✅ Detectar clic en botón de eliminar
                const deleteBtn = event.target.closest('.delete-notification-btn');
                if (deleteBtn) {
                    event.preventDefault();
                    const notificationItem = deleteBtn.closest('.notification-item');
                    const notificationId = notificationItem ? notificationItem.dataset.id : null;
                    if (notificationId) {
                        this.deleteNotification(parseInt(notificationId, 10));
                    }
                }
            });
        }
    }

    async fetchNotifications() {
        if (this.isLoading) return;

        console.log('🔄 Fetching notifications...');
        this.isLoading = true;

        try {
            const response = await fetch('/Psitios/api/notifications_api.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const responseText = await response.text();
            if (!responseText.trim()) {
                throw new Error('Server returned empty response');
            }

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('❌ JSON Parse Error:', jsonError);
                throw new Error('Invalid JSON received from server');
            }
            
            if (data.success) {
                this.updateNotificationDisplay(data.notifications);
                this.retryCount = 0;
                this.hideError();
                const retryBtn = document.getElementById('retry-btn');
                if (retryBtn) retryBtn.style.display = 'none';
            } else {
                this.handleError(new Error(data.message || 'Unknown error'));
            }

        } catch (error) {
            console.error('❌ Error fetching notifications:', error.message);
            this.handleError(error);
        } finally {
            this.isLoading = false;
        }
    }

    handleError(error) {
        this.retryCount++;
        
        if (this.retryCount >= this.maxRetries) {
            if (this.fetchInterval) {
                clearInterval(this.fetchInterval);
                this.fetchInterval = null;
            }
            this.showError('No se pueden cargar las notificaciones. ' + error.message);
            const retryBtn = document.getElementById('retry-btn');
            if (retryBtn) retryBtn.style.display = 'inline-block';
        } else {
            setTimeout(() => {
                if (this.retryCount < this.maxRetries) {
                    this.fetchNotifications();
                }
            }, this.retryDelay);
        }
    }

    updateNotificationDisplay(notifications) {
        const container = document.getElementById('notification-container');
        const badge = document.getElementById('notification-badge');
        const markAllBtn = document.getElementById('mark-all-btn');
        
        if (!container) return;

        const unreadCount = notifications.filter(n => !n.is_read).length;
        
        if (badge) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        if (markAllBtn) {
            markAllBtn.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        if (notifications.length === 0) {
            container.innerHTML = '<div class="no-notifications">📭 No hay notificaciones</div>';
            return;
        }

        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-title">${this.escapeHtml(notification.title || 'Notificación')}</div>
                <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                <div class="notification-meta">
                    <span class="notification-time">⏰ ${this.formatTime(notification.created_at)}</span>
                    ${notification.site_name ? `<span class="notification-site">🌐 ${this.escapeHtml(notification.site_name)}</span>` : ''}
                    ${notification.resolved_at ? `<span class="notification-resolved">✅ Resuelto: ${this.formatTime(notification.resolved_at)}</span>` : ''}
                </div>
                ${!notification.is_read ? '<button class="mark-read-btn">✓ Marcar como leída</button>' : ''}
                <!-- ✅ Botón eliminar -->
                <button class="delete-notification-btn" style="float: right; background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; font-size: 0.8rem; margin-left: 5px;">🗑️</button>
            </div>
        `).join('');
    }

    // ✅ Nueva función: Eliminar notificación
    async deleteNotification(notificationId) {
        if (!confirm('¿Eliminar esta notificación?')) return;

        try {
            const requestBody = {
                action: 'delete',
                notification_id: notificationId
            };

            if (this.csrfToken) {
                requestBody.csrf_token = this.csrfToken;
            }

            const headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (this.csrfToken) {
                headers['X-CSRF-TOKEN'] = this.csrfToken;
            }

            const response = await fetch('/Psitios/api/notifications_api.php', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Notificación eliminada');
                this.fetchNotifications(); // Refrescar
            } else {
                this.showError('Error: ' + data.message);
            }
        } catch (error) {
            this.showError('Error al eliminar la notificación');
        }
    }

    async markAsRead(notificationId) {
        try {
            const requestBody = {
                action: 'mark_read',
                notification_id: notificationId
            };

            if (this.csrfToken) {
                requestBody.csrf_token = this.csrfToken;
            }

            const headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (this.csrfToken) {
                headers['X-CSRF-TOKEN'] = this.csrfToken;
            }

            const response = await fetch('/Psitios/api/notifications_api.php', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Notificación marcada como leída');
                this.fetchNotifications();
            } else {
                this.showError('Error: ' + data.message);
            }
        } catch (error) {
            this.showError('Error al marcar como leída');
        }
    }

    async markAllAsRead() {
        try {
            const requestBody = {
                action: 'mark_all_read'
            };

            if (this.csrfToken) {
                requestBody.csrf_token = this.csrfToken;
            }

            const headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (this.csrfToken) {
                headers['X-CSRF-TOKEN'] = this.csrfToken;
            }

            const response = await fetch('/Psitios/api/notifications_api.php', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message || 'Todas las notificaciones marcadas como leídas');
                this.fetchNotifications();
            } else {
                this.showError('Error: ' + data.message);
            }
        } catch (error) {
            this.showError('Error al marcar todas como leídas');
        }
    }

    showError(message) {
        const errorDiv = document.getElementById('notification-error');
        if (errorDiv) {
            errorDiv.textContent = '❌ ' + message;
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 10000);
        }
    }

    showSuccess(message) {
        const successDiv = document.getElementById('notification-success');
        if (successDiv) {
            successDiv.textContent = '✅ ' + message;
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 5000);
        }
    }

    hideError() {
        const errorDiv = document.getElementById('notification-error');
        if (errorDiv) errorDiv.style.display = 'none';
    }

    retry() {
        this.retryCount = 0;
        if (!this.fetchInterval) {
            this.fetchInterval = setInterval(() => this.fetchNotifications(), 30000);
        }
        const retryBtn = document.getElementById('retry-btn');
        if (retryBtn) retryBtn.style.display = 'none';
        this.fetchNotifications();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Ahora mismo';
        if (diffMins < 60) return `Hace ${diffMins} min${diffMins > 1 ? 's' : ''}`;
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `Hace ${diffHours} hora${diffHours > 1 ? 's' : ''}`;
        
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `Hace ${diffDays} día${diffDays > 1 ? 's' : ''}`;
        
        return date.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Initializing Notification Manager...');
    window.notificationManager = new NotificationManager();
});