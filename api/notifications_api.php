<?php
/**
 * api/notifications_api.php
 * Endpoint de la API para gestionar notificaciones.
 * Permite listar, marcar como leídas y eliminar notificaciones con una lógica de permisos
 * basada en roles (user, admin, superadmin).
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
require_auth();

// Informa al cliente que la respuesta será en formato JSON y no debe ser cacheada.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Lógica Principal ---
try {
    $pdo = get_pdo_connection();
    $method = $_SERVER['REQUEST_METHOD'];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'user';
    $department_id = $_SESSION['department_id'] ?? null;

    switch ($method) {
    case 'GET':
        $sql = "SELECT n.id, n.user_id, n.site_id, n.message, n.is_read, n.created_at, n.resolved_at, u.username as sender_username, s.name AS site_name
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                LEFT JOIN sites s ON n.site_id = s.id";
        $params = [];

        if ($user_role === 'admin' && $department_id) {
            $sql .= " WHERE u.department_id = ?";
            $params[] = $department_id;
        } elseif ($user_role === 'user') {
            $sql .= " WHERE n.user_id = ?";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notifications as &$n) {
            $n['id'] = (int)$n['id'];
            $n['is_read'] = (bool)$n['is_read'];
            $n['created_at'] = date('c', strtotime($n['created_at']));
            $n['resolved_at'] = $n['resolved_at'] ? date('c', strtotime($n['resolved_at'])) : null;
        }

        echo json_encode(['success' => true, 'data' => $notifications]);
        break;

    case 'POST':
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($csrf_token)) send_json_error(403, 'Token CSRF inválido.');

        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) send_json_error(400, 'JSON inválido.');

        $action = $input['action'] ?? null;
        switch ($action) {
            case 'mark_read':
                $id = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) send_json_error(400, 'ID de notificación requerido.');

                $sql = "UPDATE notifications n ";
                $params = [];
                if ($user_role === 'admin' && $department_id) {
                    $sql .= "JOIN users u ON n.user_id = u.id SET n.is_read = 1 WHERE n.id = ? AND u.department_id = ?";
                    $params = [$id, $department_id];
                } elseif ($user_role === 'user') {
                    $sql .= "SET is_read = 1 WHERE n.id = ? AND n.user_id = ?";
                    $params = [$id, $user_id];
                } else { // superadmin
                    $sql .= "SET is_read = 1 WHERE n.id = ?";
                    $params = [$id];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Marcada como leída.']);
                } else {
                    send_json_error(404, 'Notificación no encontrada o sin permisos.');
                }
                break;

            case 'mark_all_read':
                $sql = "UPDATE notifications n ";
                $params = [];
                if ($user_role === 'admin' && $department_id) {
                    $sql .= "JOIN users u ON n.user_id = u.id SET n.is_read = 1 WHERE n.is_read = 0 AND u.department_id = ?";
                    $params = [$department_id];
                } elseif ($user_role === 'user') {
                    $sql .= "SET is_read = 1 WHERE n.is_read = 0 AND n.user_id = ?";
                    $params = [$user_id];
                } else { // superadmin
                    $sql .= "SET is_read = 1 WHERE n.is_read = 0";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => "{$stmt->rowCount()} notificaciones marcadas como leídas."]);
                break;

            case 'delete':
                $id = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) send_json_error(400, 'ID de notificación requerido.');

                $sql = "DELETE n FROM notifications n ";
                $params = [];
                if ($user_role === 'admin' && $department_id) {
                    $sql .= "JOIN users u ON n.user_id = u.id WHERE n.id = ? AND u.department_id = ?";
                    $params = [$id, $department_id];
                } elseif ($user_role === 'user') {
                    $sql .= "WHERE n.id = ? AND n.user_id = ?";
                    $params = [$id, $user_id];
                } else { // superadmin
                    $sql .= "WHERE n.id = ?";
                    $params = [$id];
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Notificación eliminada.']);
                } else {
                    send_json_error(404, 'Notificación no encontrada o sin permisos.');
                }
                break;

            default:
                send_json_error(400, 'Acción no válida.');
        }
        break;

    default:
        send_json_error(405, 'Método no permitido.');
}
} catch (Exception $e) {
    error_log("Error en notifications_api.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;