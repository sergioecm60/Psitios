<?php
declare(strict_types=1);
/**
 * bootstrap.php
 */
// Establecer zona horaria a Buenos Aires
date_default_timezone_set('America/Argentina/Buenos_Aires');

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar configuraciones
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

define('BASE_URL', '/Psitios/');

// Función global para obtener conexión PDO usando las configuraciones de database.php
function get_pdo_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
            
            // Configuraciones específicas para MySQL 8.4.3 Percona
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $pdo->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                echo "Error de conexión a la base de datos\n";
            } else {
                http_response_code(500);
                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
                } else {
                    echo "Error de conexión a la base de datos";
                }
            }
            exit(1);
        }
    }
    
    return $pdo;
}

// Función para registrar acciones en la bitácora de auditoría
function log_action($pdo, $user_id, $service_id, $action, $ip_address) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, service_id, action, ip_address, timestamp) 
             VALUES (:user_id, :service_id, :action, :ip_address, NOW())"
        );
        $stmt->execute([
            ':user_id' => $user_id,
            ':service_id' => $service_id,
            ':action' => $action,
            ':ip_address' => $ip_address
        ]);
    } catch (Exception $e) {
        // Registrar en el log de errores del servidor, pero no interrumpir la solicitud principal.
        error_log("Fallo al registrar la acción de auditoría: " . $e->getMessage());
    }
}
// Función para verificar si estamos en una petición AJAX/API
function is_api_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' ||
           isset($_SERVER['HTTP_ACCEPT']) && 
           strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false ||
           strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
}

// Configurar manejo de errores para peticiones API
set_error_handler(function($severity, $message, $file, $line) {
    if (is_api_request()) {
        error_log("Error PHP: $message en $file:$line");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        exit;
    }
    return false; // Usar el manejador por defecto para peticiones web normales
});

set_exception_handler(function($exception) {
    if (is_api_request()) {
        error_log("Excepción no capturada: " . $exception->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        exit;
    }
    throw $exception; // Re-lanzar para peticiones web normales
});