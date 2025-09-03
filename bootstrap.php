<?php
/**
 * bootstrap.php
 *
 * Archivo de arranque principal. Carga configuraciones, inicia sesiones y define constantes.
 * ¡IMPORTANTE! No debe haber NINGÚN carácter (ni espacios) antes de la etiqueta <?php.
 */

// 1. Declaración de tipos estrictos. Debe ser la primera instrucción.
declare(strict_types=1);

// 2. Iniciar el buffer de salida. Captura cualquier salida (incluyendo errores/notices)
// para prevenir errores de "headers already sent" y asegurar que las respuestas JSON sean limpias.
ob_start();

// --- INICIO: CÓDIGO DE DEPURACIÓN ---
// 3. Configuración de reporte de errores para el entorno de desarrollo.
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// --- FIN: CÓDIGO DE DEPURACIÓN ---

// 4. Iniciar la sesión si no está activa.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. Cargar archivos de configuración y librerías.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

// 6. Definir constantes globales.
define('BASE_URL', '/Psitios/');