// --- Global Functions for Modals ---
// These need to be global to be called by `onclick` attributes in the HTML.
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active'); // Use class for display
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active'); // Use class for display
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.getElementById('csrf_token')?.value;
    const adminId = document.getElementById('admin_id')?.value;
    const userId = document.getElementById('user_id')?.value;
    const chatModal = document.getElementById('chat-modal');
    const chatToggleBtn = document.getElementById('chat-toggle-btn'); // Assuming this always exists
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');

    // ✅ Función apiCall definida para resolver el ReferenceError
    async function apiCall(url, method = 'GET', body = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        };
        if (body) options.body = JSON.stringify(body);
        const response = await fetch(url, options);
        return response.json();
    }

    // --- Lógica de Pestañas ---
    const tabs = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    function loadTabContent(tabId) {
        switch (tabId) {
            case 'admin-sites-tab':
                fetchAdminSites();
                break;
            case 'user-sites-tab':
                fetchUserSites();
                break;
            case 'agenda-tab':
                loadAgenda();
                break;
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            const activeTabId = tab.dataset.tab;
            const activeTabContent = document.getElementById(activeTabId);
            if (activeTabContent) activeTabContent.classList.add('active');
            loadTabContent(activeTabId);
        });
    });

    // --- Generic Modal Handling ---
    chatToggleBtn?.addEventListener('click', () => openModal('chat-modal'));

    document.querySelectorAll('.close-button').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.dataset.modalId;
            if (modalId) closeModal(modalId);
        });
    });

    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) e.target.classList.remove('active');
    });

    // Enviar mensaje
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;
        if (!adminId) {
            alert('No se puede enviar el mensaje. No tienes un administrador asignado.');
            chatInput.value = '';
            return;
        }

        try {
            const response = await fetch('api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    message: text,
                    receiver_id: adminId
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const result = await response.json();
            if (result.success) {
                chatInput.value = '';
                fetchChatMessages();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error de conexión: ' + error.message);
        }
    });

    // Cargar mensajes
    async function fetchChatMessages() {
        try {
            const response = await fetch('api/get_messages.php');

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const data = await response.json();
            if (data.success) {
                chatMessages.innerHTML = data.messages.map(msg => {
                    const isSent = msg.sender_id == userId;
                    return `
                        <div class="chat-message ${isSent ? 'sent' : 'received'}">
                            <strong>${isSent ? 'Tú' : 'Admin'}:</strong> ${escapeHTML(msg.message)}
                            <br><small>${formatTime(msg.created_at)}</small>
                            ${isSent ? `<button class="delete-msg-btn" data-id="${msg.id}" title="Eliminar">×</button>` : ''}
                        </div>
                    `;
                }).join('');
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } catch (error) {
            chatMessages.innerHTML = `<p class="error">❌ ${error.message}</p>`;
        }
    }

    // Polling cada 10 segundos
    setInterval(fetchChatMessages, 10000);
    chatToggleBtn.addEventListener('click', () => setTimeout(fetchChatMessages, 500));

    // Eliminar mensaje
    chatMessages.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-msg-btn');
        if (deleteBtn && confirm('¿Eliminar este mensaje?')) {
            const id = deleteBtn.dataset.id;
            try {
                const response = await fetch('api/delete_user_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ message_id: id })
                });
                const result = await response.json();
                if (result.success) {
                    fetchChatMessages();
                } else {
                    alert('No se pudo eliminar: ' + result.message);
                }
            } catch (error) {
                alert('Error de conexión.');
            }
        }
    });

    // --- CARGAR SITIOS ---
    async function fetchAdminSites() {
        const grid = document.getElementById('admin-sites-grid');
        if (!grid) return;
        grid.innerHTML = '<div class="loading">Cargando sitios...</div>';
        try {
            const result = await apiCall('api/get_user_sites.php');
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

    async function fetchUserSites() {
        const grid = document.getElementById('user-sites-grid');
        if (!grid) return;
        grid.innerHTML = '<div class="loading">Cargando tus sitios...</div>';
        try {
            const result = await apiCall('api/get_user_sites_personal.php');
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

    function createServiceCard(item, isAssigned, isPersonal = false) {
        const id = isAssigned ? item.service_id : item.id;
        const siteId = isAssigned ? item.site_id : item.id;
        const hasPassword = item.has_password || item.password_encrypted;

        return `
            <div class="service-card">
                <h3>${escapeHTML(item.name)}</h3>
                ${isAssigned && item.password_needs_update ? '<p class="notification">⚠️ Contraseña pendiente</p>' : ''}
                ${item.url ? `<a href="${escapeHTML(item.url)}" target="_blank" rel="noopener noreferrer" class="btn-launch">🌐 Acceder</a>` : ''}
                <div class="credentials-area">
                    ${hasPassword ? `<button class="btn-view-creds" data-id="${id}" data-type="${isAssigned ? 'assigned' : 'personal'}">👁️ Ver</button>` : ''}
                    ${isAssigned ? `<button class="btn-notify-expired" data-id="${id}" ${item.password_needs_update ? 'disabled' : ''}>⏳ Notificar</button>` : ''}
                    ${isAssigned ? `<button class="btn-report-problem" data-site-id="${siteId}">🚨 Reportar</button>` : ''}
                    ${isPersonal ? `<button class="btn btn-sm btn-secondary btn-edit-site" data-id="${id}" data-type="personal">✏️ Editar</button>` : ''}
                    ${isPersonal ? `<button class="btn btn-sm btn-danger btn-delete-site" data-id="${id}" data-type="personal">🗑️ Eliminar</button>` : ''}
                </div>
                <div class="creds-display hidden" id="creds-${isAssigned ? 'a' : 'p'}-${id}"></div>
            </div>
        `;
    }

    // Carga inicial de la primera pestaña activa
    const initialActiveTab = document.querySelector('.tab-link.active');
    if (initialActiveTab) loadTabContent(initialActiveTab.dataset.tab);

    // --- MANEJO DE BOTONES DE SITIOS (VER, NOTIFICAR, REPORTAR) ---
    document.querySelector('.container').addEventListener('click', async (e) => {
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

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(body)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Error ${response.status}: ${errorText}`);
                }

                const result = await response.json();
                if (result.success) {
                    credsDiv.innerHTML = `
                        <p><strong>👤 Usuario:</strong> 
                           <span>${escapeHTML(result.data.username)}</span> 
                           <button class="btn-copy" data-copy="${escapeHTML(result.data.username)}">📋</button>
                        </p>
                        <p><strong>🔑 Contraseña:</strong> 
                           <span>${escapeHTML(result.data.password)}</span> 
                           <button class="btn-copy" data-copy="${escapeHTML(result.data.password)}">📋</button>
                        </p>
                    `;
                } else {
                    throw new Error(result.message || 'No se pudieron obtener las credenciales.');
                }
            } catch (error) {
                credsDiv.innerHTML = `<p class="error">❌ ${error.message}</p>`;
            }
        }

        const notifyBtn = e.target.closest('.btn-notify-expired');
        if (notifyBtn) {
            if (!confirm('¿Contraseña expirada? Se notificará al admin.')) return;
            notifyBtn.disabled = true;

            try {
                const response = await fetch('api/notify_expiration.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ id: notifyBtn.dataset.id })
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Notificado.');
                    const card = notifyBtn.closest('.service-card');
                    if (card && !card.querySelector('.notification')) {
                        const notification = document.createElement('p');
                        notification.className = 'notification';
                        notification.textContent = '⚠️ Pendiente de actualización';
                        card.insertBefore(notification, card.querySelector('.btn-launch'));
                    }
                } else {
                    alert('❌ Error: ' + (result.message || 'No se pudo notificar.'));
                }
            } catch (error) {
                alert('❌ Error de conexión.');
            } finally {
                notifyBtn.disabled = false;
            }
        }

        const reportBtn = e.target.closest('.btn-report-problem');
        if (reportBtn) {
            const siteId = reportBtn.dataset.siteId;
            if (!confirm('¿Reportar problema con este sitio?')) return;

            try {
                const response = await fetch('api/report_problem.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ site_id: siteId })
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Problema reportado.');
                } else {
                    alert('❌ Error: ' + (result.message || 'No se pudo reportar.'));
                }
            } catch (error) {
                alert('❌ Error de conexión.');
            }
        }

        const editSiteBtn = e.target.closest('.btn-edit-site');
        if (editSiteBtn) {
            const id = editSiteBtn.dataset.id;
            const result = await apiCall(`api/get_user_sites_personal.php?id=${id}`);
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
            }
        }

        const deleteSiteBtn = e.target.closest('.btn-delete-site');
        if (deleteSiteBtn) {
            if (!confirm('¿Estás seguro de que quieres eliminar este sitio personal?')) return;
            const id = deleteSiteBtn.dataset.id;
            const result = await apiCall('api/delete_user_site.php', 'POST', { id });
            if (result.success) {
                alert('Sitio eliminado.');
                fetchUserSites();
            } else {
                alert('Error al eliminar el sitio: ' + result.message);
            }
        }
    });

    // --- AGENDA PERSONAL ---
    const agendaTableBody = document.querySelector('#agenda-table tbody');
    const reminderModal = document.getElementById('reminder-modal');
    const reminderForm = document.getElementById('reminder-form');
    const reminderTypeSelect = document.getElementById('reminder-type');

    document.getElementById('add-reminder-btn')?.addEventListener('click', () => {
        reminderForm.reset();
        document.getElementById('reminder-id').value = '';
        document.getElementById('reminder-modal-title').textContent = 'Añadir Recordatorio';
        // Asegurarse de que los campos de credenciales se muestren u oculten correctamente
        toggleCredentialFields(); 
        openModal('reminder-modal');
    });

    async function loadAgenda() {
        if (!agendaTableBody) return;
        const result = await apiCall('api/get_user_reminders.php');
        agendaTableBody.innerHTML = '';
        if (result.success) {
            result.data.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="complete-reminder" data-id="${item.id}" ${item.is_completed == 1 ? 'checked' : ''}></td>
                    <td>${item.type === 'credential' ? '🔑' : '📝'}</td>
                    <td>${escapeHTML(item.title)}</td>
                    <td>${escapeHTML(item.username || '')}</td>
                    <td>${item.has_password ? `<button class="btn btn-sm btn-secondary decrypt-pass" data-id="${item.id}" data-type="reminder">Mostrar</button>` : ''}</td>
                    <td>${escapeHTML(item.notes || '')}</td>
                    <td>${item.reminder_datetime ? new Date(item.reminder_datetime).toLocaleString() : ''}</td>
                    <td><button class="btn btn-sm btn-danger delete-reminder" data-id="${item.id}">Eliminar</button></td>
                `;
                agendaTableBody.appendChild(tr);
            });
        }
    }

    agendaTableBody?.addEventListener('click', async (e) => {
        const decryptBtn = e.target.closest('.decrypt-pass');
        if (decryptBtn) {
            const id = decryptBtn.dataset.id;
            try {
                const res = await fetch('api/decrypt_user_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ id: id, type: 'reminder' })
                });
                const result = await res.json();
                if (result.success) alert(`Usuario: ${result.data.username}\nContraseña: ${result.data.password}`);
                else alert('Error: ' + result.message);
            } catch (err) {
                alert('Error al desencriptar.');
            }
        }

        const deleteBtn = e.target.closest('.delete-reminder');
        if (deleteBtn) {
            if (!confirm('¿Eliminar este recordatorio?')) return;
            const id = deleteBtn.dataset.id;
            const result = await apiCall('api/delete_user_reminder.php', 'POST', { id });
            if (result.success) {
                loadAgenda();
            } else {
                alert('Error: ' + result.message);
            }
        }

        const completeCheck = e.target.closest('.complete-reminder');
        if (completeCheck) {
            const id = completeCheck.dataset.id;
            await apiCall('api/save_user_reminder.php', 'POST', {
                action: 'toggle_complete',
                id: id
            });
        }
    });

    // --- LÓGICA DE MODALES ---

    // Modal de Sitios Personales
    document.getElementById('add-user-site-btn')?.addEventListener('click', () => {
        const form = document.getElementById('user-site-form');
        form.reset();
        document.getElementById('user-site-id').value = '';
        document.getElementById('user-site-modal-title').textContent = 'Agregar Sitio Personal';
        openModal('user-site-modal');
    });

    document.getElementById('user-site-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        const result = await apiCall('api/save_user_site.php', 'POST', data);
        if (result.success) {
            closeModal('user-site-modal');
            fetchUserSites();
        } else {
            alert('Error: ' + result.message);
        }
    });

    // Modal de Agenda
    function toggleCredentialFields() {
        const type = reminderTypeSelect.value;
        const fields = reminderModal.querySelectorAll('.credential-field');
        fields.forEach(field => {
            field.style.display = type === 'credential' ? 'block' : 'none';
        });
    }

    reminderTypeSelect?.addEventListener('change', toggleCredentialFields);

    reminderForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        const result = await apiCall('api/save_user_reminder.php', 'POST', data);
        if (result.success) {
            closeModal('reminder-modal');
            loadAgenda();
        } else {
            alert('Error: ' + result.message);
        }
    });

    // --- COPIAR AL PORTAPAPELES ---
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

    // --- FUNCIONES AUXILIARES ---
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

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

    // --- Selector de Tema ---
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
            await apiCall('api/save_theme.php', 'POST', { theme: theme });
        } catch (error) {
            console.error('No se pudo guardar el tema en la base de datos:', error);
        }
    }

    themeSelect?.addEventListener('change', (e) => {
        setTheme(e.target.value);
    });

    loadSavedTheme();
});