<?php
require_once 'bootstrap.php';
require_auth('admin');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$user_role = $_SESSION['user_role'] ?? 'user';
$user_id = $_SESSION['user_id'];
$pdo = get_pdo_connection();
$stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$user_theme = $user_data['theme'] ?? 'light';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="icon" href="<?= BASE_URL ?>favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/admin.css">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <link rel="stylesheet" href="assets/css/notifications_panel.css">
</head>
<body 
    data-csrf-token="<?php echo $csrf_token; ?>"
    data-user-role="<?php echo $user_role; ?>"
    data-theme="<?= htmlspecialchars($user_theme) ?>"
    data-company-id="<?php echo $_SESSION['company_id'] ?? ''; ?>"
    data-branch-id="<?php echo $_SESSION['branch_id'] ?? ''; ?>"
    data-department-id="<?php echo $_SESSION['department_id'] ?? ''; ?>"
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
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password">
                </div>
                <div class="form-group">
                    <label for="role">Rol</label>
                    <select id="role" name="role">
                        <option value="user">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="is_active" name="is_active" value="1"> Activo</label>
                </div>
                <div class="form-group">
                    <label for="company_id">Empresa</label>
                    <select id="company_id" name="company_id">
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch_id">Sucursal</label>
                    <select id="branch_id" name="branch_id">
                        <option value="">Seleccionar sucursal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department_id">Departamento</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Seleccionar departamento</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sitios Asignados</label>
                    <div id="sites-container"></div>
                </div>
                <div class="form-group" id="admin-assignment-group" style="display: none;">
                    <label for="assigned_admin_id">Admin Asignado</label>
                    <select id="assigned_admin_id" name="assigned_admin_id">
                        <option value="">Ninguno (usuario sin admin)</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('user-modal')">Cancelar</button>
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
                    <input type="text" id="company-name" name="name" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('company-modal')">Cancelar</button>
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
                    <input type="text" id="branch-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="branch-company-id">Empresa</label>
                    <select id="branch-company-id" name="company_id" required>
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch-country-id">País</label>
                    <select id="branch-country-id" name="country_id" required>
                        <option value="">Seleccionar país</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch-province">Provincia</label>
                    <select id="branch-province" name="province" required>
                        <option value="">Seleccionar provincia</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('branch-modal')">Cancelar</button>
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
                    <input type="text" id="department-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="department-company-id">Empresa</label>
                    <select id="department-company-id" name="company_id" required>
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department-branch-id">Sucursal</label>
                    <select id="department-branch-id" name="branch_id" required>
                        <option value="">Seleccionar sucursal</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('department-modal')">Cancelar</button>
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
                    <input type="text" id="site-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="site-url">URL del Sitio</label>
                    <input type="text" id="site-url" name="url" placeholder="ej: 192.168.0.1/misitio o https://google.com" required>
                </div>
                <div class="form-group">
                    <label for="site-username">Usuario</label>
                    <input type="text" id="site-username" name="username" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="site-password">Nueva Contraseña (opcional)</label>
                    <div class="password-wrapper">
                        <input type="password" id="site-password" name="password" autocomplete="new-password">
                        <button type="button" class="toggle-password">Mostrar</button>
                    </div>
                    <small>Deje en blanco para mantener la contraseña actual.</small>
                </div>
                <div class="form-group" id="site-visibility-group" style="display: none;">
                    <label for="site-visibility">Visibilidad</label>
                    <select id="site-visibility" name="visibility"><option value="private">Privado (Solo para mí)</option><option value="shared">Compartido (Para todos los admins)</option></select>
                </div>
                <div class="form-group">
                    <label for="site-notes">Notas</label>
                    <textarea id="site-notes" name="notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('site-modal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
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

    <script src="assets/js/admin.js" defer></script>
    <script src="assets/js/notifications_panel.js"></script>
</body>
</html>