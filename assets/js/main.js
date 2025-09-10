/**
 * /Psitios/assets/js/main.js
 *
 * Script global cargado en varias páginas.
 * Contiene funcionalidades compartidas como el manejador de llamadas a la API
 * y listeners para componentes que pueden aparecer en diferentes paneles,
 * como el botón para reportar problemas.
 */

document.addEventListener('DOMContentLoaded', function() {
    // --- 1. CONFIGURACIÓN GLOBAL ---
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : null;

    // --- 2. HELPERS (Funciones de Ayuda) ---

    /**
     * Objeto ayudante que centraliza y estandariza todas las llamadas fetch a la API.
     * Proporciona un manejo de errores robusto para problemas de red, respuestas no-JSON,
     * y errores del servidor, además de adjuntar automáticamente el token CSRF.
     */
    const api = {
        async _request(endpoint, options = {}) {
            try {
                const response = await fetch(endpoint, options);
                // Si la respuesta no es OK, intenta parsear el error del cuerpo.
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`Respuesta de error del servidor (${response.status}) para ${endpoint}:`, errorText);
                    try {
                        return JSON.parse(errorText);
                    } catch (e) {
                        return { success: false, message: `Error del servidor: ${response.status}. Revise la consola.` };
                    }
                }
                // Verifica si la respuesta es JSON antes de intentar parsearla.
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return await response.json();
                } else {
                    const responseText = await response.text();
                    console.error(`Respuesta inesperada (no-JSON) para ${endpoint}:`, responseText);
                    return { success: false, message: 'El servidor devolvió una respuesta en un formato inesperado.' };
                }
            } catch (error) {
                // Captura errores de red (ej. sin conexión).
                console.error(`Error de red para ${endpoint}:`, error);
                return { success: false, message: 'Error de conexión. Verifique su red.' };
            }
        },
        async get(endpoint) {
            return this._request(endpoint);
        },
        async post(endpoint, data) {
            // Comprobación de seguridad: no intentar hacer POST sin token.
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

    /**
     * Escapa caracteres HTML de una cadena para prevenir ataques XSS.
     * Utiliza `textContent` para una sanitización segura y recomendada.
     * @param {*} str - El valor a escapar.
     * @returns {string} - La cadena segura.
     */
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const p = document.createElement('p');
        p.textContent = String(str);
        return p.innerHTML;
    }
    // --- 3. LÓGICA DE COMPONENTES GLOBALES ---
    // Se usa delegación de eventos en `document.body` para manejar clics en botones
    // que pueden ser creados dinámicamente, como los de las tarjetas de servicio.
    document.body.addEventListener('click', async (e) => {
        if (e.target.matches('.btn-report-problem')) {
            const button = e.target;
            const siteId = button.dataset.siteId;
            
            if (!siteId) {
                console.error('El botón de reporte no tiene un site-id.');
                alert('Error interno: no se puede identificar el sitio.');
                return;
            }

            // Pide confirmación al usuario antes de enviar el reporte.
            if (confirm('¿Está seguro de que desea reportar un problema de acceso para este sitio?')) {
                try {
                    button.disabled = true;
                    button.textContent = 'Reportando...';

                    const result = await api.post('api/create_notification.php', { site_id: siteId });

                    // Proporciona feedback visual al usuario sobre el resultado.
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