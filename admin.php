<?php
require_once 'bootstrap.php';
require_auth('admin');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$user_role = $_SESSION['user_role'] ?? 'user';
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
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7f9; margin: 0; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 15px; margin-bottom: 20px; }
        header h1 { margin: 0; font-size: 24px; }
        header a { color: #007bff; text-decoration: none; }
        .tab-nav { display: flex; border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
        .tab-link { background: none; border: none; padding: 10px 20px; cursor: pointer; font-size: 16px; color: #495057; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-link.active { color: #007bff; border-color: #007bff; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:hover { background-color: #f1f1f1; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .actions a { margin-right: 8px; }
        .status-toggle { cursor: pointer; padding: 4px 8px; border-radius: 12px; color: white; font-size: 12px; }
        .status-toggle.active { background-color: #28a745; }
        .status-toggle.inactive { background-color: #dc3545; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .modal-header h2 { margin: 0; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .password-wrapper { position: relative; display: flex; align-items: stretch; }
        .password-wrapper input { border-top-right-radius: 0; border-bottom-right-radius: 0; flex-grow: 1; }
        .toggle-password {
            border: 1px solid #ccc;
            border-left: none;
            background-color: #f8f9fa;
            cursor: pointer;
            padding: 0 12px;
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
        }
        .form-check { margin-bottom: 8px; }
        .form-actions { text-align: right; margin-top: 20px; }
    </style>
    <link rel="stylesheet" href="assets/css/notifications_panel.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Panel de Administración</h1>
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
                            <th>Requiere actualización</th>
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

    <script>
    const CSRF_TOKEN = '<?php echo $csrf_token; ?>';
    const CURRENT_USER_ROLE = '<?php echo $_SESSION['user_role']; ?>';
    const CURRENT_USER_COMPANY_ID = '<?php echo $_SESSION['company_id'] ?? ''; ?>';
    const CURRENT_USER_BRANCH_ID = '<?php echo $_SESSION['branch_id'] ?? ''; ?>';
    const CURRENT_USER_DEPARTMENT_ID = '<?php echo $_SESSION['department_id'] ?? ''; ?>';

    // Se mueve fuera para que sea accesible globalmente por los atributos onclick=""
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        document.querySelectorAll('.close-btn').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modalId));
        });
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });

        async function apiCall(url, method = 'GET', body = null) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            };
            if (body) {
                options.body = JSON.stringify(body);
            }
            
            try {
                console.log('🔄 API Call to:', url, options);
                
                const response = await fetch(url, options);
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', [...response.headers.entries()]);
                
                // ✅ CORRECCIÓN: Leer el texto UNA SOLA VEZ
                const text = await response.text();
                console.log('📝 Raw response text:', text);
                console.log('📏 Text length:', text.length);
                
                if (!text || text.trim() === '') {
                    throw new Error(`Respuesta vacía del servidor (Estado: ${response.status})`);
                }

                // ✅ CORRECCIÓN: Parsear el texto ya leído
                let data;
                try {
                    data = JSON.parse(text);
                    console.log('✅ Parsed JSON:', data);
                } catch (parseError) {
                    console.error('❌ Error parsing JSON:', parseError);
                    console.error('📄 Response text:', text);
                    throw new Error('Respuesta del servidor no es JSON válido: ' + text.substring(0, 100));
                }

                // ✅ CORRECCIÓN: Verificar el estado después de parsear
                if (!response.ok) {
                    let errorMessage = data.message || `Error ${response.status}`;
                    if (data.error) {
                        errorMessage += `\nDetalles: ${data.error}`;
                    }
                    throw new Error(errorMessage);
                }

                return data;
            } catch (error) {
                console.error('💥 API Call Error:', error);
                alert('Error: ' + error.message);
                return null;
            }
        }

        function safeDate(dateString) {
            if (!dateString) return 'No especificado';
            const date = new Date(dateString);
            return isNaN(date.getTime()) 
                ? 'Fecha inválida' 
                : date.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe
                 .toString()
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // --- USUARIOS ---
        const usersTableBody = document.querySelector('#users-table-body');
        const userForm = document.getElementById('user-form');

        async function fetchUsers() {
            const result = await apiCall('api/manage_users.php?action=list');
            if (result && result.success) {
                renderUsersTable(result.data);
            }
        }

        function renderUsersTable(users) {
            usersTableBody.innerHTML = '';
            if (users.length === 0) {
                usersTableBody.innerHTML = '<tr><td colspan="7">No hay usuarios para mostrar.</td></tr>';
                return;
            }
            users.forEach(user => {
                const tr = document.createElement('tr');

                const companyName = user.company_name || (user.role === 'superadmin' ? 'Global' : 'N/A');
                const branchName = user.branch_name || (user.role === 'superadmin' ? 'Global' : 'N/A');
                const province = user.province || (user.role === 'superadmin' ? 'Global' : 'N/A');
                const departmentName = user.department_name || (user.role === 'superadmin' ? 'Global' : 'N/A');

                tr.innerHTML = `
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.role)}</td>
                    <td>${escapeHtml(companyName)}</td>
                    <td>${escapeHtml(branchName)}</td>
                    <td>${escapeHtml(province)}</td>
                    <td>${escapeHtml(departmentName)}</td>
                    <td>
                        <span class="status-toggle ${user.is_active ? 'active' : 'inactive'}">
                            ${user.is_active ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-user-btn" data-user-id="${user.id}">Editar</button>
                        <button class="btn btn-sm btn-danger delete-user-btn" data-user-id="${user.id}">Eliminar</button>
                    </td>
                `;
                usersTableBody.appendChild(tr);
            });
        }

        document.getElementById('add-user-btn').addEventListener('click', () => {
            userForm.reset();
            document.getElementById('user-id').value = '';
            document.getElementById('user-action').value = 'add';
            document.getElementById('user-modal-title').textContent = 'Agregar Usuario';
            document.getElementById('is_active').checked = true;
            loadCompanies();
            loadDepartments();
            loadSitesForUser();

            if (CURRENT_USER_ROLE === 'admin') {
                document.getElementById('company_id').value = CURRENT_USER_COMPANY_ID;
                document.getElementById('branch_id').value = CURRENT_USER_BRANCH_ID;
                document.getElementById('company_id').disabled = true;
                document.getElementById('branch_id').disabled = true;
                document.getElementById('department_id').value = CURRENT_USER_DEPARTMENT_ID;
                document.getElementById('department_id').disabled = true;
            }
            openModal('user-modal');
        });

        usersTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('delete-user-btn')) {
                e.preventDefault();
                if (!confirm('¿Eliminar este usuario?')) return;
                const id = e.target.dataset.userId;
                const result = await apiCall('api/manage_users.php', 'POST', { action: 'delete', id });
                if (result && result.success) {
                    fetchUsers();
                }
            }

            if (e.target.classList.contains('edit-user-btn')) {
                e.preventDefault();
                const id = e.target.dataset.userId;
                const result = await apiCall(`api/manage_users.php?action=get&id=${id}`);
                if (result && result.success) {
                    const user = result.data;
                    userForm.reset();
                    document.getElementById('user-modal-title').textContent = 'Editar Usuario';
                    document.getElementById('user-action').value = 'edit';
                    document.getElementById('user-id').value = user.id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('role').value = user.role;
                    document.getElementById('is_active').checked = user.is_active;
                    loadCompanies(user.company_id);
                    loadBranches(user.company_id, user.branch_id, user.department_id);
                    setTimeout(() => loadSitesForUser(user.id), 300);

                    if (CURRENT_USER_ROLE === 'admin') {
                        document.getElementById('company_id').disabled = true;
                        document.getElementById('branch_id').disabled = true;
                        document.getElementById('department_id').disabled = true;
                    }

                    openModal('user-modal');
                }
            }
        });

        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const usernameInput = document.getElementById('username');
            const username = usernameInput.value.trim();
            if (!username) {
                alert('El nombre de usuario es requerido.');
                usernameInput.focus();
                return;
            }

            const data = {
                id: document.getElementById('user-id').value || null,
                action: document.getElementById('user-action').value,
                username: username,
                password: document.getElementById('password').value || null,
                role: document.getElementById('role').value,
                is_active: document.getElementById('is_active').checked,
                company_id: document.getElementById('company_id').value || null,
                branch_id: document.getElementById('branch_id').value || null,
                department_id: document.getElementById('department_id').value || null,
                assigned_sites: Array.from(document.querySelectorAll('.site-checkbox:checked'))
                    .map(cb => cb.value)
            };

            if (data.id) data.id = parseInt(data.id);

            const result = await apiCall('api/manage_users.php', 'POST', data);
            if (result && result.success) {
                closeModal('user-modal');
                fetchUsers();
            }
        });

        // --- CARGA DE EMPRESAS Y SUCURSALES ---
        async function loadCompanies(selectedId = null) {
            const companySelect = document.getElementById('company_id');
            if (!companySelect) return;
            companySelect.innerHTML = '<option value="">Cargando empresas...</option>';
            try {
                const result = await apiCall('api/get_companies.php');
                if (result.success) {
                    companySelect.innerHTML = '<option value="">Seleccionar empresa</option>';
                    result.data.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.name;
                        if (selectedId && company.id == selectedId) option.selected = true;
                        companySelect.appendChild(option);
                    });
                } else {
                    companySelect.innerHTML = '<option value="">Error al cargar</option>';
                }
            } catch (error) {
                companySelect.innerHTML = '<option value="">Error</option>';
            }
        }

        async function loadDepartments(selectedId = null) {
            const select = document.getElementById('department_id');
            if (!select) return;
            select.innerHTML = '<option value="">Cargando...</option>';
            try {
                const result = await apiCall('api/get_departments.php');
                if (result.success) {
                    select.innerHTML = '<option value="">Seleccionar departamento</option>';
                    result.data.forEach(dep => {
                        const option = document.createElement('option');
                        option.value = dep.id;
                        option.textContent = dep.name;
                        if (selectedId && dep.id == selectedId) option.selected = true;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Error al cargar</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error</option>';
            }
        }

        document.getElementById('company_id').addEventListener('change', async function() {
            const companyId = this.value;
            const branchSelect = document.getElementById('branch_id');
            if (companyId) {
                branchSelect.innerHTML = '<option value="">Cargando...</option>';
                await loadBranches(companyId);
            } else {
                branchSelect.innerHTML = '<option value="">Seleccionar sucursal</option>';
            }
        });

        async function loadBranches(companyId, selectedId = null, departmentId = null) {
            const branchSelect = document.getElementById('branch_id');
            const departmentSelect = document.getElementById('department_id');

            try {
                const result = await apiCall(`api/get_branches.php?company_id=${companyId}`);
                if (result.success) {
                    branchSelect.innerHTML = '<option value="">Seleccionar sucursal</option>';
                    departmentSelect.innerHTML = '<option value="">Seleccionar departamento</option>'; // Reset departments
                    result.data.forEach(branch => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = `${branch.name} (${branch.province})`;
                        if (selectedId && branch.id == selectedId) {
                            option.selected = true;
                            loadDepartmentsByBranch(selectedId, departmentId); // Load departments for the selected branch
                        }
                        branchSelect.appendChild(option);
                    });
                }
            } catch (error) {
                branchSelect.innerHTML = '<option value="">Error</option>';
                departmentSelect.innerHTML = '<option value="">Error</option>';
            }
        }

        document.getElementById('branch_id').addEventListener('change', function() {
            const branchId = this.value;
            loadDepartmentsByBranch(branchId);
        });

        async function loadDepartmentsByBranch(branchId, selectedId = null) {
            const departmentSelect = document.getElementById('department_id');
            if (!branchId) {
                departmentSelect.innerHTML = '<option value="">Seleccionar departamento</option>';
                return;
            }
            departmentSelect.innerHTML = '<option value="">Cargando...</option>';
            const result = await apiCall(`api/get_departments.php?branch_id=${branchId}`);
            if (result.success) {
                departmentSelect.innerHTML = '<option value="">Seleccionar departamento</option>';
                result.data.forEach(dep => {
                    const option = document.createElement('option');
                    option.value = dep.id;
                    option.textContent = dep.name;
                    if (selectedId && dep.id == selectedId) option.selected = true;
                    departmentSelect.appendChild(option);
                });
            }
        }

        async function loadSitesForUser(userId = null) {
            const sitesContainer = document.getElementById('sites-container');
            try {
                const result = await apiCall('api/manage_sites.php?action=list');
                const assignedResult = userId ? await apiCall(`api/manage_users.php?action=get_assigned_sites&id=${userId}`) : { success: false };
                const assignedSiteIds = assignedResult.success ? assignedResult.data.map(s => s.id) : [];

                if (result.success) {
                    let html = '';
                    result.data.forEach(site => {
                        const checked = assignedSiteIds.includes(site.id) ? 'checked' : '';
                        html += `<div class="form-check">
                            <input type="checkbox" class="site-checkbox" id="site-${site.id}" value="${site.id}" ${checked}>
                            <label for="site-${site.id}">${escapeHtml(site.name)}</label>
                        </div>`;
                    });
                    sitesContainer.innerHTML = html;
                } else {
                    sitesContainer.innerHTML = '<p class="error-message">Error al cargar sitios.</p>';
                }
            } catch (error) {
                sitesContainer.innerHTML = '<p class="error-message">Error al cargar sitios.</p>';
            }
        }

        // --- EMPRESAS ---
        const companiesTableBody = document.querySelector('#companies-table-body');
        const companyFormEl = document.getElementById('company-form');

        async function fetchCompanies() {
            const result = await apiCall('api/get_companies.php');
            if (result && result.success) {
                renderCompaniesTable(result.data);
            }
        }

        function renderCompaniesTable(companies) {
            companiesTableBody.innerHTML = '';
            if (companies.length === 0) {
                companiesTableBody.innerHTML = '<tr><td colspan="3">No hay empresas para mostrar.</td></tr>';
                return;
            }
            companies.forEach(company => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(company.name)}</td>
                    <td>${safeDate(company.created_at)}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-company-btn" data-id="${company.id}">Editar</button>
                        <button class="btn btn-sm btn-danger delete-company-btn" data-id="${company.id}">Eliminar</button>
                    </td>
                `;
                companiesTableBody.appendChild(tr);
            });
        }

        document.getElementById('add-company-btn').addEventListener('click', () => {
            companyFormEl.reset();
            document.getElementById('company-id').value = '';
            document.getElementById('company-action').value = 'add';
            document.getElementById('company-modal-title').textContent = 'Agregar Empresa';
            openModal('company-modal');
        });

        companiesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('delete-company-btn')) {
                e.preventDefault();
                if (!confirm('¿Eliminar esta empresa?')) return;
                const id = e.target.dataset.id;
                const result = await apiCall('api/manage_companies.php', 'POST', { action: 'delete', id });
                if (result && result.success) {
                    fetchCompanies();
                    loadCompanies();
                }
            }

            if (e.target.classList.contains('edit-company-btn')) {
                e.preventDefault();
                const id = e.target.dataset.id;
                const result = await apiCall(`api/get_company.php?id=${id}`);
                if (result && result.success) {
                    document.getElementById('company-modal-title').textContent = 'Editar Empresa';
                    document.getElementById('company-action').value = 'edit';
                    document.getElementById('company-id').value = result.data.id;
                    document.getElementById('company-name').value = result.data.name;
                    openModal('company-modal');
                }
            }
        });

        companyFormEl.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(companyFormEl);
            const data = Object.fromEntries(formData.entries());
            data.id = parseInt(data.id) || null;
            const result = await apiCall('api/manage_companies.php', 'POST', data);
            if (result && result.success) {
                closeModal('company-modal');
                fetchCompanies();
                loadCompanies();
            }
        });

        // --- SUCURSALES ---
        const branchesTableBody = document.querySelector('#branches-table-body');
        const branchFormEl = document.getElementById('branch-form');

        async function fetchBranches() {
            const result = await apiCall('api/get_branches.php');
            if (result && result.success) {
                renderBranchesTable(result.data);
            }
        }

        function renderBranchesTable(branches) {
            branchesTableBody.innerHTML = '';
            if (branches.length === 0) {
                branchesTableBody.innerHTML = '<tr><td colspan="5">No hay sucursales para mostrar.</td></tr>';
                return;
            }
            branches.forEach(branch => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(branch.name)}</td>
                    <td>${escapeHtml(branch.company_name)}</td>
                    <td>${escapeHtml(branch.country_name || 'N/A')}</td>
                    <td>${escapeHtml(branch.province)}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-branch-btn" data-id="${branch.id}">Editar</button>
                        <button class="btn btn-sm btn-danger delete-branch-btn" data-id="${branch.id}">Eliminar</button>
                    </td>
                `;
                branchesTableBody.appendChild(tr);
            });
        }

        document.getElementById('add-branch-btn').addEventListener('click', () => {
            branchFormEl.reset();
            document.getElementById('branch-id').value = '';
            document.getElementById('branch-action').value = 'add';
            document.getElementById('branch-modal-title').textContent = 'Agregar Sucursal';
            loadBranchCompanies();
            loadCountries();
            openModal('branch-modal');
        });

        branchesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('delete-branch-btn')) {
                e.preventDefault();
                if (!confirm('¿Eliminar esta sucursal?')) return;
                const id = e.target.dataset.id;
                const result = await apiCall('api/manage_branches.php', 'POST', { action: 'delete', id });
                if (result && result.success) {
                    fetchBranches();
                }
            }

            if (e.target.classList.contains('edit-branch-btn')) {
                e.preventDefault();
                const id = e.target.dataset.id;
                const result = await apiCall(`api/get_branch.php?id=${id}`);
                if (result && result.success) {
                    document.getElementById('branch-modal-title').textContent = 'Editar Sucursal';
                    document.getElementById('branch-action').value = 'edit';
                    document.getElementById('branch-id').value = result.data.id;
                    document.getElementById('branch-name').value = result.data.name;
                    document.getElementById('branch-company-id').value = result.data.company_id;
                    loadBranchCompanies(result.data.company_id);
                    loadCountries(result.data.country_id, result.data.province);
                    openModal('branch-modal');
                }
            }
        });

        branchFormEl.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(branchFormEl);
            const data = Object.fromEntries(formData.entries());
            data.id = parseInt(data.id) || null;
            const result = await apiCall('api/manage_branches.php', 'POST', data);
            if (result && result.success) {
                closeModal('branch-modal');
                fetchBranches();
            }
        });

        async function loadBranchCompanies(selectedId = null) {
            const select = document.getElementById('branch-company-id');
            select.innerHTML = '<option value="">Cargando...</option>';
            try {
                const result = await apiCall('api/get_companies.php');
                if (result.success) {
                    select.innerHTML = '<option value="">Seleccionar empresa</option>';
                    result.data.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.name;
                        if (selectedId && company.id == selectedId) option.selected = true;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Error</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error</option>';
            }
        }

        async function loadCountries(selectedCountryId = null, selectedProvinceName = null) {
            const countrySelect = document.getElementById('branch-country-id');
            const provinceSelect = document.getElementById('branch-province');
            
            try {
                const result = await apiCall('api/get_countries.php');
                if (result.success) {
                    countrySelect.innerHTML = '<option value="">Seleccionar país</option>';
                    result.data.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.id;
                        option.textContent = country.name;
                        if (selectedCountryId && country.id == selectedCountryId) option.selected = true;
                        countrySelect.appendChild(option);
                    });

                    if (selectedCountryId) {
                        await loadProvinces(selectedCountryId, selectedProvinceName);
                    }
                }
            } catch (error) {
                countrySelect.innerHTML = '<option value="">Error</option>';
            }
        }

        async function loadProvinces(countryId, selectedProvinceName = null) {
            const provinceSelect = document.getElementById('branch-province');
            provinceSelect.innerHTML = '<option value="">Cargando...</option>';
            try {
                const result = await apiCall(`api/get_provinces.php?country_id=${countryId}`);
                if (result.success) {
                    provinceSelect.innerHTML = '<option value="">Seleccionar provincia</option>';
                    result.data.forEach(province => {
                        const option = document.createElement('option');
                        option.value = province.name; // Storing name as value
                        option.textContent = province.name;
                        if (selectedProvinceName && province.name === selectedProvinceName) option.selected = true;
                        provinceSelect.appendChild(option);
                    });
                }
            } catch (error) {
                provinceSelect.innerHTML = '<option value="">Error</option>';
            }
        }

        // --- DEPARTAMENTOS ---
        const departmentsTableBody = document.querySelector('#departments-table-body');
        const departmentForm = document.getElementById('department-form');

        async function fetchDepartments() {
            if (!departmentsTableBody) return;
            const result = await apiCall('api/get_departments.php');
            if (result && result.success) {
                renderDepartmentsTable(result.data);
            }
        }

        function renderDepartmentsTable(departments) {
            if (!departmentsTableBody) return;
            departmentsTableBody.innerHTML = '';
            if (departments.length === 0) {
                departmentsTableBody.innerHTML = '<tr><td colspan="4">No hay departamentos para mostrar.</td></tr>';
                return;
            }
            departments.forEach(dept => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(dept.name)}</td>
                    <td>${escapeHtml(dept.company_name || 'N/A')}</td>
                    <td>${escapeHtml(dept.branch_name || 'N/A')}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-department-btn" data-id="${dept.id}">Editar</button>
                        <button class="btn btn-sm btn-danger delete-department-btn" data-id="${dept.id}">Eliminar</button>
                    </td>
                `;
                departmentsTableBody.appendChild(tr);
            });
        }

        document.getElementById('add-department-btn')?.addEventListener('click', () => {
            departmentForm.reset();
            document.getElementById('department-id').value = '';
            document.getElementById('department-action').value = 'add';
            document.getElementById('department-modal-title').textContent = 'Agregar Departamento';
            loadDepartmentCompanies();
            document.getElementById('department-branch-id').innerHTML = '<option value="">Seleccionar sucursal</option>';
            openModal('department-modal');
        });

        departmentsTableBody?.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('.edit-department-btn');
            if (editBtn) {
                const id = editBtn.dataset.id;
                const result = await apiCall(`api/manage_departments.php?id=${id}`);
                if (result && result.success) {
                    const dept = result.data;
                    departmentForm.reset();
                    document.getElementById('department-id').value = dept.id;
                    document.getElementById('department-action').value = 'edit';
                    document.getElementById('department-modal-title').textContent = 'Editar Departamento';
                    document.getElementById('department-name').value = dept.name;
                    await loadDepartmentCompanies(dept.company_id);
                    await loadDepartmentBranches(dept.company_id, dept.branch_id);
                    openModal('department-modal');
                }
            }

            const deleteBtn = e.target.closest('.delete-department-btn');
            if (deleteBtn) {
                if (!confirm('¿Eliminar este departamento?')) return;
                const id = deleteBtn.dataset.id;
                const result = await apiCall('api/manage_departments.php', 'POST', { action: 'delete', id: id });
                if (result && result.success) {
                    fetchDepartments();
                }
            }
        });

        departmentForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(departmentForm);
            const data = Object.fromEntries(formData.entries());
            data.id = parseInt(data.id) || null;
            const result = await apiCall('api/manage_departments.php', 'POST', data);
            if (result && result.success) {
                closeModal('department-modal');
                fetchDepartments();
                loadDepartments(); // Recargar lista de departamentos en modal de usuario
            }
        });

        async function loadDepartmentCompanies(selectedId = null) {
            const select = document.getElementById('department-company-id');
            if (!select) return;
            await loadCompaniesGeneric(select, selectedId);
        }

        async function loadDepartmentBranches(companyId, selectedId = null) {
            const select = document.getElementById('department-branch-id');
            if (!select) return;
            await loadBranchesGeneric(select, companyId, selectedId);
        }

        document.getElementById('department-company-id')?.addEventListener('change', function() {
            const companyId = this.value;
            const branchSelect = document.getElementById('department-branch-id');
            if (companyId) {
                branchSelect.innerHTML = '<option value="">Cargando...</option>';
                loadDepartmentBranches(companyId);
            } else {
                branchSelect.innerHTML = '<option value="">Seleccionar sucursal</option>';
            }
        });

        async function loadCompaniesGeneric(selectElement, selectedId = null) {
            if (!selectElement) return;
            selectElement.innerHTML = '<option value="">Cargando...</option>';
            const result = await apiCall('api/get_companies.php');
            if (result && result.success) {
                selectElement.innerHTML = '<option value="">Seleccionar empresa</option>';
                result.data.forEach(c => {
                    const option = document.createElement('option');
                    option.value = c.id;
                    option.textContent = c.name;
                    if (selectedId && c.id == selectedId) option.selected = true;
                    selectElement.appendChild(option);
                });
            } else {
                selectElement.innerHTML = '<option value="">Error</option>';
            }
        }

        async function loadBranchesGeneric(selectElement, companyId, selectedId = null) {
            if (!selectElement) return;
            if (!companyId) {
                selectElement.innerHTML = '<option value="">Seleccionar sucursal</option>';
                return;
            }
            selectElement.innerHTML = '<option value="">Cargando...</option>';
            const result = await apiCall(`api/get_branches.php?company_id=${companyId}`);
            if (result && result.success) {
                selectElement.innerHTML = '<option value="">Seleccionar sucursal</option>';
                result.data.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.id;
                    option.textContent = b.name;
                    if (selectedId && b.id == selectedId) option.selected = true;
                    selectElement.appendChild(option);
                });
            } else {
                selectElement.innerHTML = '<option value="">Error</option>';
            }
        }

        document.getElementById('branch-country-id').addEventListener('change', function() {
            const countryId = this.value;
            if (countryId) {
                loadProvinces(countryId);
            } else {
                document.getElementById('branch-province').innerHTML = '<option value="">Seleccionar provincia</option>';
            }
        });

        // --- SITIOS ---
        const sitesTableBody = document.querySelector('#sites-table-body');
        const siteForm = document.getElementById('site-form');

        async function fetchSites() {
            const result = await apiCall('api/manage_sites.php?action=list');
            if (result && result.success) {
                renderSitesTable(result.data);
            }
        }

        function renderSitesTable(sites) {
            sitesTableBody.innerHTML = '';
            if (sites.length === 0) {
                sitesTableBody.innerHTML = '<tr><td colspan="5">No hay sitios para mostrar.</td></tr>';
                return;
            }
            sites.forEach(site => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(site.name)}</td>
                    <td><a href="${site.url}" target="_blank">${escapeHtml(site.url)}</a></td>
                    <td>${escapeHtml(site.username)}</td>
                    <td>${site.password_needs_update ? '✅ Sí' : '❌ No'}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-secondary edit-site-btn" data-site-id="${site.id}">Editar</button>
                        <button class="btn btn-sm btn-danger delete-site-btn" data-site-id="${site.id}">Eliminar</button>
                    </td>
                `;
                sitesTableBody.appendChild(tr);
            });
        }

        document.getElementById('add-site-btn').addEventListener('click', () => {
            siteForm.reset();
            document.getElementById('site-id').value = '';
            document.getElementById('site-action').value = 'add';
            document.getElementById('site-modal-title').textContent = 'Agregar Sitio';
            // Mostrar selector de visibilidad solo para superadmin
            if (CURRENT_USER_ROLE === 'superadmin') {
                document.getElementById('site-visibility-group').style.display = 'block';
            }
            openModal('site-modal');
        });

        sitesTableBody.addEventListener('click', async (e) => {
            // ✅ Detectar clic en botón de editar (aunque sea en el texto)
            const editBtn = e.target.closest('.edit-site-btn');
            if (editBtn) {
                e.preventDefault();
                const id = editBtn.dataset.siteId;
                const result = await apiCall(`api/manage_sites.php?action=get&id=${id}`);
                if (result && result.success) {
                    const site = result.data;
                    siteForm.reset();
                    document.getElementById('site-modal-title').textContent = 'Editar Sitio';
                    document.getElementById('site-action').value = 'edit';
                    document.getElementById('site-id').value = site.id;
                    document.getElementById('site-name').value = site.name;
                    document.getElementById('site-url').value = site.url;
                    document.getElementById('site-username').value = site.username;
                    document.getElementById('site-notes').value = site.notes;
                    openModal('site-modal');
                }
            }

            // ✅ Detectar clic en botón de eliminar
            const deleteBtn = e.target.closest('.delete-site-btn');
            if (deleteBtn) {
                e.preventDefault();
                if (!confirm('¿Eliminar este sitio? Esta acción no se puede deshacer.')) return;
                const id = deleteBtn.dataset.siteId;
                const result = await apiCall('api/manage_sites.php', 'POST', { action: 'delete', id: id });
                if (result && result.success) {
                    fetchSites();
                }
            }
        });

        siteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(siteForm);
            const data = Object.fromEntries(formData.entries());
            data.id = parseInt(data.id) || null;
            const result = await apiCall('api/manage_sites.php', 'POST', data);
            if (result && result.success) {
                closeModal('site-modal');
                fetchSites();
            }
        });

        // --- MENSAJES ---
        const messagesTableBody = document.getElementById('messages-table-body');
        document.querySelector('[data-tab="messages-tab"]').addEventListener('click', () => {
            fetchUserMessages();
        });

        async function fetchUserMessages() {
            try {
                const result = await apiCall('api/get_user_messages.php');
                if (result.success) {
                    messagesTableBody.innerHTML = '';
                    result.data.forEach(msg => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${escapeHtml(msg.username)}</td>
                            <td>${escapeHtml(msg.message)}</td>
                            <td>${safeDate(msg.created_at)}</td>
                            <td class="actions">
                                <button class="btn btn-sm btn-primary reply-btn" data-user-id="${msg.sender_id}">Responder</button>
                                <button class="btn btn-sm btn-danger delete-msg-btn" data-id="${msg.id}">Eliminar</button>
                            </td>
                        `;
                        messagesTableBody.appendChild(tr);
                    });
                }
            } catch (error) {
                messagesTableBody.innerHTML = '<tr><td colspan="4">Error al cargar mensajes.</td></tr>';
            }
        }

        messagesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('reply-btn')) {
                e.preventDefault();
                const userId = e.target.dataset.userId;
                const reply = prompt('Escriba su respuesta:');
                if (reply && reply.trim()) {
                    const result = await apiCall('api/send_message.php', 'POST', {
                        receiver_id: userId,
                        message: reply.trim()
                    });
                    if (result && result.success) {
                        alert('Mensaje enviado.');
                        fetchUserMessages();
                    }
                }
            }

            if (e.target.classList.contains('delete-msg-btn')) {
                e.preventDefault();
                if (!confirm('¿Eliminar este mensaje?')) return;
                const id = e.target.dataset.id;
                const result = await apiCall('api/delete_message.php', 'POST', { message_id: id });
                if (result && result.success) {
                    fetchUserMessages();
                }
            }
        });

        // --- INICIALIZACIÓN ---
        fetchUsers();
        fetchSites();
        fetchCompanies();
        fetchBranches();
        fetchDepartments();
        // --- AUDITORÍA ---
    const auditTableBody = document.getElementById('audit-table-body');

    async function fetchAuditLogs() {
        try {
            const result = await apiCall('api/get_audit_logs.php');
            if (result.success) {
                renderAuditTable(result.data);
            }
        } catch (error) {
            auditTableBody.innerHTML = '<tr><td colspan="6">Error al cargar la bitácora.</td></tr>';
        }
    }

    function renderAuditTable(logs) {
        auditTableBody.innerHTML = '';
        if (logs.length === 0) {
            auditTableBody.innerHTML = '<tr><td colspan="6">No hay registros en la bitácora.</td></tr>';
            return;
        }
        logs.forEach(log => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${log.id}</td>
                <td>${escapeHtml(log.username || 'Sistema')}</td>
                <td>${escapeHtml(log.action)}</td>
                <td>${escapeHtml(log.service_name || '-')}</td>
                <td><code>${escapeHtml(log.ip_address || 'N/A')}</code></td>
                <td>${safeDate(log.timestamp)}</td>
            `;
            auditTableBody.appendChild(tr);
        });
    }

    // Cargar auditoría cuando se haga clic en la pestaña
    document.querySelector('[data-tab="audit-tab"]').addEventListener('click', () => {
        fetchAuditLogs();
});

        // --- INICIALIZAR NOTIFICACIONES ---
        document.querySelector('[data-tab="notifications-tab"]').addEventListener('click', () => {
            // El script externo notifications_panel.js se encarga
        });
    });
    </script>
    <script src="assets/js/notifications_panel.js"></script>
</body>
</html>