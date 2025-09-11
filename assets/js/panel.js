/**
 * /Psitios/assets/js/panel.js
 * 
 * Lógica principal para el panel de usuario (panel.php).
 * Este script maneja las pestañas de "Mis Sitios" y "Mi Agenda", la funcionalidad de chat,
 * los modales para agregar/editar contenido y las notificaciones de recordatorios.
 */

// --- 1. FUNCIONES GLOBALES DE UI ---
// Se mantienen en el ámbito global para ser accesibles desde cualquier parte.

/**
 * Abre un modal por su ID.
 * @param {string} modalId - El ID del elemento modal a mostrar.
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

/**
 * Cierra un modal por su ID.
 * @param {string} modalId - El ID del elemento modal a ocultar.
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // --- 2. CONFIGURACIÓN Y ESTADO ---
    // Elementos del DOM y datos iniciales
    const adminId = document.getElementById('admin_id')?.value;
    const userId = document.getElementById('user_id')?.value;

    // Elementos del Chat
    const chatModal = document.getElementById('chat-modal');
    const chatToggleBtn = document.getElementById('chat-toggle-btn');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');

    // Elementos de la Agenda
    const agendaTableBody = document.querySelector('#agenda-table tbody');
    const reminderModal = document.getElementById('reminder-modal');
    const reminderForm = document.getElementById('reminder-form');
    const reminderTypeSelect = document.getElementById('reminder-type');

    // Estado de la aplicación
    let chatPollingInterval = null;
    let notifiedReminders = new Set(JSON.parse(localStorage.getItem('notifiedReminders') || '[]'));
    let agendaItems = []; // Almacena los recordatorios para la comprobación de notificaciones.

    // --- 4. LÓGICA DE CHAT ---

    /** Maneja el envío de un nuevo mensaje de chat. */
    chatForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;
        if (!adminId) {
            alert('No se puede enviar el mensaje. No tienes un administrador asignado.');
            chatInput.value = '';
            return;
        }

        const result = await window.api.post('api/send_message.php', { message: text, receiver_id: adminId });
        if (result.success) {
            chatInput.value = '';
            fetchChatMessages();
        } else {
            alert('Error al enviar mensaje: ' + result.message);
        }
    });

    /** Obtiene y renderiza los mensajes del chat. */
    async function fetchChatMessages() {
        try {
            const result = await window.api.get('api/get_messages.php');
            if (result.success) {
                if (Array.isArray(result.data) && result.data.length > 0) {
                    chatMessages.innerHTML = result.data.map(msg => {
                    const isSent = msg.sender_id == userId;
                    return `
                        <div class="chat-message ${isSent ? 'sent' : 'received'}">
                            <strong>${isSent ? 'Tú' : 'Admin'}:</strong> ${window.escapeHTML(msg.message)}
                            <br><small>${formatTime(msg.created_at)}</small>
                            ${isSent ? `<button class="delete-msg-btn" data-id="${msg.id}" title="Eliminar">×</button>` : ''} 
                        </div>
                    `;
                    }).join('');
                } else {
                    chatMessages.innerHTML = '<p>Aún no hay mensajes.</p>';
                }
                chatMessages.scrollTop = chatMessages.scrollHeight;
            } else {
                chatMessages.innerHTML = `<p class="error">❌ ${result.message || 'Error al cargar mensajes.'}</p>`;
            }
        } catch (error) {
            chatMessages.innerHTML = `<p class="error">❌ ${error.message}</p>`;
        }
    }

    /** Inicia el polling para actualizar el chat solo si el modal está abierto. */
    function startChatPolling() {
        if (chatPollingInterval) clearInterval(chatPollingInterval);
        fetchChatMessages(); // Carga inmediata
        chatPollingInterval = setInterval(fetchChatMessages, 10000); // Polling cada 10s
    }

    /** Detiene el polling del chat. */
    function stopChatPolling() {
        if (chatPollingInterval) clearInterval(chatPollingInterval);
        chatPollingInterval = null;
    }

    /** Maneja la eliminación de un mensaje de chat. */
    chatMessages?.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-msg-btn');
        if (deleteBtn && confirm('¿Eliminar este mensaje?')) {
            const id = deleteBtn.dataset.id;
            const result = await window.api.post('api/delete_user_message.php', { message_id: id });
            if (result.success) {
                fetchChatMessages();
            } else {
                alert('Error: ' + result.message);
            }
        }
    });

    // --- 5. LÓGICA DE LA PESTAÑA "MIS SITIOS" ---

    /**
     * Obtiene y renderiza los sitios asignados por el administrador.
     */
    async function fetchAdminSites() {
        const grid = document.getElementById('admin-sites-grid');
        if (!grid) return;
        grid.innerHTML = '<div class="loading">Cargando sitios del administrador...</div>';
        try {
            const result = await window.api.get('api/get_user_sites.php');
            if (result.success && result.data.length > 0) {
                grid.innerHTML = result.data.map(service => createServiceCard(service, true)).join('');
            } else if (result.success) {
                grid.innerHTML = '<p>No tienes sitios asignados por el administrador.</p>';
            } else {
                grid.innerHTML = `<p class="error">❌ ${result.message || 'Error al cargar sitios.'}</p>`;
            }
        } catch (error) {
            grid.innerHTML = `<p class="error">❌ Error de conexión al cargar sitios.</p>`;
        }
    }

    /**
     * Obtiene y renderiza los sitios personales del usuario.
     */
    async function fetchUserSites() {
        const grid = document.getElementById('user-sites-grid');
        if (!grid) return;
        grid.innerHTML = '<div class="loading">Cargando tus sitios personales...</div>';
        try {
            const result = await window.api.get('api/get_user_sites_personal.php');
            if (result.success && result.data.length > 0) {
                grid.innerHTML = result.data.map(site => createServiceCard(site, false, true)).join('');
            } else if (result.success) {
                grid.innerHTML = '<p>Aún no has creado sitios personales.</p>';
            } else {
                grid.innerHTML = `<p class="error">❌ ${result.message || 'Error al cargar tus sitios.'}</p>`;
            }
        } catch (error) {
            grid.innerHTML = `<p class="error">❌ Error de conexión al cargar tus sitios.</p>`;
        }
    }

    /**
     * Crea el HTML para una tarjeta de servicio (sitio).
     * @param {object} item - El objeto del sitio/servicio.
     * @param {boolean} isAssigned - True si es un sitio asignado por el admin.
     * @param {boolean} [isPersonal=false] - True si es un sitio personal del usuario.
     * @returns {string} - El HTML de la tarjeta.
     */
    function createServiceCard(item, isAssigned, isPersonal = false) {
        const id = isAssigned ? item.service_id : item.id;
        const siteId = isAssigned ? item.site_id : item.id;
        const hasPassword = item.has_password || item.password_encrypted;

        // Si el sitio personal tiene una URL, convierte el título en un enlace.
        const nameHtml = (isPersonal && item.url)
            ? `<a href="${item.url.startsWith('http') ? item.url : 'http://' + item.url}" target="_blank" rel="noopener noreferrer" title="Ir a ${window.escapeHTML(item.name)}">${window.escapeHTML(item.name)}</a>`
            : window.escapeHTML(item.name);

        // Lógica para el botón de SSO: si el sitio se llama 'pvytgestiones', muestra un botón de acceso directo.
        const isPbytSite = isPersonal && item.name.toLowerCase() === 'pvytgestiones';
        const ssoButton = `<a href="auth/sso_pvyt.php?id=${id}" class="btn-sso">🔐 Acceder (SSO)</a>`;
        const viewButton = hasPassword ? `<button class="btn-view-creds" data-id="${id}" data-type="${isAssigned ? 'assigned' : 'personal'}">👁️ Ver</button>` : '';

        return `
            <div class="service-card">
                <h3>${nameHtml}</h3>
                ${isAssigned && item.password_needs_update ? '<p class="notification">⚠️ Contraseña pendiente</p>' : ''}
                <div class="credentials-area">
                    ${isPbytSite ? ssoButton : viewButton}
                    ${isAssigned ? `<button class="btn-notify-expired" data-id="${id}" ${item.password_needs_update ? 'disabled' : ''}>⏳ Notificar</button>` : ''}
                    ${isAssigned ? `<button class="btn-report-problem" data-site-id="${siteId}">🚨 Reportar</button>` : ''}
                    ${isPersonal ? `<button class="btn btn-sm btn-secondary btn-edit-site" data-id="${id}" data-type="personal">✏️ Editar</button>` : ''}
                    ${isPersonal ? `<button class="btn btn-sm btn-danger btn-delete-site" data-id="${id}" data-type="personal">🗑️ Eliminar</button>` : ''}
                </div>
                <div class="creds-display hidden" id="creds-${isAssigned ? 'a' : 'p'}-${id}"></div>
            </div>
        `;
    }

    // --- 6. LÓGICA DE LA PESTAÑA "MI AGENDA" ---

    class AgendaManager {
        constructor() {
            this.agendaTable = document.getElementById('agenda-table');
            this.searchInput = document.getElementById('agenda-search-input');
            this.typeFilter = document.getElementById('agenda-type-filter');
            this.addBtn = document.getElementById('add-reminder-btn');
            this.reminderForm = document.getElementById('reminder-form');
            this.reminderTypeSelect = document.getElementById('reminder-type');
            this.sortable = null;
            this.debounceTimer = null;

            // Estado para notificaciones
            this.notifiedReminders = new Set(JSON.parse(localStorage.getItem('notifiedReminders') || '[]'));
        }

        init() {
            this.bindEvents();
            this.loadReminders();
        }

        bindEvents() {
            // Búsqueda con debounce para no sobrecargar el servidor
            this.searchInput.addEventListener('input', () => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.loadReminders(), 300);
            });

            // Filtro por tipo
            this.typeFilter.addEventListener('change', () => this.loadReminders());

            // Botón de agregar
            this.addBtn.addEventListener('click', () => this.openAddModal());

            // Delegación de eventos para botones de acción en la tabla
            this.agendaTable.addEventListener('click', (e) => {
                const target = e.target;
                if (target.closest('.pin-btn')) this.togglePin(target.closest('.pin-btn').dataset.id);
                if (target.closest('.edit-reminder')) this.openEditModal(target.closest('.edit-reminder').dataset.id);
                if (target.closest('.delete-reminder')) this.deleteReminder(target.closest('.delete-reminder').dataset.id);
                if (target.closest('.complete-reminder')) this.toggleComplete(target.closest('.complete-reminder').dataset.id);
                if (target.closest('.decrypt-pass')) this.decryptReminderPassword(target.closest('.decrypt-pass'));
            });

            // Listeners para el formulario modal de recordatorios
            this.reminderTypeSelect?.addEventListener('change', (e) => this.updateReminderFormUI(e.target.value));
            this.reminderForm?.addEventListener('submit', (e) => this.handleReminderFormSubmit(e));
        }

        async loadReminders() {
            if (!this.agendaTable) return;
            // Limpia los `tbody` existentes y muestra el mensaje de carga en un nuevo `tbody`.
            this.agendaTable.querySelectorAll('tbody').forEach(tbody => tbody.remove());
            const loadingBody = document.createElement('tbody');
            loadingBody.innerHTML = `<tr><td colspan="8" class="loading">Cargando agenda...</td></tr>`;
            this.agendaTable.appendChild(loadingBody);

            const searchTerm = this.searchInput.value;
            const typeFilter = this.typeFilter.value;
            
            // Construir la URL relativa con parámetros de búsqueda
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (typeFilter) params.append('type', typeFilter);
            const queryString = params.toString();
            const endpoint = `api/get_user_reminders.php${queryString ? '?' + queryString : ''}`;

            const result = await window.api.get(endpoint);

            if (result.success) {
                agendaItems = result.data; // Actualiza la variable global para las notificaciones
                this.renderTable(result.data);
            } else {
                agendaItems = [];
                // Limpia el `tbody` de carga y muestra el error en uno nuevo.
                this.agendaTable.querySelectorAll('tbody').forEach(tbody => tbody.remove());
                const errorBody = document.createElement('tbody');
                errorBody.innerHTML = `<tr><td colspan="8" class="error">❌ ${result.message || 'Error al cargar la agenda.'}</td></tr>`;
                this.agendaTable.appendChild(errorBody);
            }
        }

        renderTable(reminders) {
            // Limpia cualquier contenido anterior (carga, error, datos viejos).
            this.agendaTable.querySelectorAll('tbody').forEach(tbody => tbody.remove());

            if (reminders.length === 0) {
                const emptyBody = document.createElement('tbody');
                emptyBody.innerHTML = `<tr><td colspan="8">No se encontraron recordatorios.</td></tr>`;
                this.agendaTable.appendChild(emptyBody);
                return;
            }

            reminders.forEach(item => {
                const typeInfo = this.getTypeInfo(item.type);
                const isCompleted = item.is_completed;
                const isPinned = item.is_pinned;

                // 1. Crea un nuevo <tbody> para cada recordatorio, que actuará como un grupo arrastrable.
                const reminderGroup = document.createElement('tbody');
                reminderGroup.className = 'reminder-group';

                const tr = document.createElement('tr');
                tr.dataset.id = item.id;
                tr.className = isCompleted ? 'completed' : '';

                let notesContent = '—';
                if (item.type === 'phone' && item.notes) {
                    const phoneNumber = window.escapeHTML(item.notes);
                    notesContent = `<a href="tel:${phoneNumber}" title="Llamar a ${phoneNumber}">${phoneNumber}</a>`;
                } else if (item.notes) {
                    notesContent = window.escapeHTML(item.notes.substring(0, 50) + (item.notes.length > 50 ? '...' : ''));
                }

                tr.innerHTML = `
                    <td class="drag-handle" title="Arrastrar para reordenar">☰</td>
                    <td class="pin-cell">
                        <button class="pin-btn ${isPinned ? 'pinned' : ''}" data-id="${item.id}" title="${isPinned ? 'Desfijar' : 'Fijar'}">${isPinned ? '📌' : '📍'}</button>
                    </td>
                    <td title="${typeInfo.label}">${typeInfo.icon}</td>
                    <td>
                        <input type="checkbox" class="complete-reminder" data-id="${item.id}" ${isCompleted ? 'checked' : ''} title="Marcar como completado">
                        ${window.escapeHTML(item.title)}
                    </td>
                    <td>${item.has_password ? `<button class="btn btn-sm btn-secondary decrypt-pass" data-id="${item.id}" data-target-row="details-row-${item.id}">Mostrar</button>` : '—'}</td>
                    <td>${notesContent}</td>
                    <td>${item.reminder_datetime ? new Date(item.reminder_datetime).toLocaleString('es-ES') : '—'}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-reminder" data-id="${item.id}">Editar</button> 
                        <button class="btn btn-sm btn-danger delete-reminder" data-id="${item.id}">Eliminar</button>
                    </td>
                `;
                // 2. Añade la fila principal al grupo.
                reminderGroup.appendChild(tr);

                if (item.type === 'credential' && item.has_password) {
                    const detailsRow = document.createElement('tr');
                    detailsRow.classList.add('credential-details-row', 'hidden');
                    detailsRow.id = `details-row-${item.id}`;
                    detailsRow.innerHTML = `<td colspan="8"><div class="credential-details-content"></div></td>`;
                    // 3. Añade la fila de detalles al MISMO grupo.
                    reminderGroup.appendChild(detailsRow);
                }

                // 4. Añade el grupo completo (el nuevo tbody) a la tabla principal.
                this.agendaTable.appendChild(reminderGroup);
            });

            this.initSortable();
        }

        initSortable() {
            if (this.sortable) {
                this.sortable.destroy();
            }
            // Inicializa SortableJS en el elemento <table>, no en el <tbody>.
            this.sortable = new Sortable(this.agendaTable, {
                animation: 150,
                handle: '.drag-handle',
                draggable: 'tbody', // Especifica que los elementos arrastrables son los <tbody>.
                onEnd: async (evt) => {
                    const orderedIds = Array.from(this.agendaTable.querySelectorAll('tbody.reminder-group tr[data-id]')).map(tr => tr.dataset.id);
                    const result = await window.api.post('api/update_reminder_order.php', { order: orderedIds });
                    if (!result.success) {
                        alert('Error al guardar el nuevo orden.');
                        this.loadReminders(); // Recargar para revertir el cambio visual
                    }
                }
            });
        }

        async togglePin(id) {
            const result = await window.api.post('api/toggle_reminder_pin.php', { id });
            if (result.success) {
                this.loadReminders(); // Recargar para que el orden se actualice
            } else {
                alert('Error al fijar el recordatorio.');
            }
        }

        async deleteReminder(id) {
            if (!confirm('¿Eliminar este recordatorio?')) return;
            const result = await window.api.post('api/delete_user_reminder.php', { id });
            if (result.success) {
                this.loadReminders();
            } else {
                alert('Error: ' + result.message);
            }
        }

        async handleReminderFormSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            const result = await window.api.post('api/save_user_reminder.php', data);
            if (result.success) {
                closeModal('reminder-modal');
                // En lugar de un evento, llamamos directamente a la recarga.
                this.loadReminders();
            } else {
                alert('Error: ' + result.message);
            }
        }

        async toggleComplete(id) {
            await window.api.post('api/save_user_reminder.php', { action: 'toggle_complete', id });
            // Recargamos para que el orden se actualice (completados van al final)
            this.loadReminders();
        }

        openAddModal() {
            reminderForm.reset();
            document.getElementById('reminder-id').value = '';
            document.getElementById('reminder-modal-title').textContent = 'Añadir Recordatorio';
            this.updateReminderFormUI('note');
            openModal('reminder-modal');
        }

        async openEditModal(id) {
            const result = await window.api.get(`api/get_user_reminder_details.php?id=${id}`);
            if (result.success) {
                const reminder = result.data;
                reminderForm.reset();
                document.getElementById('reminder-modal-title').textContent = 'Editar Recordatorio';
                document.getElementById('reminder-id').value = reminder.id;
                document.getElementById('reminder-type').value = reminder.type;
                document.getElementById('reminder-title').value = reminder.title;
                document.getElementById('reminder-username').value = reminder.username || '';
                document.getElementById('reminder-datetime').value = reminder.reminder_datetime ? reminder.reminder_datetime.substring(0, 16) : '';

                this.updateReminderFormUI(reminder.type);

                if (reminder.type === 'phone') {
                    document.getElementById('reminder-phone').value = reminder.notes || '';
                } else {
                    document.getElementById('reminder-notes').value = reminder.notes || '';
                }

                openModal('reminder-modal');
            } else {
                alert('Error al cargar los detalles del recordatorio: ' + result.message);
            }
        }

        getTypeInfo(type) {
            const types = {
                note: { icon: '📝', label: 'Nota' },
                credential: { icon: '🔑', label: 'Credencial' },
                phone: { icon: '📞', label: 'Teléfono' }
            };
            return types[type] || { icon: '❓', label: 'Desconocido' };
        }

        updateReminderFormUI(type) {
            const titleLabel = document.querySelector('label[for="reminder-title"]');
            const credentialFields = document.querySelectorAll('.credential-field');
            const phoneField = document.querySelector('.phone-field');
            const notesGroup = document.querySelector('label[for="reminder-notes"]').parentNode;
    
            // Ocultar todos los campos opcionales
            credentialFields.forEach(field => field.style.display = 'none');
            phoneField.style.display = 'none';
            notesGroup.style.display = 'block'; // Mostrar por defecto
    
            switch (type) {
                case 'credential':
                    titleLabel.textContent = 'Título';
                    credentialFields.forEach(field => field.style.display = 'block');
                    break;
                case 'phone':
                    titleLabel.textContent = 'Nombre del Contacto';
                    phoneField.style.display = 'block';
                    notesGroup.style.display = 'none'; // Ocultar notas para teléfonos
                    break;
                case 'note':
                default:
                    titleLabel.textContent = 'Título';
                    break;
            }
        }

        async decryptReminderPassword(decryptBtn) {
            decryptBtn.disabled = true; // Prevenir doble clic
            const id = decryptBtn.dataset.id;
            const targetRowId = decryptBtn.dataset.targetRow;
            const targetRow = document.getElementById(targetRowId);

            if (!targetRow) {
                console.error('Fila de detalles no encontrada:', targetRowId);
                decryptBtn.disabled = false;
                return;
            }

            if (!targetRow.classList.contains('hidden')) {
                targetRow.classList.add('hidden');
                decryptBtn.textContent = 'Mostrar';
                decryptBtn.disabled = false;
                return;
            }

            const contentDiv = targetRow.querySelector('.credential-details-content');
            contentDiv.innerHTML = '<p>Obteniendo...</p>';
            targetRow.classList.remove('hidden');
            decryptBtn.textContent = 'Ocultar';

            const decryptResult = await window.api.post('api/decrypt_user_data.php', { id: id, type: 'reminder' });
            if (decryptResult.success) {
                contentDiv.innerHTML = `
                    <p><strong>👤 Usuario:</strong> <span>${window.escapeHTML(decryptResult.data.username)}</span> <button class="btn-copy" data-copy="${window.escapeHTML(decryptResult.data.username)}">📋</button></p>
                    <p><strong>🔑 Contraseña:</strong> <span>${window.escapeHTML(decryptResult.data.password)}</span> <button class="btn-copy" data-copy="${window.escapeHTML(decryptResult.data.password)}">📋</button></p>
                `;
            } else {
                contentDiv.innerHTML = `<p class="error">❌ ${decryptResult.message || 'No se pudieron obtener las credenciales.'}</p>`;
            }
            decryptBtn.disabled = false;
        }

        checkReminders() {
            const now = new Date();
            agendaItems.forEach(async (item) => {
                if (!item.id || this.notifiedReminders.has(String(item.id)) || !item.reminder_datetime || item.is_completed) {
                    return;
                }
                const reminderTime = new Date(item.reminder_datetime);
                if (isNaN(reminderTime.getTime()) || reminderTime > now) {
                    return;
                }
                this.notifiedReminders.add(String(item.id));
                localStorage.setItem('notifiedReminders', JSON.stringify(Array.from(this.notifiedReminders)));
                const result = await window.api.get(`api/get_user_reminder_details.php?id=${item.id}`);
                this.showReminderAlert(result.success ? result.data : { title: item.title, notes: '(No se pudieron cargar los detalles)' });
            });
        }

        showReminderAlert(reminder) {
            const modal = document.getElementById('reminder-alert-modal');
            const body = document.getElementById('reminder-alert-body');
            if (!modal || !body) return;
            let message = `<h3>${window.escapeHTML(reminder.title)}</h3>`;
            if (reminder.username) message += `<p><strong>Usuario:</strong> ${window.escapeHTML(reminder.username)}</p>`;
            if (reminder.notes) message += `<p><strong>Nota:</strong> ${window.escapeHTML(reminder.notes)}</p>`;
            body.innerHTML = message;
            openModal('reminder-alert-modal');
        }
    }

    // --- 7. MANEJADORES DE EVENTOS DELEGADOS ---
    // Un solo listener en el contenedor principal para manejar clics en botones dinámicos.
    document.querySelector('.container').addEventListener('click', async (e) => {
        // 7.1. Botón "Ver Credenciales" (Sitios)
        const viewBtn = e.target.closest('.btn-view-creds');
        if (viewBtn) {
            const serviceId = viewBtn.dataset.id;
            const type = viewBtn.dataset.type;
            const credsDivId = `creds-${type === 'personal' ? 'p' : 'a'}-${serviceId}`;
            const credsDiv = document.getElementById(credsDivId);
            if (!credsDiv) return;

            if (!credsDiv.classList.contains('hidden')) {
                credsDiv.classList.add('hidden');
                return;
            }

            credsDiv.classList.remove('hidden');
            credsDiv.innerHTML = '<p>Obteniendo...</p>';

            const endpoint = type === 'personal' ? 'api/decrypt_user_data.php' : 'api/get_credentials.php';
            const body = type === 'personal' ? { id: serviceId, type: 'site' } : { id: serviceId };

            const result = await window.api.post(endpoint, body);
                if (result.success) {
                    credsDiv.innerHTML = `
                        ${result.data.url ? `<p><strong>🌐 URL:</strong> <a href="${result.data.url.startsWith('http') ? result.data.url : 'http://' + result.data.url}" target="_blank" rel="noopener noreferrer">${window.escapeHTML(result.data.url)}</a></p>` : ''}
                        <p><strong>👤 Usuario:</strong> 
                           <span>${window.escapeHTML(result.data.username)}</span> 
                           <button class="btn-copy" data-copy="${window.escapeHTML(result.data.username)}">📋</button>
                        </p>
                        <p><strong>🔑 Contraseña:</strong> 
                           <span>${window.escapeHTML(result.data.password)}</span> 
                           <button class="btn-copy" data-copy="${window.escapeHTML(result.data.password)}">📋</button>
                        </p>
                        ${result.data.notes ? `<div class="notes"><strong>📝 Notas:</strong><br>${window.escapeHTML(result.data.notes)}</div>` : ''}
                    `;
                } else {
                    credsDiv.innerHTML = `<p class="error">❌ ${result.message || 'No se pudieron obtener las credenciales.'}</p>`;
                }
        }

        // 7.2. Botón "Notificar Expiración" (Sitios)
        const notifyBtn = e.target.closest('.btn-notify-expired');
        if (notifyBtn) {
            if (!confirm('¿Contraseña expirada? Se notificará al admin.')) return;
            notifyBtn.disabled = true;
            const result = await window.api.post('api/notify_expiration.php', { id: notifyBtn.dataset.id });
            if (result.success) {
                alert('✅ Notificado.');
                fetchAdminSites();
            } else {
                alert('Error: ' + result.message);
            }
            notifyBtn.disabled = false;
        }

        // 7.3. Botón "Reportar Problema" (Sitios) - Usa el handler de main.js
        // No se necesita código aquí porque main.js ya lo maneja.

        // 7.4. Botón "Editar Sitio Personal"
        const editSiteBtn = e.target.closest('.btn-edit-site');
        if (editSiteBtn) {
            const id = editSiteBtn.dataset.id;
            const result = await window.api.get(`api/get_user_site_details.php?id=${id}`);
            if (result.success) {
                const site = result.data;
                const form = document.getElementById('user-site-form');
                form.reset();
                document.getElementById('user-site-modal-title').textContent = 'Editar Sitio Personal';
                document.getElementById('user-site-id').value = site.id;
                document.getElementById('user-site-name').value = site.name;
                document.getElementById('user-site-url').value = site.url;
                document.getElementById('user-site-username').value = site.username;
                document.getElementById('user-site-notes').value = site.notes;
                openModal('user-site-modal');
            } else {
                alert('Error al cargar los detalles del sitio: ' + result.message);
            }
        }

        // 7.5. Botón "Eliminar Sitio Personal"
        const deleteSiteBtn = e.target.closest('.btn-delete-site');
        if (deleteSiteBtn) {
            if (!confirm('¿Estás seguro de que quieres eliminar este sitio personal?')) return;
            const id = deleteSiteBtn.dataset.id;
            const result = await window.api.post('api/delete_user_site.php', { id });
            if (result.success) {
                alert('Sitio eliminado.');
                fetchUserSites();
            } else {
                alert('Error: ' + result.message);
            }
        }
    });

    document.getElementById('user-site-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        const result = await window.api.post('api/save_user_site.php', data);
        if (result.success) {
            closeModal('user-site-modal');
            fetchUserSites();
        } else {
            alert('Error: ' + result.message);
        }
    });

    // --- 8. LÓGICA DE UI (PESTAÑAS, MODALES, TEMA) ---
    document.addEventListener('click', e => {
        const copyBtn = e.target.closest('.btn-copy');
        if (copyBtn) {
            const text = copyBtn.dataset.copy;
            navigator.clipboard.writeText(text).then(() => {
                const original = copyBtn.textContent;
                copyBtn.textContent = '✅';
                setTimeout(() => copyBtn.textContent = original, 1500);
            }).catch(err => {
                console.error('Error al copiar:', err);
            });
        }
    });

    // 8.1. Funciones Auxiliares de Formato
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'Ahora';
        if (diffMins < 60) return `Hace ${diffMins} min`;
        if (diffMins < 1440) return `Hace ${Math.floor(diffMins / 60)} h`;
        return date.toLocaleDateString();
    }

    // 8.2. Gestión de Tema
    const themeSelect = document.getElementById('theme-select');

    function loadSavedTheme() {
        const savedTheme = localStorage.getItem('userTheme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        if (themeSelect) themeSelect.value = savedTheme;
    }

    async function setTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('userTheme', theme);

        // Guardar en la base de datos en segundo plano
        try {
            await window.api.post('api/save_theme.php', { theme: theme });
        } catch (error) {
            console.error('No se pudo guardar el tema en la base de datos:', error);
        }
    }

    themeSelect?.addEventListener('change', (e) => {
        setTheme(e.target.value);
    });

    // 8.3. Lógica de Pestañas
    function setupTabs() {
        const tabNav = document.querySelector('.tab-nav');
        tabNav?.addEventListener('click', (e) => {
            const tabButton = e.target.closest('.tab-link');
            if (!tabButton) return;

            const tabId = tabButton.dataset.tab;
            showTab(tabId);
        });
    }

    function showTab(tabId) {
        document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        const activeTab = document.querySelector(`[data-tab="${tabId}"]`);
        const activeTabContent = document.getElementById(tabId);

        if (activeTab) activeTab.classList.add('active');
        if (activeTabContent) activeTabContent.classList.add('active');

        loadTabContent(tabId);
    }

    const loadedTabs = new Set();
    let agendaManagerInstance = null;
    function loadTabContent(tabId) {
        // Para la agenda, siempre recargamos para tener los datos más frescos.
        if (tabId !== 'agenda-tab' && loadedTabs.has(tabId)) return;

        switch (tabId) {
            case 'sites-tab':
                fetchAdminSites();
                fetchUserSites();
                break;
            case 'agenda-tab':
                if (!agendaManagerInstance) {
                    agendaManagerInstance = new AgendaManager();
                }
                agendaManagerInstance.init();
                break;
        }
        loadedTabs.add(tabId);
    }

    // --- 10. INICIALIZACIÓN ---

    /** Función principal que se ejecuta al cargar el DOM. */
    function init() {
        // Configurar listeners para UI estática
        setupTabs();
        loadSavedTheme();

        // Listeners para modales
        document.querySelectorAll('.close-modal-btn, .close-button').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modalId));
        });
        window.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) closeModal(e.target.id);
        });

        // Listeners para el chat
        chatToggleBtn?.addEventListener('click', () => {
            openModal('chat-modal');
            startChatPolling();
        });
        document.querySelector('#chat-modal .close-button')?.addEventListener('click', stopChatPolling);

        // Cargar contenido de la primera pestaña activa
        const initialActiveTab = document.querySelector('.tab-link.active');
        if (initialActiveTab) {
            showTab(initialActiveTab.dataset.tab);
        }
    }

    init();
});
