/**
 * /Psitios/assets/js/admin.js
 * 
 * Lógica principal para el panel de administración (admin.php).
 * Este script maneja la interacción del usuario con las pestañas, modales,
 * y realiza todas las operaciones CRUD (Crear, Leer, Actualizar, Eliminar)
 * a través de llamadas a la API.
 */

// --- 1. FUNCIONES GLOBALES DE UI ---
// Estas funciones se mantienen en el ámbito global para poder ser llamadas
// desde cualquier parte, aunque su uso directo en HTML (onclick) se ha minimizado.

/**
 * Abre un modal por su ID.
 * @param {string} modalId - El ID del elemento modal a mostrar.
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active'); // La clase 'active' controla la visibilidad.
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
    // --- 2. CONFIGURACIÓN GLOBAL Y CONSTANTES ---
    // Leemos datos importantes pasados desde PHP a través de atributos data-* en la etiqueta <body>.
    const adminData = document.body.dataset;
    const CURRENT_USER_ROLE = adminData.userRole;
    const CURRENT_USER_COMPANY_ID = adminData.companyId;
    const CURRENT_USER_BRANCH_ID = adminData.branchId;
    const CURRENT_USER_DEPARTMENT_ID = adminData.departmentId;

    // --- 3. FUNCIONES AUXILIARES ---

    /**
     * Formatea una cadena de fecha (ej. '2023-10-27 10:30:00') a un formato legible en español.
     * @param {string|null} dateString - La fecha a formatear.
     * @returns {string} - La fecha formateada o un texto indicativo si es inválida/nula.
     */
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

    // --- 4. GESTIÓN DE USUARIOS (PESTAÑA POR DEFECTO) ---

    // 4.1. Elementos del DOM
    const usersTableBody = document.querySelector('#users-table-body');
    const userForm = document.getElementById('user-form');

    async function fetchUsers() {
        const result = await window.api.get('api/manage_users.php?action=list');
        if (result && result.success) {
            renderUsersTable(result.data);
        }
    }

    // 4.2. Renderizado de la tabla de usuarios
    function renderUsersTable(users) {
        usersTableBody.innerHTML = '';
        if (users.length === 0) {
            usersTableBody.innerHTML = '<tr><td colspan="8">No hay usuarios para mostrar.</td></tr>';
            return;
        }
        users.forEach(user => {
            const tr = document.createElement('tr');
            // Muestra 'Global' para superadmin o 'N/A' si no hay datos, para mayor claridad.
            const companyName = user.company_name || (user.role === 'superadmin' ? 'Global' : 'N/A');
            const branchName = user.branch_name || (user.role === 'superadmin' ? 'Global' : 'N/A');
            const province = user.province || (user.role === 'superadmin' ? 'Global' : 'N/A');
            const departmentName = user.department_name || (user.role === 'superadmin' ? 'Global' : 'N/A');

            tr.innerHTML = `
                <td>${window.escapeHTML(user.username)}</td>
                <td>${window.escapeHTML(user.role)}</td>
                <td>${window.escapeHTML(companyName)}</td>
                <td>${window.escapeHTML(branchName)}</td>
                <td>${window.escapeHTML(province)}</td>
                <td>${window.escapeHTML(departmentName)}</td>
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

    // 4.3. Event Listeners (Agregar, Editar, Eliminar)
    document.getElementById('add-user-btn').addEventListener('click', async () => {
        userForm.reset();
        document.getElementById('user-id').value = '';
        document.getElementById('user-action').value = 'add';
        document.getElementById('user-modal-title').textContent = 'Agregar Usuario';
        document.getElementById('is_active').checked = true;

        // Resetea y habilita los desplegables para el formulario de 'agregar'.
        document.getElementById('company_id').disabled = false;
        document.getElementById('branch_id').disabled = false;
        document.getElementById('department_id').disabled = false;

        // Set placeholder text
        document.getElementById('branch_id').innerHTML = '<option value="">Seleccione una empresa primero</option>';
        document.getElementById('department_id').innerHTML = '<option value="">Seleccione una sucursal primero</option>';

        // Inicia la carga de datos no críticos en paralelo para mejorar la percepción de velocidad.
        const sitesPromise = loadSitesForUser();

        if (CURRENT_USER_ROLE === 'admin') {
            // Para un 'admin', carga su jerarquía específica y deshabilita los desplegables.
            await loadCompanies(CURRENT_USER_COMPANY_ID);
            await loadBranches(CURRENT_USER_COMPANY_ID, CURRENT_USER_BRANCH_ID, CURRENT_USER_DEPARTMENT_ID);
            document.getElementById('company_id').disabled = true;
            document.getElementById('branch_id').disabled = true;
            document.getElementById('department_id').disabled = true;
        } else {
            // Para un 'superadmin', carga todas las empresas para que pueda elegir.
            await loadCompanies();
            const adminGroup = document.getElementById('admin-assignment-group');
            if (CURRENT_USER_ROLE === 'superadmin' && document.getElementById('role').value === 'user') {
                adminGroup.classList.remove('hidden');
                loadAdmins(); // This can be async without await, not critical path
            } else {
                adminGroup.classList.add('hidden');
            }
        }

        await sitesPromise; // Espera a que los sitios se carguen antes de mostrar el modal.
        openModal('user-modal');
    });

    usersTableBody.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm('¿Eliminar este usuario?')) return;
            const id = deleteBtn.dataset.userId;
            const result = await window.api.post('api/manage_users.php', { action: 'delete', id });
            if (result && result.success) {
                fetchUsers();
            }
        }

        const editBtn = e.target.closest('.edit-user-btn');
        if (editBtn) {
            e.preventDefault();
            const id = editBtn.dataset.userId;
            const result = await window.api.get(`api/manage_users.php?action=get&id=${id}`);
            if (result && result.success && result.data) {
                const user = result.data;
                userForm.reset();
                document.getElementById('user-modal-title').textContent = 'Editar Usuario';
                document.getElementById('user-action').value = 'edit';
                document.getElementById('user-id').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('role').value = user.role;
                document.getElementById('is_active').checked = user.is_active;
                await loadCompanies(user.company_id);
                await loadBranches(user.company_id, user.branch_id, user.department_id);
                await loadSitesForUser(user.id);

                if (CURRENT_USER_ROLE === 'admin') {
                    document.getElementById('company_id').disabled = true;
                    document.getElementById('branch_id').disabled = true;
                    document.getElementById('department_id').disabled = true;
                }

                // Mostrar/ocultar campo de admin asignado
                const adminGroup = document.getElementById('admin-assignment-group');
                if (CURRENT_USER_ROLE === 'superadmin' && user.role === 'user') {
                    adminGroup.classList.remove('hidden');
                    loadAdmins(user.assigned_admin_id || null);
                } else {
                    adminGroup.classList.add('hidden');
                }

                openModal('user-modal');
            } else {
                // Manejo de error si la API devuelve success: false o data: undefined
                console.error('Error al obtener datos del usuario:', result);
                alert('Error al cargar los datos del usuario. Por favor, inténtelo nuevamente.');
            }
        }
    });

    // 4.4. Manejador del formulario de usuario (submit)
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
            assigned_admin_id: document.getElementById('assigned_admin_id').value || null,
            assigned_sites: Array.from(document.querySelectorAll('.site-checkbox:checked'))
                .map(cb => cb.value)
        };

        if (data.id) data.id = parseInt(data.id);

        const result = await window.api.post('api/manage_users.php', data);
        if (result && result.success) {
            closeModal('user-modal');
            fetchUsers();
        }
    });

    // 4.5. Funciones auxiliares para el formulario de usuario
    async function loadAdmins(selectedId = null) {
        const select = document.getElementById('assigned_admin_id');
        if (!select) return;

        try {
            const result = await window.api.get('api/get_admins.php');
            select.innerHTML = '<option value="">Ninguno (usuario sin admin)</option>';

            if (result.success && Array.isArray(result.data)) {
                result.data.forEach(admin => {
                    const option = document.createElement('option');
                    option.value = admin.id;
                    option.textContent = admin.username;
                    if (admin.id == selectedId) option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error al cargar admins:', error);
            select.innerHTML = '<option value="">Error al cargar admins</option>';
        }
    }

    document.getElementById('role').addEventListener('change', function() {
        const adminGroup = document.getElementById('admin-assignment-group');
        adminGroup.classList.toggle('hidden', !(CURRENT_USER_ROLE === 'superadmin' && this.value === 'user'));
    });

    // 4.6. Carga de desplegables jerárquicos (Empresas, Sucursales, Departamentos)
    async function loadCompanies(selectedId = null) {
        const companySelect = document.getElementById('company_id');
        if (!companySelect) return;
        companySelect.innerHTML = '<option value="">Cargando empresas...</option>';
        const result = await window.api.get('api/get_companies.php');
        
        if (result && result.success) {
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
    }


    const companySelect = document.getElementById('company_id');
    if (companySelect) {
        companySelect.addEventListener('change', async function() {
            const companyId = this.value;
            await loadBranches(companyId);
        });
    }

    async function loadBranches(companyId, selectedBranchId = null, selectedDepartmentId = null) {
        const branchSelect = document.getElementById('branch_id');
        const departmentSelect = document.getElementById('department_id');
        if (!branchSelect || !departmentSelect) return;

        departmentSelect.innerHTML = '<option value="">Seleccione una sucursal</option>'; // Resetea el de departamentos

        // Handle case where no company is selected
        if (!companyId) {
            branchSelect.innerHTML = '<option value="">Seleccione una empresa primero</option>';
            return;
        }

        branchSelect.innerHTML = '<option value="">Cargando...</option>';
        const result = await window.api.get(`api/get_branches.php?company_id=${companyId}`);

        if (result && result.success) {
            if (result.data.length === 0) {
                branchSelect.innerHTML = '<option value="">No hay sucursales</option>';
            } else {
                branchSelect.innerHTML = '<option value="">Seleccionar sucursal</option>';
                result.data.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = `${branch.name} (${branch.province})`;
                    if (selectedBranchId && branch.id == selectedBranchId) {
                        option.selected = true;
                    }
                    branchSelect.appendChild(option);
                });

                // Si se preseleccionó una sucursal, cargar sus departamentos
                if (selectedBranchId) {
                    await loadDepartmentsByBranch(selectedBranchId, selectedDepartmentId);
                }
            }
        } else {
            branchSelect.innerHTML = '<option value="">Error al cargar</option>';
            if (result) {
                console.error('API Error (loadBranches):', result.message);
            }
        }
    }

    document.getElementById('branch_id').addEventListener('change', function() {
        const branchId = this.value;
        loadDepartmentsByBranch(branchId);
    });

    async function loadDepartmentsByBranch(branchId, selectedId = null) {
        const departmentSelect = document.getElementById('department_id');
        if (!branchId) {
            departmentSelect.innerHTML = '<option value="">Seleccione una sucursal primero</option>';
            return;
        }
        departmentSelect.innerHTML = '<option value="">Cargando...</option>';
        const result = await window.api.get(`api/get_departments.php?branch_id=${branchId}`);
        if (result && result.success) {
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
        if (!sitesContainer) return;
        try {
            const result = await window.api.get('api/manage_sites.php?action=list');
            
            // Safer handling of assigned sites to prevent errors if apiCall returns null
            let assignedSiteIds = [];
            if (userId) {
                const assignedResult = await window.api.get(`api/manage_users.php?action=get_assigned_sites&id=${userId}`);
                if (assignedResult && assignedResult.success) {
                    assignedSiteIds = assignedResult.data.map(s => s.id);
                }
            }

            if (result.success) {
                let html = '';
                result.data.forEach(site => {
                    const checked = assignedSiteIds.includes(site.id) ? 'checked' : '';
                    html += `<div class="form-check">
                        <input type="checkbox" class="site-checkbox" id="site-${site.id}" value="${site.id}" ${checked}>
                        <label for="site-${site.id}">${window.escapeHTML(site.name)}</label>
                    </div>`;
                });
                sitesContainer.innerHTML = html || '<p>No hay sitios para asignar.</p>';
            } else {
                sitesContainer.innerHTML = '<p class="error-message">Error al cargar sitios.</p>';
            }
        } catch (error) {
            sitesContainer.innerHTML = '<p class="error-message">Error al cargar sitios.</p>';
        }
    }

    // --- 5. GESTIÓN DE EMPRESAS ---
    const companiesTableBody = document.querySelector('#companies-table-body');
    const companyFormEl = document.getElementById('company-form');

    async function fetchCompanies() {
        if (!companiesTableBody) return;
        const result = await window.api.get('api/get_companies.php');
        if (result && result.success) {
            renderCompaniesTable(result.data);
        }
    }

    function renderCompaniesTable(companies) {
        if (!companiesTableBody) return;
        companiesTableBody.innerHTML = '';
        if (companies.length === 0) {
            companiesTableBody.innerHTML = '<tr><td colspan="3">No hay empresas para mostrar.</td></tr>';
            return;
        }
        companies.forEach(company => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${window.escapeHTML(company.name)}</td>
                <td>${safeDate(company.created_at)}</td>
                <td class="actions">
                    <button class="btn btn-sm btn-secondary edit-company-btn" data-id="${company.id}">Editar</button>
                    <button class="btn btn-sm btn-danger delete-company-btn" data-id="${company.id}">Eliminar</button>
                </td>
            `;
            companiesTableBody.appendChild(tr);
        });
    }

    document.getElementById('add-company-btn')?.addEventListener('click', () => {
        if (!companyFormEl) return;
        companyFormEl.reset();
        document.getElementById('company-id').value = '';
        document.getElementById('company-action').value = 'add';
        document.getElementById('company-modal-title').textContent = 'Agregar Empresa';
        openModal('company-modal');
    });

    companiesTableBody?.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-company-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm('¿Eliminar esta empresa?')) return;
            const id = deleteBtn.dataset.id;
            const result = await window.api.post('api/manage_companies.php', { action: 'delete', id });
            if (result && result.success) {
                fetchCompanies();
                loadCompanies();
            }
        }

        const editBtn = e.target.closest('.edit-company-btn');
        if (editBtn) {
            e.preventDefault();
            const id = editBtn.dataset.id;
            const result = await window.api.get(`api/get_company.php?id=${id}`);
            if (result && result.success) {
                document.getElementById('company-modal-title').textContent = 'Editar Empresa';
                document.getElementById('company-action').value = 'edit';
                document.getElementById('company-id').value = result.data.id;
                document.getElementById('company-name').value = result.data.name;
                openModal('company-modal');
            }
        }
    });

    companyFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(companyFormEl);
        const data = Object.fromEntries(formData.entries());
        data.id = parseInt(data.id) || null;
        const result = await window.api.post('api/manage_companies.php', data);
        if (result && result.success) {
            closeModal('company-modal');
            fetchCompanies();
            loadCompanies();
        }
    });

    // --- 6. GESTIÓN DE SUCURSALES ---
    const branchesTableBody = document.querySelector('#branches-table-body');
    const branchFormEl = document.getElementById('branch-form');

    async function fetchBranches() {
        if (!branchesTableBody) return;
        const result = await window.api.get('api/get_branches.php');
        if (result && result.success) {
            renderBranchesTable(result.data);
        }
    }

    function renderBranchesTable(branches) {
        if (!branchesTableBody) return;
        branchesTableBody.innerHTML = '';
        if (branches.length === 0) {
            branchesTableBody.innerHTML = '<tr><td colspan="5">No hay sucursales para mostrar.</td></tr>';
            return;
        }
        branches.forEach(branch => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${window.escapeHTML(branch.name)}</td>
                <td>${window.escapeHTML(branch.company_name)}</td>
                <td>${window.escapeHTML(branch.country_name || 'N/A')}</td>
                <td>${window.escapeHTML(branch.province)}</td>
                <td class="actions">
                    <button class="btn btn-sm btn-secondary edit-branch-btn" data-id="${branch.id}">Editar</button>
                    <button class="btn btn-sm btn-danger delete-branch-btn" data-id="${branch.id}">Eliminar</button>
                </td>
            `;
            branchesTableBody.appendChild(tr);
        });
    }

    document.getElementById('add-branch-btn')?.addEventListener('click', () => {
        if (!branchFormEl) return;
        branchFormEl.reset();
        document.getElementById('branch-id').value = '';
        document.getElementById('branch-action').value = 'add';
        document.getElementById('branch-modal-title').textContent = 'Agregar Sucursal';        
        loadCompaniesGeneric(document.getElementById('branch-company-id'));
        loadCountries();
        openModal('branch-modal');
    });

    branchesTableBody?.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-branch-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm('¿Eliminar esta sucursal?')) return;
            const id = deleteBtn.dataset.id;
            const result = await window.api.post('api/manage_branches.php', { action: 'delete', id });
            if (result && result.success) {
                fetchBranches();
            }
        }

        const editBtn = e.target.closest('.edit-branch-btn');
        if (editBtn) {
            e.preventDefault();
            const id = editBtn.dataset.id;
            const result = await window.api.get(`api/get_branch.php?id=${id}`);
            if (result && result.success) {
                document.getElementById('branch-modal-title').textContent = 'Editar Sucursal';
                document.getElementById('branch-action').value = 'edit';
                document.getElementById('branch-id').value = result.data.id;
                document.getElementById('branch-name').value = result.data.name;
                await loadCompaniesGeneric(document.getElementById('branch-company-id'), result.data.company_id);
                await loadCountries(result.data.country_id, result.data.province);
                openModal('branch-modal');
            }
        }
    });

    branchFormEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(branchFormEl);
        const data = Object.fromEntries(formData.entries());
        data.id = parseInt(data.id) || null;
        const result = await window.api.post('api/manage_branches.php', data);
        if (result && result.success) {
            closeModal('branch-modal');
            fetchBranches();
        }
    });

    async function loadCountries(selectedCountryId = null, selectedProvinceName = null) {
        const countrySelect = document.getElementById('branch-country-id');
        if (!countrySelect) return;
        try {
            const result = await window.api.get('api/get_countries.php');
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
        if (!provinceSelect) return;
        provinceSelect.innerHTML = '<option value="">Cargando...</option>';
        try {
            const result = await window.api.get(`api/get_provinces.php?country_id=${countryId}`);
            if (result.success) {
                provinceSelect.innerHTML = '<option value="">Seleccionar provincia</option>';
                result.data.forEach(province => {
                    const option = document.createElement('option');
                    option.value = province.name;
                    option.textContent = province.name;
                    if (selectedProvinceName && province.name === selectedProvinceName) option.selected = true;
                    provinceSelect.appendChild(option);
                });
            }
        } catch (error) {
            provinceSelect.innerHTML = '<option value="">Error</option>';
        }
    }

    document.getElementById('branch-country-id')?.addEventListener('change', function() {
        loadProvinces(this.value);
    });

    // --- 7. GESTIÓN DE DEPARTAMENTOS ---
    const departmentsTableBody = document.querySelector('#departments-table-body');
    const departmentForm = document.getElementById('department-form');

    async function fetchDepartments() {
        if (!departmentsTableBody) return;
        const result = await window.api.get('api/get_departments.php');
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
                <td>${window.escapeHTML(dept.name)}</td>
                <td>${window.escapeHTML(dept.company_name || 'N/A')}</td>
                <td>${window.escapeHTML(dept.branch_name || 'N/A')}</td>
                <td class="actions">
                    <button class="btn btn-sm btn-secondary edit-department-btn" data-id="${dept.id}">Editar</button>
                    <button class="btn btn-sm btn-danger delete-department-btn" data-id="${dept.id}">Eliminar</button>
                </td>
            `;
            departmentsTableBody.appendChild(tr);
        });
    }

    document.getElementById('add-department-btn')?.addEventListener('click', () => {
        if (!departmentForm) return;
        departmentForm.reset();
        document.getElementById('department-id').value = '';
        document.getElementById('department-action').value = 'add'; 
        document.getElementById('department-modal-title').textContent = 'Agregar Departamento';
        loadCompaniesGeneric(document.getElementById('department-company-id'));
        document.getElementById('department-branch-id').innerHTML = '<option value="">Seleccionar sucursal</option>';
        openModal('department-modal');
    });

    departmentsTableBody?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-department-btn');
        if (editBtn) {
            const id = editBtn.dataset.id;
            const result = await window.api.get(`api/manage_departments.php?id=${id}`);
            if (result && result.success) {
                const dept = result.data;
                departmentForm.reset();
                document.getElementById('department-id').value = dept.id;
                document.getElementById('department-action').value = 'edit';
                document.getElementById('department-modal-title').textContent = 'Editar Departamento';
                document.getElementById('department-name').value = dept.name;
                await loadCompaniesGeneric(document.getElementById('department-company-id'), dept.company_id);
                await loadBranchesGeneric(document.getElementById('department-branch-id'), dept.company_id, dept.branch_id);
                openModal('department-modal');
            }
        }

        const deleteBtn = e.target.closest('.delete-department-btn');
        if (deleteBtn) {
            if (!confirm('¿Eliminar este departamento?')) return;
            const id = deleteBtn.dataset.id;
            const result = await window.api.post('api/manage_departments.php', { action: 'delete', id: id });
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
        const result = await window.api.post('api/manage_departments.php', data);
        if (result && result.success) {
            closeModal('department-modal');
            fetchDepartments();
        }
    });

    document.getElementById('department-company-id')?.addEventListener('change', function() {
        loadBranchesGeneric(document.getElementById('department-branch-id'), this.value);
    });

    /**
     * Función genérica para cargar empresas en cualquier elemento <select>.
     * @param {HTMLSelectElement} selectElement - El elemento select a poblar.
     * @param {string|number|null} [selectedId=null] - El ID de la empresa a preseleccionar.
     */
    async function loadCompaniesGeneric(selectElement, selectedId = null) {
        if (!selectElement) return;
        selectElement.innerHTML = '<option value="">Cargando...</option>';
        const result = await window.api.get('api/get_companies.php');
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

    /**
     * Función genérica para cargar sucursales de una empresa en un <select>.
     * @param {HTMLSelectElement} selectElement - El elemento select a poblar.
     * @param {string|number} companyId - El ID de la empresa de la que cargar sucursales.
     * @param {string|number|null} [selectedId=null] - El ID de la sucursal a preseleccionar.
     */
    async function loadBranchesGeneric(selectElement, companyId, selectedId = null) {
        if (!selectElement) return;
        if (!companyId) {
            selectElement.innerHTML = '<option value="">Seleccionar sucursal</option>';
            return;
        }
        selectElement.innerHTML = '<option value="">Cargando...</option>';
        const result = await window.api.get(`api/get_branches.php?company_id=${companyId}`);
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

    // --- 8. GESTIÓN DE SITIOS ---
    const sitesTableBody = document.querySelector('#sites-table-body');
    const siteForm = document.getElementById('site-form');

    async function fetchSites() {
        if (!sitesTableBody) return;
        const result = await window.api.get('api/manage_sites.php?action=list');
        if (result && result.success) {
            renderSitesTable(result.data);
        }
    }

    function renderSitesTable(sites) {
        if (!sitesTableBody) return;
        sitesTableBody.innerHTML = '';
        if (sites.length === 0) {
            sitesTableBody.innerHTML = '<tr><td colspan="4">No hay sitios para mostrar.</td></tr>';
            return;
        }
        sites.forEach(site => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${window.escapeHTML(site.name)}</td>
                <td><a href="${site.url}" target="_blank">${window.escapeHTML(site.url)}</a></td>
                <td>${window.escapeHTML(site.username)}</td>
                <td class="actions">
                    <button class="btn btn-sm btn-secondary edit-site-btn" data-site-id="${site.id}">Editar</button>
                    <button class="btn btn-sm btn-danger delete-site-btn" data-site-id="${site.id}">Eliminar</button>
                </td>
            `;
            sitesTableBody.appendChild(tr);
        });
    }

    document.getElementById('add-site-btn')?.addEventListener('click', () => {
        if (!siteForm) return;
        siteForm.reset();
        document.getElementById('site-id').value = '';
        document.getElementById('site-action').value = 'add';
        document.getElementById('site-modal-title').textContent = 'Agregar Sitio';
        const visibilityGroup = document.getElementById('site-visibility-group');
        visibilityGroup.classList.toggle('hidden', CURRENT_USER_ROLE !== 'superadmin');
        openModal('site-modal');
    });

    sitesTableBody?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-site-btn');
        if (editBtn) {
            e.preventDefault();
            const id = editBtn.dataset.siteId;
            const result = await window.api.get(`api/manage_sites.php?action=get&id=${id}`);
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
                const visibilityGroup = document.getElementById('site-visibility-group');
                visibilityGroup.classList.toggle('hidden', CURRENT_USER_ROLE !== 'superadmin');
                openModal('site-modal');
            }
        }

        const deleteBtn = e.target.closest('.delete-site-btn');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm('¿Eliminar este sitio? Esta acción no se puede deshacer.')) return;
            const id = deleteBtn.dataset.siteId;
            const result = await window.api.post('api/manage_sites.php', { action: 'delete', id: id });
            if (result && result.success) {
                fetchSites();
            }
        }
    });

    siteForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(siteForm);
        const data = Object.fromEntries(formData.entries());
        data.id = parseInt(data.id) || null;
        const result = await window.api.post('api/manage_sites.php', data);
        if (result && result.success) {
            closeModal('site-modal');
            fetchSites();
        }
    });

    // Toggle para mostrar/ocultar contraseña en el modal de sitios
    siteForm?.addEventListener('click', e => {
        if (e.target.classList.contains('toggle-password')) {
            const passwordInput = e.target.previousElementSibling;
            if (passwordInput && passwordInput.type === 'password') {
                passwordInput.type = 'text';
                e.target.textContent = 'Ocultar';
            } else if (passwordInput && passwordInput.type === 'text') {
                passwordInput.type = 'password';
                e.target.textContent = 'Mostrar';
            }
        }
    });

    // --- 9. GESTIÓN DE MENSAJES ---
    const messagesTableBody = document.getElementById('messages-table-body');
    document.querySelector('[data-tab="messages-tab"]')?.addEventListener('click', fetchUserMessages);

    async function fetchUserMessages() {
        if (!messagesTableBody) return;
        try {
            const result = await window.api.get('api/get_user_messages.php');
            if (result.success) {
                messagesTableBody.innerHTML = '';
                if (result.data.length === 0) {
                    messagesTableBody.innerHTML = '<tr><td colspan="4">No hay mensajes.</td></tr>';
                    return;
                }
                result.data.forEach(msg => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${window.escapeHTML(msg.username)}</td>
                        <td>${window.escapeHTML(msg.message)}</td>
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

    messagesTableBody?.addEventListener('click', async (e) => {
        const replyBtn = e.target.closest('.reply-btn');
        if (replyBtn) {
            e.preventDefault();
            const userId = replyBtn.dataset.userId;
            const reply = prompt('Escriba su respuesta:');
            if (reply && reply.trim()) {
                const result = await window.api.post('api/send_message.php', {
                    receiver_id: userId,
                    message: reply.trim()
                });
                if (result && result.success) {
                    alert('Mensaje enviado.');
                    fetchUserMessages();
                }
            }
        }

        const deleteMsgBtn = e.target.closest('.delete-msg-btn');
        if (deleteMsgBtn) {
            e.preventDefault();
            if (!confirm('¿Eliminar este mensaje?')) return;
            const id = deleteMsgBtn.dataset.id;
            const result = await window.api.post('api/delete_message.php', { message_id: id });
            if (result && result.success) {
                fetchUserMessages();
            }
        }
    });

    // --- 10. GESTIÓN DE AUDITORÍA ---
    const auditTableBody = document.getElementById('audit-table-body');

    async function fetchAuditLogs() {
        if (!auditTableBody) return;
        try {
            const result = await window.api.get('api/get_audit_logs.php');
            if (result.success) {
                renderAuditTable(result.data);
            }
        } catch (error) {
            auditTableBody.innerHTML = '<tr><td colspan="6">Error al cargar la bitácora.</td></tr>';
        }
    }

    function renderAuditTable(logs) {
        if (!auditTableBody) return;
        auditTableBody.innerHTML = '';
        if (logs.length === 0) {
            auditTableBody.innerHTML = '<tr><td colspan="6">No hay registros en la bitácora.</td></tr>';
            return;
        }
        logs.forEach(log => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${log.id}</td>
                <td>${window.escapeHTML(log.username || 'Sistema')}</td>
                <td>${window.escapeHTML(log.action)}</td>
                <td>${window.escapeHTML(log.site_name || '-')}</td>
                <td><code>${window.escapeHTML(log.ip_address || 'N/A')}</code></td>
                <td>${safeDate(log.timestamp)}</td>
            `;
            auditTableBody.appendChild(tr);
        });
    }

    // --- 11. LÓGICA DE UI (PESTAÑAS Y MODALES) ---
    const tabNav = document.querySelector('.tab-nav');
    
    // Manejo de pestañas con delegación de eventos para mayor eficiencia.
    tabNav?.addEventListener('click', (e) => {
        const tab = e.target.closest('.tab-link');
        if (!tab) return;

        tabNav.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        const tabContentId = tab.dataset.tab;
        const tabContent = document.getElementById(tabContentId);
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        // Carga el contenido de la pestaña dinámicamente si es necesario.
        handleTabClick(tabContentId);
    });

    // Carga el contenido de ciertas pestañas solo cuando se hace clic en ellas por primera vez.
    const loadedTabs = new Set(); // Para no recargar en cada clic.
    function handleTabClick(tabId) {
        if (loadedTabs.has(tabId)) return;

        switch(tabId) {
            case 'audit-tab': fetchAuditLogs(); break;
            case 'messages-tab': fetchUserMessages(); break;
        }
        loadedTabs.add(tabId);
    }

    // --- 12. GESTIÓN DE TEMA (THEME) ---
    const themeSelect = document.getElementById('theme-select');

    /**
     * Carga el tema guardado en localStorage o el tema por defecto del usuario desde la BD.
     */
    function loadSavedTheme() {
        const savedTheme = localStorage.getItem('userTheme') || adminData.theme || 'light';
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

    // --- 13. INICIALIZACIÓN ---
    
    /**
     * Función principal que se ejecuta al cargar el DOM.
     * Configura los listeners y carga los datos iniciales.
     */
    function init() {
        // Configurar listeners para cerrar modales
        document.querySelectorAll('.close-btn').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modalId));
        });
        document.querySelectorAll('.close-modal-btn').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.modalId));
        });
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });

        // Cargar datos iniciales
        fetchUsers(); // La pestaña de usuarios es la principal
        if (CURRENT_USER_ROLE === 'superadmin') {
            // Para superadmin, cargamos todo al inicio ya que tiene acceso a todo.
            fetchSites();
            fetchCompanies();
            fetchBranches();
            fetchDepartments();
        }

        loadSavedTheme();
    }

    init();
});