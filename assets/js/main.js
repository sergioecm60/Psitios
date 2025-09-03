document.addEventListener('DOMContentLoaded', function() {
    // --- ELEMENTOS GLOBALES ---
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

    // --- Objeto ayudante para centralizar las llamadas a la API ---
    const api = {
        async _request(endpoint, options = {}) {
            try {
                const response = await fetch(endpoint, options);
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`Respuesta de error del servidor (${response.status}) para ${endpoint}:`, errorText);
                    try {
                        return JSON.parse(errorText);
                    } catch (e) {
                        return { success: false, message: `Error del servidor: ${response.status}. Revise la consola.` };
                    }
                }
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return await response.json();
                } else {
                    const responseText = await response.text();
                    console.error(`Respuesta inesperada (no-JSON) para ${endpoint}:`, responseText);
                    return { success: false, message: 'El servidor devolvió una respuesta en un formato inesperado.' };
                }
            } catch (error) {
                console.error(`Error de red para ${endpoint}:`, error);
                return { success: false, message: 'Error de conexión. Verifique su red.' };
            }
        },
        async get(endpoint) {
            return this._request(endpoint);
        },
        async post(endpoint, data) {
            if (!csrfToken) {
                console.error('CSRF token no disponible. No se puede hacer POST.');
                return { success: false, message: 'Error de seguridad. Recargue la página.' };
            }

            return this._request(endpoint, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken 
                },
                body: JSON.stringify(data)
            });
        }
    };

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const p = document.createElement('p');
        p.textContent = String(str);
        return p.innerHTML;
    }

    // --- LÓGICA SOLO PARA LA PÁGINA DE ADMINISTRACIÓN ---
    if (document.body.classList.contains('page-admin')) {
        // ... (todas las variables) ...

        // ✅ LÓGICA DE PESTAÑAS: con verificación
        const tabContainer = document.querySelector('.admin-tabs');
        if (tabContainer) {
            tabContainer.addEventListener('click', function(e) {
                const tabButton = e.target.closest('.tab-link');
                if (!tabButton) return;

                e.preventDefault();
                document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.admin-tab-content').forEach(content => content.classList.remove('active'));
                tabButton.classList.add('active');
                const tabId = tabButton.dataset.tab;
                const targetContent = document.getElementById(tabId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        }

        // ... resto del código (usuarios, servicios, sitios) ...
    }

    // --- LÓGICA PARA REPORTAR PROBLEMAS ---
    document.body.addEventListener('click', async (e) => {
        if (e.target.matches('.btn-report-problem')) {
            const button = e.target;
            const siteId = button.dataset.siteId;
            
            if (!siteId) {
                console.error('El botón de reporte no tiene un site-id.');
                alert('Error interno: no se puede identificar el sitio.');
                return;
            }

            if (confirm('¿Está seguro de que desea reportar un problema de acceso para este sitio?')) {
                try {
                    button.disabled = true;
                    button.textContent = 'Reportando...';

                    const result = await api.post('api/create_notification.php', { site_id: siteId });

                    if (result.success) {
                        alert(result.message || 'Reporte enviado con éxito.');
                        button.textContent = 'Reportado';
                    } else {
                        throw new Error(result.message || 'Error desconocido al enviar el reporte.');
                    }
                } catch (error) {
                    console.error('Error al reportar problema:', error);
                    alert(`No se pudo enviar el reporte. Error: ${error.message}`);
                    button.disabled = false;
                    button.textContent = 'Reportar Problema';
                }
            }
        }
    });
});