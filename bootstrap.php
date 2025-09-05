<?php
declare(strict_types=1);
/**
 * bootstrap.php
 */
// Establecer zona horaria a Buenos Aires
date_default_timezone_set('America/Argentina/Buenos_Aires');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Cargar dependencias de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Verificar si las dependencias de Composer están cargadas.
if (!class_exists(Dotenv\Dotenv::class)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    die("Error Crítico: Las dependencias de Composer no están instaladas correctamente.\n\n" .
        "Por favor, ejecute 'composer update' en la terminal desde el directorio del proyecto (C:\\laragon\\www\\Psitios).\n\n" .
        "La clase 'Dotenv\\Dotenv' no fue encontrada.");
}

// Verificar si el archivo .env existe para evitar errores fatales.
if (!file_exists(__DIR__ . '/.env')) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    die("Error de Configuración: El archivo .env no existe.\n\n" .
        "Por favor, copie el archivo '.env.example' a '.env' y complete sus credenciales de base de datos y la ENCRYPTION_KEY.\n\n" .
        "Este archivo es esencial para que la aplicación funcione localmente y está ignorado por Git por seguridad."
    );
}

// Cargar variables de entorno desde .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

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
            // Re-lanzar la excepción para que sea capturada por el manejador de errores del endpoint.
            throw new PDOException("Error de conexión a la base de datos. Verifique la configuración.", (int)$e->getCode(), $e);
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
// Convertir todos los errores (warnings, notices, etc.) en excepciones.
// Esto asegura que sean capturados por los bloques try/catch en los endpoints de la API.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // Este código de error no está incluido en error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// El manejador de excepciones global (`set_exception_handler`) se elimina.
// La responsabilidad de capturar excepciones ahora recae completamente en los
// bloques `try/catch (Throwable $e)` de cada script de la API,
// lo que evita terminaciones abruptas y respuestas vacías.