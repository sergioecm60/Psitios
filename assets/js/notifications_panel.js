/**
 * /Psitios/assets/js/notifications_panel.js
 * 
 * Gestiona la lógica del panel de notificaciones.
 * Esta clase se encarga de obtener, mostrar y actualizar las notificaciones de forma periódica,
 * así como de manejar las interacciones del usuario (marcar como leída, eliminar, etc.).
 */
class NotificationManager {
    /**
     * @param {number} [fetchIntervalMs=30000] - Intervalo en milisegundos para buscar nuevas notificaciones.
     */
    constructor() {
        // --- Estado Interno ---
        this.isLoading = false;
        this.retryCount = 0;
        this.maxRetries = 3;
        this.retryDelay = 5000;
        this.fetchInterval = null;
        this.fetchIntervalMs = 30000; // 30 segundos

        // Iniciar el gestor
        this.init();
    }

    /**
     * Inicializa el gestor de notificaciones.
     * Realiza la primera carga y configura el polling y los event listeners.
     */
    init() {
        this.fetchNotifications();

        this.fetchInterval = setInterval(() => {
            this.fetchNotifications();
        }, this.fetchIntervalMs);

        // Limpia el intervalo cuando el usuario abandona la página para evitar ejecuciones en segundo plano.
        window.addEventListener('beforeunload', () => {
            if (this.fetchInterval) {
                clearInterval(this.fetchInterval);
            }
        });

        this.setupEventListeners();
    }

