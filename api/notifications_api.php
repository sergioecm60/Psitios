<?php
// api/notifications_api.php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

require_once '../config/database.php';

// Validar token CSRF
function validateCsrfToken() {
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if (!$headerToken || !$sessionToken || !hash_equals($sessionToken, $headerToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Token CSRF inválido'
        ]);
        exit;
    }
}

try {
    $pdo = get_pdo_connection();
} catch (Exception $e) {
    error_log("Error de conexión a DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
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
                $n['created_at'] = date('Y-m-d H:i:s', strtotime($n['created_at']));
                if ($n['resolved_at']) {
                    $n['resolved_at'] = date('Y-m-d H:i:s', strtotime($n['resolved_at']));
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
            exit;
        }

        if (!isset($input['action'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción requerida']);
            exit;
        }

        switch ($input['action']) {
            case 'mark_read':
                $id = $input['notification_id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID requerido']);
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Notificación marcada como leída'
                    ]);
                } catch (Exception $e) {
                    error_log("Error al marcar como leída: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error interno del servidor'
                    ]);
                }
                break;

            case 'mark_all_read':
                try {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$_SESSION['user_id']]);
                    $count = $stmt->rowCount();

                    echo json_encode([
                        'success' => true,
                        'message' => $count > 0 
                            ? "Se marcaron $count notificaciones como leídas" 
                            : "No había notificaciones sin leer"
                    ]);
                } catch (Exception $e) {
                    error_log("Error al marcar todas como leídas: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error interno del servidor'
                    ]);
                }
                break;

            // ✅ Nueva acción: Eliminar notificación
            case 'delete':
                $id = $input['notification_id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID requerido']);
                    exit;
                }

                try {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Notificación eliminada'
                        ]);
                    } else {
                        http_response_code(404);
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Notificación no encontrada'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Error al eliminar notificación: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error interno del servidor'
                    ]);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Acción no válida: ' . $input['action']
                ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'success' => false, 
            'message' => 'Método no permitido'
        ]);
}