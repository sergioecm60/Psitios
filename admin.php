<?php
/**
 * /Psitios/admin.php - Panel de Administración para roles 'admin' y 'superadmin'.
 *
 * Proporciona la interfaz para gestionar usuarios, empresas, sitios, y más.
 * La lógica de la interfaz es manejada principalmente por assets/js/admin.js.
 */
require_once 'bootstrap.php';
require_auth('admin'); // Solo permite acceso a usuarios con rol 'admin' o 'superadmin'

// --- Preparación de datos para la vista ---
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Obtener el tema del usuario desde la BD
$pdo = get_pdo_connection();
$stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_theme = $stmt->fetchColumn() ?: 'light';

// Generar un nonce para la Política de Seguridad de Contenido (CSP)
$nonce = base64_encode(random_bytes(16));

// --- Headers de Seguridad ---
// Previene ataques de XSS al restringir de dónde se pueden cargar los scripts.
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; connect-src 'self';");
// El error reporting ya está configurado en bootstrap.php, no es necesario repetirlo aquí.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="icon" href="<?= BASE_URL ?>favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/admin.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/css/notifications_panel.css">
    <style nonce="<?= $nonce ?>">
        .hidden { display: none !important; }
        .site-assignment-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
            background-color: var(--background-color-light);
            border: 1px solid var(--border-color);
        }
        .site-assignment-row label {
            flex-grow: 1;
            font-weight: 500;
        }
        .site-assignment-row input[type="text"],
        .site-assignment-row input[type="password"] {
            width: 180px;
        }
    </style>
