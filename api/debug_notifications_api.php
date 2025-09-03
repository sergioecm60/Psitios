<?php
// debug_notifications_api.php - Debugging version
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors instead

// Ensure clean output buffer
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set headers first
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

// Function to log debug info
function debug_log($message) {
    error_log(date('Y-m-d H:i:s') . " - Notifications API: " . $message);
}

// Function to send JSON and exit cleanly
function send_json_exit($data, $status = 200) {
    http_response_code($status);
    
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = json_encode(['success' => false, 'message' => 'JSON encoding error: ' . json_last_error_msg()]);
    }
    
    echo $json;
    exit();
}

try {
    debug_log("Request started - Method: " . $_SERVER['REQUEST_METHOD']);
    
    // Start session safely
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        debug_log("Session started");
    }
    
    // Debug session info
    debug_log("Session data: " . print_r($_SESSION, true));
    
    // Simple authorization check (adjust according to your system)
    if (!isset($_SESSION['user_id'])) {
        debug_log("No user_id in session");
        send_json_exit([
            'success' => false, 
            'message' => 'No autorizado - no user_id',
            'debug' => 'Session: ' . print_r($_SESSION, true)
        ], 401);
    }
    
    debug_log("User ID: " . $_SESSION['user_id']);
    
    // Handle different request methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            debug_log("Processing GET request");
            
            // For debugging, return mock data first
            $notifications = getMockNotifications();
            
            debug_log("Returning " . count($notifications) . " notifications");
            
            send_json_exit([
                'success' => true,
                'notifications' => $notifications,
                'debug' => [
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'count' => count($notifications)
                ]
            ]);
            break;

        case 'POST':
            debug_log("Processing POST request");
            
            $input = json_decode(file_get_contents('php://input'), true);
            debug_log("POST data: " . print_r($input, true));
            
            if (!$input) {
                send_json_exit(['success' => false, 'message' => 'Invalid JSON input'], 400);
            }
            
            $action = $input['action'] ?? null;
            switch ($action) {
                case 'mark_read':
                    $notification_id = $input['notification_id'] ?? null;
                    if (!$notification_id) {
                        send_json_exit(['success' => false, 'message' => 'ID de notificación requerido'], 400);
                    }
                    debug_log("Marking notification $notification_id as read");
                    send_json_exit(['success' => true, 'message' => 'Notificación marcada como leída (mock)']);
                    break;
                
                // Puedes añadir más acciones aquí en el futuro
                // case 'delete_all': ...

                default:
                    send_json_exit(['success' => false, 'message' => 'Acción no reconocida o no proporcionada'], 400);
            }
            break;

        case 'OPTIONS':
            // Handle preflight requests
            debug_log("Handling OPTIONS request");
            send_json_exit(['success' => true, 'message' => 'Options handled']);
            break;

        default:
            debug_log("Unsupported method: " . $_SERVER['REQUEST_METHOD']);
            send_json_exit(['success' => false, 'message' => 'Método no permitido'], 405);
    }

} catch (Throwable $e) {
    debug_log("Exception caught: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    send_json_exit([
        'success' => false, 
        'message' => 'Error interno del servidor',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], 500);
}

/**
 * Return mock notifications for testing
 */
function getMockNotifications() {
    return [
        [
            'id' => 1,
            'title' => 'Notificación de prueba 1',
            'message' => 'Este es un mensaje de prueba para verificar que el sistema funciona.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'is_read' => false
        ],
        [
            'id' => 2,
            'title' => 'Notificación de prueba 2',
            'message' => 'Segunda notificación de prueba.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'is_read' => true
        ],
        [
            'id' => 3,
            'title' => 'Sistema funcionando',
            'message' => 'El sistema de notificaciones está funcionando correctamente.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'is_read' => false
        ]
    ];
}

// This should never be reached, but just in case
debug_log("Script end reached without proper exit");
send_json_exit(['success' => false, 'message' => 'Unexpected script termination']);
?>