<?php

/**
 * api/manage_services.php
 * API para gestionar las asignaciones de sitios (servicios) a los usuarios.
 * Permite listar, obtener, añadir, editar y eliminar asignaciones.
 * Utilizado por el panel de administración.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once '../bootstrap.php';
require_auth('admin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Lógica Principal ---
$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();
$current_user_role = $_SESSION['user_role'] ?? 'user';
$current_user_department_id = $_SESSION['department_id'] ?? null;

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;

            if ($action === 'list' && isset($_GET['user_id'])) {
                $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
                if (!$user_id) send_json_error(400, 'ID de usuario no válido.');

                // ✅ Security: Admin can only list services for users in their department.
                if ($current_user_role === 'admin' && $current_user_department_id) {
                    $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $target_user = $stmt->fetch();
                    if (!$target_user || $target_user['department_id'] != $current_user_department_id) {
                        send_json_error(403, 'No tiene permiso para ver los servicios de este usuario.');
                    }
                }

                $stmt = $pdo->prepare(
                    "SELECT s.id as service_id, st.id as site_id, st.name as site_name
                     FROM services s
                     JOIN sites st ON s.site_id = st.id
                     WHERE s.user_id = ?
                     ORDER BY st.name ASC"
                );
                $stmt->execute([$user_id]);
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($services as &$service) {
                    $service['service_id'] = (int)$service['service_id'];
                    $service['site_id'] = (int)$service['site_id'];
                }

                echo json_encode(['success' => true, 'data' => $services]);
            } else {
                send_json_error(400, 'Acción GET no válida.');
            }
            break;

        case 'POST':
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verify_csrf_token($csrf_token)) {
                send_json_error(403, 'Token CSRF inválido.');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                send_json_error(400, 'JSON inválido.');
            }

            $action = $input['action'] ?? null;

            switch ($action) {
                case 'add':
                    $user_id = filter_var($input['user_id'], FILTER_VALIDATE_INT);
                    $site_id = filter_var($input['site_id'], FILTER_VALIDATE_INT);

                    if (!$user_id || !$site_id) send_json_error(400, 'Usuario y Sitio son requeridos.');

                    // ✅ Security check for admin role
                    if ($current_user_role === 'admin' && $current_user_department_id) {
                        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $target_user = $stmt->fetch();
                        if (!$target_user || $target_user['department_id'] != $current_user_department_id) {
                            send_json_error(403, 'No tiene permiso para asignar servicios a este usuario.');
                        }
                    }

                    // ✅ Duplicate check
                    $stmt = $pdo->prepare("SELECT id FROM services WHERE user_id = ? AND site_id = ?");
                    $stmt->execute([$user_id, $site_id]);
                    if ($stmt->fetch()) {
                        send_json_error(409, 'Este sitio ya está asignado a este usuario.');
                    }

                    $stmt = $pdo->prepare("INSERT INTO services (user_id, site_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $site_id]);
                    echo json_encode(['success' => true, 'message' => 'Servicio asignado.', 'id' => (int)$pdo->lastInsertId()]);
                    break;

                case 'delete':
                    $id = filter_var($input['id'], FILTER_VALIDATE_INT);
                    if (!$id) send_json_error(400, 'ID de servicio no válido.');

                    // ✅ Security check for admin role using a JOIN in the DELETE statement
                    if ($current_user_role === 'admin' && $current_user_department_id) {
                        $stmt = $pdo->prepare(
                            "DELETE s FROM services s
                             JOIN users u ON s.user_id = u.id
                             WHERE s.id = ? AND u.department_id = ?"
                        );
                        $stmt->execute([$id, $current_user_department_id]);
                    } else { // Superadmin
                        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                        $stmt->execute([$id]);
                    }

                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Asignación eliminada.']);
                    } else {
                        send_json_error(404, 'Asignación no encontrada o sin permisos.');
                    }
                    break;

                default:
                    send_json_error(400, 'Acción POST no válida.');
            }
            break;

        default:
            send_json_error(405, 'Método no permitido.');
            break;
    }
} catch (PDOException $e) {
    error_log("Error de BD en manage_services.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
        send_json_error(409, 'No se pudo realizar la operación. Verifique que los datos no estén duplicados.');
    }
    send_json_error(500, 'Error de base de datos.');
} catch (Exception $e) {
    error_log("Error en manage_services.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;