</head>
<body 
    data-csrf-token="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
    data-user-role="<?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?>"
    data-theme="<?= htmlspecialchars($user_theme) ?>"
    data-company-id="<?= htmlspecialchars($_SESSION['company_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-branch-id="<?= htmlspecialchars($_SESSION['branch_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-department-id="<?= htmlspecialchars($_SESSION['department_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="container">
        <header>
            <h1>Panel de Administración</h1>
            <div class="theme-selector">
                <label for="theme-select">🎨 Tema:</label>
                <select id="theme-select">
                    <option value="light">Claro</option>
                    <option value="dark">Oscuro</option>
                    <option value="blue">Azul</option>
                    <option value="green">Verde</option>
                </select>
            </div>
            <a href="logout.php">Cerrar Sesión</a>
        </header>
        <nav class="tab-nav">
            <button class="tab-link" data-tab="audit-tab">📋 Auditoría</button>
            <button class="tab-link active" data-tab="users-tab">Usuarios</button>
            <?php if ($user_role === 'superadmin'): ?>
            <button class="tab-link" data-tab="companies-tab">🏢 Empresas</button>
            <button class="tab-link" data-tab="branches-tab">📍 Sucursales</button>
            <button class="tab-link" data-tab="departments-tab">🏛️ Departamentos</button>
            <?php endif; ?>
            <button class="tab-link" data-tab="sites-tab">Sitios</button>
            <button class="tab-link" data-tab="messages-tab">💬 Mensajes</button>
            <button class="tab-link" data-tab="notifications-tab">Notificaciones</button>
        </nav>

        <!-- Pestaña de Auditoría -->
        <div id="audit-tab" class="tab-content">
            <h2>📋 Bitácora de Actividades</h2>
            <div class="table-wrapper">
                <table id="audit-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Servicio</th>
                            <th>IP</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody id="audit-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Usuarios -->
        <div id="users-tab" class="tab-content active">
            <h2>👥 Gestión de Usuarios</h2>
            <button class="btn btn-primary" id="add-user-btn">+ Agregar Usuario</button>
            <div class="table-wrapper">
                <table id="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Empresa</th>
                            <th>Sucursal</th>
                            <th>Provincia</th>
                            <th>Departamento</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body"></tbody>
                </table>
            </div>
        </div>

        <?php if ($user_role === 'superadmin'): ?>
        <!-- Pestaña de Empresas -->
        <div id="companies-tab" class="tab-content">
            <h2>🏢 Gestión de Empresas</h2>
            <button class="btn btn-primary" id="add-company-btn">+ Agregar Empresa</button>
            <div class="table-wrapper">
                <table id="companies-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="companies-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Sucursales -->
        <div id="branches-tab" class="tab-content">
            <h2>📍 Gestión de Sucursales</h2>
            <button class="btn btn-primary" id="add-branch-btn">+ Agregar Sucursal</button>
            <div class="table-wrapper">
                <table id="branches-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>País</th>
                            <th>Provincia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="branches-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Departamentos -->
        <div id="departments-tab" class="tab-content">
            <h2>🏛️ Gestión de Departamentos</h2>
            <button class="btn btn-primary" id="add-department-btn">+ Agregar Departamento</button>
            <div class="table-wrapper">
                <table id="departments-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Sucursal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="departments-table-body"></tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pestaña de Sitios -->
        <div id="sites-tab" class="tab-content">
            <h2>🌐 Gestión de Sitios</h2>
            <button class="btn btn-primary" id="add-site-btn">+ Agregar Sitio</button>
            <div class="table-wrapper">
                <table id="sites-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>URL</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="sites-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Mensajes -->
        <div id="messages-tab" class="tab-content">
            <h2>💬 Mensajes de Usuarios</h2>
            <div class="table-wrapper">
                <table id="messages-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Mensaje</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="messages-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Notificaciones -->
        <div id="notifications-tab" class="tab-content">
            <h2>📬 Panel de Notificaciones</h2>
            <div class="notifications-header">
                <div class="notifications-title">
                    <span id="notification-badge" class="notification-badge">0</span>
                </div>
                <div class="header-buttons">
                    <button class="mark-all-read-btn hidden" id="mark-all-btn">Marcar todas como leídas</button>
                    <button class="retry-btn hidden" id="retry-btn">Reintentar</button>
                </div>
            </div>
            <div id="notification-error" class="error-message"></div>
            <div id="notification-success" class="success-message"></div>
            <div class="notifications-container">
                <div id="notification-container" class="loading">🔄 Cargando notificaciones...</div>
            </div>
        </div>
    </div>

    <!-- Modal para Usuario -->
    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="user-modal-title">Agregar Usuario</h2>
                <span class="close-btn" data-modal-id="user-modal">&times;</span>
            </div>
            <form id="user-form">
                <input type="hidden" id="user-id" name="id">
                <input type="hidden" id="user-action" name="action">
                <div class="form-group">
                    <label for="username">Nombre de Usuario</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="role">Rol</label>
                    <select id="role" name="role" autocomplete="off">
                        <option value="user">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="is_active" name="is_active" value="1"> Activo</label>
                </div>
                <div class="form-group">
                    <label for="company_id">Empresa</label>
                    <select id="company_id" name="company_id" autocomplete="off">
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch_id">Sucursal</label>
                    <select id="branch_id" name="branch_id" autocomplete="off">
                        <option value="">Seleccionar sucursal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department_id">Departamento</label>
                    <select id="department_id" name="department_id" autocomplete="off">
                        <option value="">Seleccionar departamento</option>
                    </select>
                </div>
                <fieldset class="form-group">
                    <legend>Sitios Asignados</legend>
                    <div id="sites-container"></div>
                </fieldset>
                <div class="form-group hidden" id="admin-assignment-group">
                    <label for="assigned_admin_id">Admin Asignado</label>
                    <select id="assigned_admin_id" name="assigned_admin_id">
                        <option value="">Ninguno (usuario sin admin)</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary close-modal-btn" data-modal-id="user-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Empresa -->
    <div id="company-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="company-modal-title">Agregar Empresa</h2>
                <span class="close-btn" data-modal-id="company-modal">&times;</span>
            </div>
            <form id="company-form">
                <input type="hidden" id="company-id" name="id">
                <input type="hidden" id="company-action" name="action">
                <div class="form-group">
                    <label for="company-name">Nombre de la Empresa</label>
                    <input type="text" id="company-name" name="name" required autocomplete="organization">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary close-modal-btn" data-modal-id="company-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Sucursal -->
    <div id="branch-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="branch-modal-title">Agregar Sucursal</h2>
                <span class="close-btn" data-modal-id="branch-modal">&times;</span>
            </div>
            <form id="branch-form">
                <input type="hidden" id="branch-id" name="id">
                <input type="hidden" id="branch-action" name="action">
                <div class="form-group">
                    <label for="branch-name">Nombre</label>
                    <input type="text" id="branch-name" name="name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="branch-company-id">Empresa</label>
                    <select id="branch-company-id" name="company_id" required autocomplete="off">
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch-country-id">País</label>
                    <select id="branch-country-id" name="country_id" required autocomplete="off">
                        <option value="">Seleccionar país</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch-province">Provincia</label>
                    <select id="branch-province" name="province" required autocomplete="off">
                        <option value="">Seleccionar provincia</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary close-modal-btn" data-modal-id="branch-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Departamento -->
    <div id="department-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="department-modal-title">Agregar Departamento</h2>
                <span class="close-btn" data-modal-id="department-modal">&times;</span>
            </div>
            <form id="department-form">
                <input type="hidden" id="department-id" name="id">
                <input type="hidden" id="department-action" name="action">
                <div class="form-group">
                    <label for="department-name">Nombre del Departamento</label>
                    <input type="text" id="department-name" name="name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="department-company-id">Empresa</label>
                    <select id="department-company-id" name="company_id" required autocomplete="off">
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department-branch-id">Sucursal</label>
                    <select id="department-branch-id" name="branch_id" required autocomplete="off">
                        <option value="">Seleccionar sucursal</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary close-modal-btn" data-modal-id="department-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Sitio -->
    <div id="site-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="site-modal-title">Agregar Sitio</h2>
                <span class="close-btn" data-modal-id="site-modal">&times;</span>
            </div>
            <form id="site-form">
                <input type="hidden" id="site-id" name="id">
                <input type="hidden" id="site-action" name="action">
                <div class="form-group">
                    <label for="site-name">Nombre del Sitio</label>
                    <input type="text" id="site-name" name="name" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="site-url">URL del Sitio</label>
                    <input type="text" id="site-url" name="url" placeholder="ej: 192.168.0.1/misitio o https://google.com" required autocomplete="url">
                </div>
                <div class="form-group" id="site-username-group">
                    <label for="site-username">Usuario</label>
                    <input type="text" id="site-username" name="username" autocomplete="username">
                </div>
                <div class="form-group" id="site-password-group">
                    <label for="site-password">Nueva Contraseña (opcional)</label>
                    <div class="password-wrapper">
                        <input type="password" id="site-password" name="password" autocomplete="new-password">
                        <button type="button" class="toggle-password">Mostrar</button>
                    </div>
                    <small>Deje en blanco para mantener la contraseña actual.</small>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="site-is-sso" name="is_sso" value="1"> Es un sitio SSO (Single Sign-On)</label>
                    <small>Si se marca, se usará el flujo de inicio de sesión automático y solo se mostrará el botón "Ingresar".</small>
                </div>
                <div class="form-group hidden" id="site-visibility-group">
                    <label for="site-visibility">Visibilidad</label>
                    <select id="site-visibility" name="visibility"><option value="private">Privado (Solo para mí)</option><option value="shared">Compartido (Para todos los admins)</option></select>
                </div>
                <div class="form-group">
                    <label for="site-notes">Notas</label>
                    <textarea id="site-notes" name="notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary close-modal-btn" data-modal-id="site-modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js" nonce="<?= $nonce ?>" defer></script>
    <script src="assets/js/admin.js" nonce="<?= $nonce ?>" defer></script>
    <script src="assets/js/notifications_panel.js" nonce="<?= $nonce ?>" defer></script>
</body>
</html>