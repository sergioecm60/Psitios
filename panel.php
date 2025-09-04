<?php
/**
 * panel.php - Panel de usuario seguro con CSP, sin errores
 */
require_once 'bootstrap.php';
require_auth();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$pdo = get_pdo_connection();
$nonce = base64_encode(random_bytes(16));
$csrf_token = generate_csrf_token();
$admin_id = 1; // Cambia si tu admin tiene otro ID

// Content Security Policy (CSP) segura
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; connect-src 'self';");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Sitios</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/main.css">

    <!-- Estilos con nonce (seguro para CSP) -->
    <style nonce="<?= htmlspecialchars($nonce) ?>">
        /* Chat */
        #chat-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        #chat-modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        #chat-messages {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        #chat-form {
            display: flex;
            gap: 10px;
        }
        #chat-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        #chat-form button {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
        }
        .chat-message {
            position: relative; /* ← Aquí va el estilo, no en el HTML */
            margin: 8px 0;
            padding: 6px 10px;
            border-radius: 12px;
            max-width: 80%;
            line-height: 1.4;
        }
        .chat-message.sent {
            background: #007bff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .chat-message.received {
            background: #e9ecef;
            color: #333;
            margin-right: auto;
        }
        .delete-msg-btn {
            position: absolute;
            right: 0;
            top: 5px;
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .creds-display p {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-copy {
            margin-left: 10px;
            padding: 2px 6px;
            font-size: 0.8rem;
            cursor: pointer;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Datos ocultos para JS -->
    <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" id="admin_id" value="<?= (int)$admin_id ?>">
    <input type="hidden" id="user_id" value="<?= (int)$user_id ?>">

    <div class="container">
        <header class="admin-header">
            <h1>🔐 Mis Sitios (<?= htmlspecialchars($username) ?>)</h1>
            <div class="chat-logout">
                <button id="chat-toggle-btn" class="btn-secondary">💬 Chatear con el Admin</button>
                <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </header>

        <div class="services-grid">
            <?php
            $stmt = $pdo->prepare("
                SELECT s.id, st.id as site_id, st.name, st.url, s.password_needs_update
                FROM services s
                JOIN sites st ON s.site_id = st.id
                WHERE s.user_id = :user_id
                ORDER BY st.name ASC
            ");
            $stmt->execute(['user_id' => $user_id]);
            $services = $stmt->fetchAll();
            ?>

            <?php if (empty($services)): ?>
                <p>No tienes sitios asignados.</p>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <h3><?= htmlspecialchars($service['name']) ?></h3>
                        <?php if ($service['password_needs_update']): ?>
                            <p class="notification">⚠️ Pendiente de actualización</p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($service['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-launch">🌐 Acceder</a>
                        <div class="credentials-area">
                            <button class="btn-view-creds" data-id="<?= (int)$service['id'] ?>">👁️ Ver</button>
                            <button class="btn-notify-expired" data-id="<?= (int)$service['id'] ?>" <?= $service['password_needs_update'] ? 'disabled' : '' ?>>⏳ Notificar</button>
                            <button class="btn-report-problem" data-site-id="<?= (int)$service['site_id'] ?>">🚨 Reportar</button>
                        </div>
                        <div class="creds-display hidden" id="creds-<?= (int)$service['id'] ?>">
                            <p>Cargando...</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Chat -->
    <div id="chat-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>💬 Chat con el Administrador</h3>
                <span class="close-button" data-modal-id="chat-modal">&times;</span>
            </div>
            <div id="chat-messages">
                <p>Cargando mensajes...</p>
            </div>
            <form id="chat-form">
                <input type="text" id="chat-input" placeholder="Escribe un mensaje..." maxlength="255" required>
                <button type="submit">Enviar</button>
            </form>
        </div>
    </div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        const csrfToken = document.getElementById('csrf_token').value;
        const adminId = document.getElementById('admin_id').value;
        const userId = document.getElementById('user_id').value;
        const chatModal = document.getElementById('chat-modal');
        const chatToggleBtn = document.getElementById('chat-toggle-btn');
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');

        // Abrir/cerrar chat
        chatToggleBtn.addEventListener('click', () => chatModal.classList.add('active'));
        document.querySelectorAll('.close-button').forEach(btn => {
            btn.addEventListener('click', () => chatModal.classList.remove('active'));
        });
        window.addEventListener('click', e => {
            if (e.target === chatModal) {
                chatModal.classList.remove('active');
            }
        });

        // Enviar mensaje
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = chatInput.value.trim();
            if (!text) return;

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

        // --- VER CREDENCIALES ---
        document.querySelectorAll('.btn-view-creds').forEach(button => {
            button.addEventListener('click', async function() {
                const serviceId = this.dataset.id;
                const credsDiv = document.getElementById(`creds-${serviceId}`);
                
                if (!credsDiv.classList.contains('hidden')) {
                    credsDiv.classList.add('hidden');
                    return;
                }

                credsDiv.classList.remove('hidden');
                credsDiv.innerHTML = '<p>Obteniendo...</p>';

                try {
                    const response = await fetch('api/get_credentials.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ id: serviceId })
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
            });
        });

        // --- NOTIFICAR EXPIRACIÓN ---
        document.querySelectorAll('.btn-notify-expired').forEach(button => {
            button.addEventListener('click', async function() {
                if (!confirm('¿Contraseña expirada? Se notificará al admin.')) return;
                this.disabled = true;

                try {
                    const response = await fetch('api/notify_expiration.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ id: this.dataset.id })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Notificado.');
                        const card = this.closest('.service-card');
                        const notification = document.createElement('p');
                        notification.className = 'notification';
                        notification.textContent = '⚠️ Pendiente de actualización';
                        card.insertBefore(notification, card.querySelector('.btn-launch'));
                    } else {
                        alert('❌ Error: ' + result.message);
                    }
                } catch (error) {
                    alert('❌ Error de conexión.');
                }
                this.disabled = false;
            });
        });

        // --- REPORTAR PROBLEMA ---
        document.querySelectorAll('.btn-report-problem').forEach(button => {
            button.addEventListener('click', async function() {
                const siteId = this.dataset.siteId;
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
                        alert('❌ Error: ' + result.message);
                    }
                } catch (error) {
                    alert('❌ Error de conexión.');
                }
            });
        });

        // --- COPIAR AL PORTAPAPELES ---
        document.addEventListener('click', e => {
            if (e.target.classList.contains('btn-copy')) {
                const text = e.target.dataset.copy;
                navigator.clipboard.writeText(text).then(() => {
                    const original = e.target.textContent;
                    e.target.textContent = '✅';
                    setTimeout(() => e.target.textContent = original, 1500);
                }).catch(err => {
                    console.error('Error al copiar:', err);
                });
            }
        });

        // --- FUNCIONES AUXILIARES ---
        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
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
    </script>
</body>
</html>