    /**
     * Configura los listeners para los botones estáticos y el contenedor de notificaciones.
     * Utiliza delegación de eventos en el contenedor para manejar los botones dinámicos.
     */
    setupEventListeners() {
        const markAllBtn = document.getElementById('mark-all-btn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
        
        const retryBtn = document.getElementById('retry-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.retry());
        }

        // Delegación de eventos para los botones dentro de cada notificación.
        const container = document.getElementById('notification-container');
        if (container) {
            container.addEventListener('click', (event) => {
                const button = event.target.closest('.mark-read-btn');
                if (button) {
                    event.preventDefault();
                    const notificationId = button.dataset.id;
                    if (notificationId) {
                        this.markAsRead(parseInt(notificationId, 10));
                    }
                }

                const deleteBtn = event.target.closest('.delete-notification-btn');
                if (deleteBtn) {
                    event.preventDefault();
                    const notificationId = deleteBtn.dataset.id;
                    if (notificationId) {
                        this.deleteNotification(parseInt(notificationId, 10));
                    }
                }

                const resolveBtn = event.target.closest('.resolve-btn');
                if (resolveBtn) {
                    event.preventDefault();
                    const notificationId = resolveBtn.dataset.id;
                    if (notificationId && confirm('¿Marcar esta notificación como resuelta?')) {
                        this.resolveNotification(parseInt(notificationId, 10));
                    }
                }
            });
        }
    }

    /**
     * Obtiene las notificaciones desde la API.
     * Maneja el estado de carga y la lógica de reintentos en caso de error.
     */
    async fetchNotifications() {
        if (this.isLoading) return;

        // console.log('🔄 Fetching notifications...'); // Descomentar para depuración
        this.isLoading = true;

        try {
            const response = await fetch('api/notifications_api.php', {
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
                this.updateNotificationDisplay(data.data);
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

    /**
     * Maneja los errores de la API, implementando una estrategia de reintentos.
     * @param {Error} error - El objeto de error capturado.
     */
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

    /**
     * Actualiza la interfaz de usuario con las notificaciones recibidas.
     * @param {Array<object>} notifications - Un array de objetos de notificación.
     */
    updateNotificationDisplay(notifications) {
        const container = document.getElementById('notification-container');
        const badge = document.getElementById('notification-badge');
        const markAllBtn = document.getElementById('mark-all-btn');

        if (!container) return;

        const unreadCount = notifications.filter(n => !n.is_read).length;

        if (badge) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = unreadCount > 0 ? 'flex' : 'none'; // Usar flex para centrar mejor
        }

        if (markAllBtn) {
            markAllBtn.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        if (notifications.length === 0) {
            container.innerHTML = '<div class="no-notifications">📭 No hay notificaciones</div>';
            return;
        }

        container.innerHTML = notifications.map(notification => {
            const isResolved = !!notification.resolved_at;
            return `
                <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                    <div class="notification-title">${window.escapeHTML(notification.title || 'Notificación')}</div>
                    <div class="notification-message">${window.escapeHTML(notification.message)}</div>
                    <div class="notification-meta">
                        <span class="notification-time">⏰ ${this.formatTime(notification.created_at)}</span>
                        ${notification.site_name ? `<span class="notification-site">🌐 ${window.escapeHTML(notification.site_name)}</span>` : ''}
                        ${isResolved 
                            ? `<span class="notification-resolved">✅ Resuelto: ${this.formatTime(notification.resolved_at)}</span>` 
                            : `<button class="btn-action resolve-btn" data-id="${notification.id}">✅ Resuelto</button>`
                        }
                    </div>
                    ${!notification.is_read && !isResolved ? `<button class="mark-read-btn" data-id="${notification.id}">✓ Marcar como leída</button>` : ''}
                    <button class="btn-action delete-notification-btn" data-id="${notification.id}">🗑️</button>
                </div>
            `;
        }).join('');
    }

    /**
     * Elimina una notificación.
     * @param {number} notificationId - El ID de la notificación a eliminar.
     */
    async deleteNotification(notificationId) {
        if (!confirm('¿Eliminar esta notificación?')) return;
        this.handleApiResponse(
            window.api.post('api/notifications_api.php', { action: 'delete', notification_id: notificationId }),
            'Notificación eliminada'
        );
    }

    /**
     * Marca una notificación específica como leída.
     * @param {number} notificationId - El ID de la notificación.
     */
    async markAsRead(notificationId) {
        this.handleApiResponse(
            window.api.post('api/notifications_api.php', { action: 'mark_read', notification_id: notificationId }),
            'Notificación marcada como leída'
        );
    }

    /**
     * Marca todas las notificaciones no leídas como leídas.
     */
    async markAllAsRead() {
        this.handleApiResponse(
            window.api.post('api/notifications_api.php', { action: 'mark_all_read' }),
            'Todas las notificaciones marcadas como leídas'
        );
    }

    /**
     * Marca una notificación como resuelta.
     * @param {number} notificationId - El ID de la notificación a resolver.
     */
    async resolveNotification(notificationId) {
        this.handleApiResponse(
            window.api.post('api/resolve_notification.php', { action: 'resolve', notification_id: notificationId }),
            'Notificación resuelta y servicio actualizado'
        );
    }

    /**
     * Maneja la respuesta de una promesa de API, mostrando éxito o error.
     * @param {Promise<object>} apiPromise - La promesa devuelta por la llamada a la API.
     * @param {string} successMessage - El mensaje a mostrar si la operación tiene éxito.
     */
    async handleApiResponse(apiPromise, successMessage) {
        try {
            const result = await apiPromise;
            if (result.success) {
                this.showSuccess(result.message || successMessage);
                this.fetchNotifications(); // Refrescar la lista
            } else {
                this.showError(result.message || 'Ocurrió un error desconocido.');
            }
        } catch (error) {
            this.showError('Error de conexión o respuesta inválida del servidor.');
            console.error('API Action Error:', error);
        }
    }

    // --- Métodos de UI (Helpers) ---

    /** Muestra un mensaje de error temporal. */
    showError(message) {
        const errorDiv = document.getElementById('notification-error');
        if (errorDiv) {
            errorDiv.textContent = '❌ ' + message;
            errorDiv.style.display = 'block';
            setTimeout(() => errorDiv.style.display = 'none', 10000);
        }
    }

    /** Muestra un mensaje de éxito temporal. */
    showSuccess(message) {
        const successDiv = document.getElementById('notification-success');
        if (successDiv) {
            successDiv.textContent = '✅ ' + message;
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 5000);
        }
    }

    /** Oculta el mensaje de error. */
    hideError() {
        const errorDiv = document.getElementById('notification-error');
        if (errorDiv) errorDiv.style.display = 'none';
    }

    /**
     * Reinicia el contador de reintentos y fuerza una nueva búsqueda de notificaciones.
     */
    retry() {
        this.retryCount = 0;
        if (!this.fetchInterval) {
            this.fetchInterval = setInterval(() => this.fetchNotifications(), this.fetchIntervalMs);
        }
        const retryBtn = document.getElementById('retry-btn');
        if (retryBtn) retryBtn.style.display = 'none';
        this.fetchNotifications();
    }

    formatTime(timestamp) {
        // NOTA: Esta función también es un buen candidato para un archivo de utilidades global.
        if (!timestamp) return '';
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
    // console.log('🚀 Initializing Notification Manager...'); // Descomentar para depuración
    window.notificationManager = new NotificationManager();
});