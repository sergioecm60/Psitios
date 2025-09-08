<?php
/**
 * panel.php - Panel de usuario seguro con CSP, sin errores
 */
require_once 'bootstrap.php';
require_auth();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$pdo = get_pdo_connection();

// Obtener el admin asignado al usuario para el chat
$stmt = $pdo->prepare("SELECT assigned_admin_id, theme FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$admin_id = $user_data['assigned_admin_id'] ?? null;
$user_theme = $user_data['theme'] ?? 'light';

$nonce = base64_encode(random_bytes(16));
$csrf_token = generate_csrf_token();

// Content Security Policy (CSP) segura
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; connect-src 'self';");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Sitios</title>
    <link rel="icon" href="<?= BASE_URL ?>favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/panel.css">
</head>
<body data-theme="<?= htmlspecialchars($user_theme) ?>">
    <!-- Datos ocultos para JS -->
    <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" id="admin_id" value="<?= htmlspecialchars($admin_id ?? '') ?>">
    <input type="hidden" id="user_id" value="<?= (int)$user_id ?>">

    <div class="container">
        <header class="admin-header">
            <h1>🔐 Mis Sitios (<?= htmlspecialchars($username) ?>)</h1>
            <div class="chat-logout">
                <div class="theme-selector">
                    <label for="theme-select">🎨 Tema:</label>
                    <select id="theme-select">
                        <option value="light">Claro</option>
                        <option value="dark">Oscuro</option>
                        <option value="blue">Azul</option>
                        <option value="green">Verde</option>
                    </select>
                </div>
                <button id="chat-toggle-btn" class="btn-secondary" <?= !$admin_id ? 'disabled title="No tienes un admin asignado"' : '' ?>>💬 Chatear con el Admin</button>
                <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </header>

        <nav class="tab-nav">
            <button class="tab-link active" data-tab="sites-tab">Sitios</button>
            <button class="tab-link" data-tab="agenda-tab">📅 Mi Agenda</button>
        </nav>

        <!-- Pestaña de Sitios (combinada) -->
        <div id="sites-tab" class="tab-content active">
            <h2>🌐 Sitios Asignados por el Administrador</h2>
            <div id="admin-sites-grid" class="services-grid">
                <div class="loading">Cargando sitios...</div>
            </div>
            
            <hr class="content-divider">

            <h2>🔐 Mis Sitios Personales</h2>
            <button class="btn btn-primary" id="add-user-site-btn">+ Agregar Sitio Personal</button>
            <div id="user-sites-grid" class="services-grid"></div>
        </div>

        <!-- Pestaña de Agenda -->
        <div id="agenda-tab" class="tab-content">
            <h2>📅 Mi Agenda Personal</h2>
            <button class="btn btn-primary" id="add-reminder-btn">+ Añadir Recordatorio</button>
            <div class="table-wrapper">
                <table id="agenda-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Tipo</th>
                            <th>Título</th>
                            <th>Usuario</th>
                            <th>Contraseña</th>
                            <th>Nota</th>
                            <th>Recordatorio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal para Sitios Personales -->
    <div id="user-site-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="user-site-modal-title">Agregar Sitio Personal</h2>
                <span class="close-button" data-modal-id="user-site-modal">&times;</span>
            </div>
            <form id="user-site-form">
                <input type="hidden" id="user-site-id" name="id">
                <div class="form-group">
                    <label for="user-site-name">Nombre del Sitio</label>
                    <input type="text" id="user-site-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="user-site-url">URL</label>
                    <input type="text" id="user-site-url" name="url">
                </div>
                <div class="form-group">
                    <label for="user-site-username">Usuario</label>
                    <input type="text" id="user-site-username" name="username">
                </div>
                <div class="form-group">
                    <label for="user-site-password">Contraseña (dejar en blanco para no cambiar)</label>
                    <input type="password" id="user-site-password" name="password">
                </div>
                <div class="form-group">
                    <label for="user-site-notes">Notas</label>
                    <textarea id="user-site-notes" name="notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('user-site-modal')">Cancelar</button>
                </div>
            </form>
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

    <!-- Modal para Agenda -->
    <div id="reminder-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="reminder-modal-title">Añadir Recordatorio</h2>
                <span class="close-button" data-modal-id="reminder-modal">&times;</span>
            </div>
            <form id="reminder-form">
                <input type="hidden" id="reminder-id" name="id">
                <div class="form-group">
                    <label for="reminder-type">Tipo</label>
                    <select id="reminder-type" name="type" required>
                        <option value="note">Nota</option>
                        <option value="credential">Credencial</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reminder-title">Título</label>
                    <input type="text" id="reminder-title" name="title" required>
                </div>
                <div class="form-group credential-field">
                    <label for="reminder-username">Usuario</label>
                    <input type="text" id="reminder-username" name="username">
                </div>
                <div class="form-group credential-field">
                    <label for="reminder-password">Contraseña (dejar en blanco para no cambiar)</label>
                    <input type="password" id="reminder-password" name="password">
                </div>
                <div class="form-group">
                    <label for="reminder-notes">Nota / Descripción</label>
                    <textarea id="reminder-notes" name="notes"></textarea>
                </div>
                <div class="form-group">
                    <label for="reminder-datetime">Fecha y Hora de Recordatorio (opcional)</label>
                    <input type="datetime-local" id="reminder-datetime" name="reminder_datetime">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reminder-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/panel.js" nonce="<?= htmlspecialchars($nonce) ?>" defer></script>
</body>
</html>