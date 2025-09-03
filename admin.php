<?php
require_once 'bootstrap.php';
require_auth('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
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
            <button class="tab-link active" data-tab="users-tab">Usuarios</button>
            <button class="tab-link" data-tab="companies-tab">🏢 Empresas</button>
            <button class="tab-link" data-tab="branches-tab">📍 Sucursales</button>
            <button class="tab-link" data-tab="sites-tab">Sitios</button>
            <button class="tab-link" data-tab="messages-tab">💬 Mensajes</button>
            <button class="tab-link" data-tab="notifications-tab">Notificaciones</button>
        </nav>

        <!-- Pestaña de Usuarios -->
        <div id="users-tab" class="tab-content active">
            <button class="btn btn-primary" id="add-user-btn">Agregar Nuevo Usuario</button>
            <div class="table-wrapper">
                <table id="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Empresa</th>
                            <th>Sucursal</th>
                            <th>Provincia</th>
                            <th>Creado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Empresas -->
        <div id="companies-tab" class="tab-content">
            <button class="btn btn-primary" id="add-company-btn">Agregar Empresa</button>
            <div class="table-wrapper">
                <table id="companies-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Creada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Sucursales -->
        <div id="branches-tab" class="tab-content">
            <button class="btn btn-primary" id="add-branch-btn">Agregar Sucursal</button>
            <div class="table-wrapper">
                <table id="branches-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Provincia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña de Sitios -->
        <div id="sites-tab" class="tab-content">
            <button class="btn btn-primary" id="add-site-btn">Agregar Nuevo Sitio</button>
            <div class="table-wrapper">
                <table id="sites-table">
                    <thead>
                        <tr>
                            <th>Nombre del Sitio</th>
                            <th>URL</th>
                            <th>Usuario</th>
                            <th>Notas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
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
                    <small>Dejar en blanco para no cambiar la contraseña existente.</small>
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
                    <label>Sitios asignados</label>
                    <div id="sites-list" style="max-height: 200px; overflow-y: auto;">
                        <p>Cargando sitios...</p>
                    </div>
                    <small>Seleccione los sitios que este usuario puede acceder.</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-btn" data-modal-id="user-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
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
                    <button type="button" class="btn btn-secondary close-btn" data-modal-id="company-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
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
                    <label for="branch-company-id">Empresa</label>
                    <select id="branch-company-id" name="company_id" required>
                        <option value="">Seleccionar empresa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch-name">Nombre de la Sucursal</label>
                    <input type="text" id="branch-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="branch-province">Provincia</label>
                    <select id="branch-province" name="province" required>
                        <option value="">Seleccionar provincia</option>
                        <!-- Argentina -->
                        <optgroup label="Argentina">
                            <option value="Buenos Aires">Buenos Aires</option>
                            <option value="Córdoba">Córdoba</option>
                            <option value="Santa Fe">Santa Fe</option>
                            <option value="Mendoza">Mendoza</option>
                            <option value="Tucumán">Tucumán</option>
                            <option value="Salta">Salta</option>
                            <option value="Entre Ríos">Entre Ríos</option>
                            <option value="Misiones">Misiones</option>
                            <option value="Chaco">Chaco</option>
                            <option value="Formosa">Formosa</option>
                            <option value="Corrientes">Corrientes</option>
                            <option value="San Juan">San Juan</option>
                            <option value="San Luis">San Luis</option>
                            <option value="La Rioja">La Rioja</option>
                            <option value="Catamarca">Catamarca</option>
                            <option value="Jujuy">Jujuy</option>
                            <option value="Río Negro">Río Negro</option>
                            <option value="Neuquén">Neuquén</option>
                            <option value="Chubut">Chubut</option>
                            <option value="Santa Cruz">Santa Cruz</option>
                            <option value="Tierra del Fuego">Tierra del Fuego</option>
                        </optgroup>
                        <!-- Uruguay -->
                        <optgroup label="Uruguay">
                            <option value="Montevideo">Montevideo</option>
                            <option value="Canelones">Canelones</option>
                            <option value="Maldonado">Maldonado</option>
                            <option value="Lavalleja">Lavalleja</option>
                            <option value="Rocha">Rocha</option>
                            <option value="Treinta y Tres">Treinta y Tres</option>
                            <option value="Cerro Largo">Cerro Largo</option>
                            <option value="Rivera">Rivera</option>
                            <option value="Artigas">Artigas</option>
                            <option value="Salto">Salto</option>
                            <option value="Paysandú">Paysandú</option>
                            <option value="Río Negro">Río Negro</option>
                            <option value="Soriano">Soriano</option>
                            <option value="Colonia">Colonia</option>
                            <option value="San José">San José</option>
                            <option value="Flores">Flores</option>
                            <option value="Florida">Florida</option>
                            <option value="Durazno">Durazno</option>
                            <option value="Tacuarembó">Tacuarembó</option>
                            <option value="Paso de los Toros">Paso de los Toros</option>
                        </optgroup>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-btn" data-modal-id="branch-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
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
                    <input type="url" id="site-url" name="url" placeholder="https://ejemplo.com" required>
                </div>
                <div class="form-group">
                    <label for="site-username">Usuario</label>
                    <input type="text" id="site-username" name="username">
                </div>
                <div class="form-group">
                    <label for="site-password">Nueva Contraseña (opcional)</label>
                    <div class="password-wrapper">
                        <input type="password" id="site-password" name="password" autocomplete="new-password">
                        <button type="button" class="toggle-password">Mostrar</button>
                    </div>
                    <small>Deje en blanco para mantener la contraseña actual.</small>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="site-password-needs-update" name="password_needs_update" value="1">
                        Marcar como "requiere actualización"
                    </label>
                    <small>El usuario verá un aviso en su panel.</small>
                </div>
                <div class="form-group">
                    <label for="site-notes">Notas</label>
                    <textarea id="site-notes" name="notes"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-btn" data-modal-id="site-modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const CSRF_TOKEN = '<?php echo $csrf_token; ?>';

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

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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
                const response = await fetch(url, options);
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `Error ${response.status}`);
                }
                return response.json();
            } catch (error) {
                console.error('Error en la llamada API:', error);
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
                    day: '2-digit'
                });
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe
                 .toString()
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "<")
                 .replace(/>/g, ">")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        // --- GESTIÓN DE USUARIOS ---
        const usersTableBody = document.querySelector('#users-table tbody');
        const userModal = document.getElementById('user-modal');
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
                usersTableBody.innerHTML = '<tr><td colspan="8">No hay usuarios para mostrar.</td></tr>';
                return;
            }
            users.forEach(user => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.role)}</td>
                    <td>${escapeHtml(user.company_name || '-')}</td>
                    <td>${escapeHtml(user.branch_name || '-')}</td>
                    <td>${escapeHtml(user.province || '-')}</td>
                    <td>${safeDate(user.created_at)}</td>
                    <td>
                        <span class="status-toggle ${user.is_active ? 'active' : 'inactive'}" data-user-id="${user.id}" data-active="${user.is_active}">
                            ${user.is_active ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="#" class="btn btn-secondary btn-sm edit-user-btn" data-user-id="${user.id}">Editar</a>
                        <a href="#" class="btn btn-danger btn-sm delete-user-btn" data-user-id="${user.id}">Eliminar</a>
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
            loadSitesForUser();
            openModal('user-modal');
        });

        usersTableBody.addEventListener('click', async (e) => {
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
                    document.getElementById('is_active').checked = user.is_active == 1;
                    loadCompanies();
                    setTimeout(() => {
                        if (user.company_id) {
                            document.getElementById('company_id').value = user.company_id;
                            loadBranches(user.company_id, user.branch_id);
                        }
                    }, 300);
                    loadSitesForUser(user.id);
                    openModal('user-modal');
                }
            }
        });

        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(userForm);
            const data = Object.fromEntries(formData.entries());
            data.id = parseInt(data.id) || null;
            data.is_active = document.getElementById('is_active').checked;

            const assignedSites = Array.from(document.querySelectorAll('.site-checkbox:checked'))
                .map(cb => cb.value);
            data.assigned_sites = assignedSites;

            const result = await apiCall('api/manage_users.php', 'POST', data);
            if (result && result.success) {
                closeModal('user-modal');
                fetchUsers();
            }
        });

        // --- CARGA DE EMPRESAS Y SUCURSALES ---
        async function loadCompanies() {
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
                        companySelect.appendChild(option);
                    });
                } else {
                    companySelect.innerHTML = '<option value="">Error al cargar</option>';
                }
            } catch (error) {
                companySelect.innerHTML = '<option value="">Error</option>';
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

        async function loadBranches(companyId, selectedId = null) {
            const branchSelect = document.getElementById('branch_id');
            try {
                const result = await apiCall(`api/get_branches.php?company_id=${companyId}`);
                if (result.success) {
                    branchSelect.innerHTML = '<option value="">Seleccionar sucursal</option>';
                    result.data.forEach(branch => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = `${branch.name} (${branch.province})`;
                        if (branch.id == selectedId) option.selected = true;
                        branchSelect.appendChild(option);
                    });
                } else {
                    branchSelect.innerHTML = '<option value="">Error</option>';
                }
            } catch (error) {
                branchSelect.innerHTML = '<option value="">Error</option>';
            }
        }

        // --- GESTIÓN DE SITIOS ---
        const sitesTableBody = document.querySelector('#sites-table tbody');
        const siteModal = document.getElementById('site-modal');
        const siteForm = document.getElementById('site-form');

        async function fetchSites() {
            const result = await apiCall('api/manage_sites.php?action=list');
            if (result && result.success) {
                renderSitesTable(result.data);
            }
        }

        function renderSitesTable(sites) {
            sitesTableBody.innerHTML = '';
            sites.forEach(site => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(site.name)}</td>
                    <td><a href="${escapeHtml(site.url.trim())}" target="_blank">${escapeHtml(site.url.trim())}</a></td>
                    <td>${escapeHtml(site.username || '-')}</td>
                    <td>${escapeHtml(site.notes || '-')}</td>
                    <td class="actions">
                        <a href="#" class="btn btn-secondary btn-sm edit-site-btn" data-site-id="${site.id}">Editar</a>
                        <a href="#" class="btn btn-danger btn-sm delete-site-btn" data-site-id="${site.id}">Eliminar</a>
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
            openModal('site-modal');
        });

        sitesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('edit-site-btn')) {
                e.preventDefault();
                const id = e.target.dataset.siteId;
                const result = await apiCall(`api/manage_sites.php?action=get&id=${id}`);
                if (result && result.success) {
                    const site = result.data;
                    siteForm.reset();
                    document.getElementById('site-modal-title').textContent = 'Editar Sitio';
                    document.getElementById('site-action').value = 'edit';
                    document.getElementById('site-id').value = site.id;
                    document.getElementById('site-name').value = site.name;
                    document.getElementById('site-url').value = site.url.trim();
                    document.getElementById('site-username').value = site.username;
                    document.getElementById('site-notes').value = site.notes;
                    document.getElementById('site-password-needs-update').checked = site.password_needs_update == 1;
                    openModal('site-modal');
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

        // --- LÓGICA ADICIONAL ---
        document.querySelector('.toggle-password')?.addEventListener('click', function (e) {
            const passwordInput = document.getElementById('site-password');
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            e.target.textContent = isPassword ? 'Ocultar' : 'Mostrar';
        });

        // --- CARGA DE SITIOS PARA USUARIOS ---
        async function loadSitesForUser(userId = null) {
            const sitesContainer = document.getElementById('sites-list');
            if (!sitesContainer) return;
            sitesContainer.innerHTML = '<p>Cargando sitios...</p>';
            try {
                const result = await apiCall('api/manage_sites.php?action=list');
                if (!result || !result.success) throw new Error('Error al cargar sitios');
                const allSites = result.data;
                const selectedSites = userId ? await fetchAssignedSites(userId) : [];
                let html = '';
                allSites.forEach(site => {
                    const checked = selectedSites.includes(site.id);
                    html += `
                        <div class="form-check">
                            <input type="checkbox" class="site-checkbox" name="assigned_sites[]" value="${site.id}" ${checked ? 'checked' : ''}>
                            <label>${escapeHtml(site.name)}</label>
                        </div>
                    `;
                });
                sitesContainer.innerHTML = html;
            } catch (error) {
                sitesContainer.innerHTML = '<p class="error-message">Error al cargar sitios.</p>';
            }
        }

        async function fetchAssignedSites(userId) {
            try {
                const result = await apiCall(`api/manage_users.php?action=get_assigned_sites&id=${userId}`);
                if (result && result.success) {
                    return result.data.map(s => s.id);
                }
                return [];
            } catch (error) {
                return [];
            }
        }

        // --- EMPRESAS ---
        const companiesTableBody = document.querySelector('#companies-table tbody');
        const companyModalEl = document.getElementById('company-modal');
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
                        <a href="#" class="btn btn-secondary btn-sm edit-company-btn" data-id="${company.id}">Editar</a>
                        <a href="#" class="btn btn-danger btn-sm delete-company-btn" data-id="${company.id}">Eliminar</a>
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
        const branchesTableBody = document.querySelector('#branches-table tbody');
        const branchModalEl = document.getElementById('branch-modal');
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
                branchesTableBody.innerHTML = '<tr><td colspan="4">No hay sucursales para mostrar.</td></tr>';
                return;
            }
            branches.forEach(branch => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(branch.name)}</td>
                    <td>${escapeHtml(branch.company_name)}</td>
                    <td>${escapeHtml(branch.province)}</td>
                    <td class="actions">
                        <a href="#" class="btn btn-secondary btn-sm edit-branch-btn" data-id="${branch.id}">Editar</a>
                        <a href="#" class="btn btn-danger btn-sm delete-branch-btn" data-id="${branch.id}">Eliminar</a>
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
            openModal('branch-modal');
        });

        branchesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('edit-branch-btn')) {
                e.preventDefault();
                const id = e.target.dataset.id;
                const result = await apiCall(`api/get_branch.php?id=${id}`);
                if (result && result.success) {
                    document.getElementById('branch-modal-title').textContent = 'Editar Sucursal';
                    document.getElementById('branch-action').value = 'edit';
                    document.getElementById('branch-id').value = result.data.id;
                    document.getElementById('branch-company-id').value = result.data.company_id;
                    document.getElementById('branch-name').value = result.data.name;
                    document.getElementById('branch-province').value = result.data.province;
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

        async function loadBranchCompanies() {
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
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">Error</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error</option>';
            }
        }

        // --- MENSAJES ---
        const messagesTableBody = document.getElementById('messages-table-body');

        document.querySelector('[data-tab="messages-tab"]').addEventListener('click', () => {
            fetchUserMessages();
        });

        async function fetchUserMessages() {
            try {
                const result = await apiCall('api/get_user_messages.php');
                if (result.success) {
                    renderMessagesTable(result.data);
                }
            } catch (error) {
                messagesTableBody.innerHTML = '<tr><td colspan="4">Error de conexión.</td></tr>';
            }
        }

        function renderMessagesTable(messages) {
            if (messages.length === 0) {
                messagesTableBody.innerHTML = '<tr><td colspan="4">No hay mensajes.</td></tr>';
                return;
            }
            messagesTableBody.innerHTML = messages.map(msg => `
                <tr>
                    <td>${escapeHtml(msg.username)}</td>
                    <td title="${escapeHtml(msg.message)}">${escapeHtml(msg.message.substring(0, 60))}${msg.message.length > 60 ? '...' : ''}</td>
                    <td>${safeDate(msg.created_at)}</td>
                    <td class="actions">
                        <a href="#" class="btn btn-secondary btn-sm reply-btn" data-user-id="${msg.sender_id}" data-username="${escapeHtml(msg.username)}">Responder</a>
                        <a href="#" class="btn btn-danger btn-sm delete-msg-btn" data-id="${msg.id}">Eliminar</a>
                    </td>
                </tr>
            `).join('');
        }

        messagesTableBody.addEventListener('click', async (e) => {
            if (e.target.classList.contains('reply-btn')) {
                e.preventDefault();
                const userId = e.target.dataset.userId;
                const username = e.target.dataset.username;
                const reply = prompt(`Responder a ${username}:`);
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
    });
    </script>
    <script src="assets/js/notifications_panel.js"></script>
</body>
</html>