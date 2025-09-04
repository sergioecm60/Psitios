<?php
// api/notifications_api.php

// Asegurar que no haya salida antes del JSON
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Cabeceras para API JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Para entornos con CORS (opcional)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    ob_end_flush(); // Asegurar salida limpia
    exit;
}

// Cargar conexión a la base de datos
require_once '../bootstrap.php';

try {
    $pdo = get_pdo_connection();
} catch (Exception $e) {
    error_log("Error de conexión a DB en notifications_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    ob_end_flush();
    exit;
}

// Validar token CSRF (solo para POST)
function validateCsrfToken() {
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if (!$headerToken || !$sessionToken || !hash_equals($sessionToken, $headerToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Token CSRF inválido'
        ]);
        ob_end_flush();
        exit;
    }
}

// Manejar métodos HTTP
switch ($_SERVER['REQUEST_METHOD']) {
    case 'OPTIONS':
        // Preflight CORS
        http_response_code(200);
        exit;

    case 'GET':
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    n.id,
                    n.user_id,
                    n.site_id,
                    n.message,
                    n.is_read,
                    n.created_at,
                    n.resolved_at,
                    s.name AS site_name
                FROM notifications n
                LEFT JOIN sites s ON n.site_id = s.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($notifications as &$n) {
                $n['is_read'] = (bool) $n['is_read'];
                $n['created_at'] = date('c', strtotime($n['created_at'])); // ISO 8601
                if ($n['resolved_at']) {
                    $n['resolved_at'] = date('c', strtotime($n['resolved_at']));
                }
                $n['title'] = $n['site_name'] 
                    ? 'Notificación: ' . $n['site_name'] 
                    : 'Notificación del sistema';
            }

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("Error al obtener notificaciones: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al cargar notificaciones'
            ]);
        }
        break;

    case 'POST':
        validateCsrfToken();

        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            break;
        }

        if (!isset($input['action'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción requerida']);
            break;
        }

        switch ($input['action']) {
            case 'mark_read':
                $id = $input['notification_id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID requerido']);
                    break;
                }
                try {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    echo json_encode(['success' => true, 'message' => 'Marcada como leída']);
                } catch (Exception $e) {
                    error_log("Error al marcar como leída: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                }
                break;

            case 'mark_all_read':
                try {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$_SESSION['user_id']]);
                    $count = $stmt->rowCount();
                    echo json_encode([
                        'success' => true,
                        'message' => $count > 0 ? "Marcadas $count como leídas" : "No había notificaciones sin leer"
                    ]);
                } catch (Exception $e) {
                    error_log("Error al marcar todas como leídas: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                }
                break;

            case 'delete':
                $id = $input['notification_id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID requerido']);
                    break;
                }
                try {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Notificación eliminada']);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'No encontrada']);
                    }
                } catch (Exception $e) {
                    error_log("Error al eliminar notificación: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error interno']);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

// Limpiar buffer de salida
if (ob_get_level()) {
    ob_end_flush();
}
exit;