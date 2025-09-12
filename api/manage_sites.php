<?php
/**
 * api/manage_sites.php
 * Endpoint de la API para gestionar sitios compartidos (CRUD).
 * Permite listar, obtener, crear, editar y eliminar sitios.
 * Utilizado por el panel de administración, con lógica de permisos para 'admin' y 'superadmin'.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Lógica Principal ---
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = get_pdo_connection();
    $current_user_id = $_SESSION['user_id'];
    $current_user_department_id = $_SESSION['department_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? 'user';

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;
            switch ($action) {
                case 'list':
                    $sql = "SELECT s.id, s.name, s.url, s.username, s.password_needs_update, s.notes, s.is_sso, d.name as department_name 
                            FROM sites s
                            LEFT JOIN departments d ON s.department_id = d.id";
                    $params = [];

                    if ($user_role === 'admin' && $current_user_department_id) {
                        $sql .= " WHERE s.department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $sql .= " ORDER BY s.name ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($sites as &$site) {
                        $site['id'] = (int)$site['id'];
                        $site['password_needs_update'] = (bool)$site['password_needs_update'];
                        $site['is_sso'] = (bool)$site['is_sso'];
                    }

                    echo json_encode(['success' => true, 'data' => $sites]);
                    break;

                case 'get':
                    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                    if (!$id) send_json_error(400, 'ID de sitio inválido.');

                    $sql = "SELECT id, name, url, username, notes, department_id, is_sso, password_encrypted IS NOT NULL as has_password FROM sites WHERE id = ?";
                    $params = [$id];

                    if ($user_role === 'admin' && $current_user_department_id) {
                        $sql .= " AND department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $site = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$site) send_json_error(404, 'Sitio no encontrado o sin permisos.');

                    $site['id'] = (int)$site['id'];
                    $site['department_id'] = $site['department_id'] ? (int)$site['department_id'] : null;
                    $site['has_password'] = (bool)$site['has_password'];
                    $site['is_sso'] = (bool)$site['is_sso'];

                    echo json_encode(['success' => true, 'data' => $site]);
                    break;

                default:
                    send_json_error(400, 'Acción GET no válida.');
            }
            break;

        case 'POST':
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verify_csrf_token($csrf_token)) send_json_error(403, 'Token CSRF inválido.');

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) send_json_error(400, 'JSON inválido.');

            $action = $input['action'] ?? null;
            switch ($action) {
                case 'add':
                case 'edit':
                    // Validación de campos
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    $name = trim($input['name'] ?? '');
                    $url = trim($input['url'] ?? '');
                    $username = trim($input['username'] ?? '');
                    $password = $input['password'] ?? null;
                    $notes = trim($input['notes'] ?? '');
                    $is_sso = filter_var($input['is_sso'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (empty($name) || empty($url)) send_json_error(400, 'Nombre y URL son requeridos.');
                    if (!filter_var('http://' . preg_replace('#^https?://#', '', $url), FILTER_VALIDATE_URL)) send_json_error(400, 'URL no válida.');

                    // Lógica de departamento
                    $department_id = null;
                    if ($user_role === 'superadmin') {
                        $department_id = filter_var($input['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    } elseif ($user_role === 'admin') {
                        $department_id = $current_user_department_id;
                    }

                    // Validar nombre duplicado
                    $check_sql = "SELECT id FROM sites WHERE name = ?";
                    $check_params = [$name];
                    if ($action === 'edit') $check_sql .= " AND id != ?";
                    if ($action === 'edit') $check_params[] = $id;
                    $stmt = $pdo->prepare($check_sql);
                    $stmt->execute($check_params);
                    if ($stmt->fetch()) send_json_error(409, 'Ya existe un sitio con este nombre.');

                    // Encriptar contraseña si se proporciona
                    $encrypted_password = null;
                    if ($password !== null && $password !== '') {
                        $encrypted_password = encrypt_data($password);
                        if (!$encrypted_password) send_json_error(500, 'Error al encriptar la contraseña.');
                    }

                    if ($action === 'add') {
                        $sql = "INSERT INTO sites (name, url, username, notes, created_by, department_id, is_sso, password_needs_update" . ($encrypted_password ? ", password_encrypted" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, 0" . ($encrypted_password ? ", ?" : "") . ")";
                        $params = [$name, $url, $username, $notes, $current_user_id, $department_id, $is_sso];
                        if ($encrypted_password) {
                            $params[] = $encrypted_password;
                        }
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        echo json_encode(['success' => true, 'message' => 'Sitio creado.', 'id' => (int)$pdo->lastInsertId()]);
                    } else { // edit
                        if (!$id) send_json_error(400, 'ID de sitio no proporcionado para editar.');
                        $sql_parts = ["name = ?", "url = ?", "username = ?", "notes = ?", "is_sso = ?", "password_needs_update = 0"];
                        $params = [$name, $url, $username, $notes, $is_sso];
                        if ($user_role === 'superadmin') {
                            $sql_parts[] = "department_id = ?";
                            $params[] = $department_id;
                        }
                        if ($encrypted_password) {
                            $sql_parts[] = "password_encrypted = ?";
                            $params[] = $encrypted_password;
                        }
                        $sql = "UPDATE sites SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                        $params[] = $id;
                        if ($user_role === 'admin') {
                            $sql .= " AND department_id = ?";
                            $params[] = $current_user_department_id;
                        }
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        if ($encrypted_password) {
                            $stmt_services = $pdo->prepare("UPDATE services SET password_needs_update = 0 WHERE site_id = ?");
                            $stmt_services->execute([$id]);
                        }

                        if ($stmt->rowCount() > 0) {
                            echo json_encode(['success' => true, 'message' => 'Sitio actualizado.']);
                        } else {
                            send_json_error(404, 'Sitio no encontrado, sin permisos o sin cambios.');
                        }
                    }
                    break;

                case 'delete':
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) send_json_error(400, 'ID de sitio no válido.');

                    // Obtener el nombre del sitio ANTES de eliminarlo para poder registrarlo.
                    $stmt_get_name = $pdo->prepare("SELECT name FROM sites WHERE id = ?");
                    $stmt_get_name->execute([$id]);
                    $site = $stmt_get_name->fetch();
                    $site_name_for_log = $site ? $site['name'] : "ID {$id}";

                    // ✅ Validación de dependencias: no permitir eliminar si está asignado.
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE site_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        send_json_error(400, 'No se puede eliminar el sitio porque está asignado a uno o más usuarios.');
                    }

                    $sql = "DELETE FROM sites WHERE id = ?";
                    $params = [$id];
                    if ($user_role === 'admin' && $current_user_department_id) {
                        $sql .= " AND department_id = ?";
                        $params[] = $current_user_department_id;
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        log_action($pdo, $current_user_id, null, "site_deleted: {$site_name_for_log}", $_SERVER['REMOTE_ADDR']);
                        echo json_encode(['success' => true, 'message' => 'Sitio eliminado.']);
                    } else {
                        send_json_error(404, 'Sitio no encontrado o sin permisos.');
                    }
                    break;

                default:
                    send_json_error(400, 'Acción POST no válida.');
            }
            break;

        default:
            send_json_error(405, 'Método no permitido.');
    }
} catch (PDOException $e) {
    error_log("Error de BD en manage_sites.php: " . $e->getMessage());
    if ($e->getCode() == '23000') {
        send_json_error(409, 'Error de integridad de datos. Es posible que el nombre ya exista.');
    }
    send_json_error(500, 'Error de base de datos.');
} catch (Exception $e) {
    error_log("Error en manage_sites.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;