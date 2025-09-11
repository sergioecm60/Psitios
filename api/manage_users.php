<?php
/**
 * api/manage_users.php
 * Endpoint de la API para gestionar usuarios (CRUD).
 * Permite listar, obtener, crear, editar y eliminar usuarios.
 * Utilizado por el panel de administración, con lógica de permisos para 'admin' y 'superadmin'.
 */

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// Limpia cualquier salida de buffer anterior
if (ob_get_level()) {
    ob_end_clean();
}

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

/**
 * Envía una respuesta de error en formato JSON y termina la ejecución.
 */
function send_json_error(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Actualiza las asignaciones de sitios (servicios) para un usuario.
 * Elimina las asignaciones antiguas y crea las nuevas.
 *
 * @param PDO $pdo Conexión a la base de datos.
 * @param int $userId ID del usuario.
 * @param array $siteIds Array de IDs de sitios a asignar.
 * @return bool True si hubo algún cambio (eliminación o inserción).
 */
function updateUserSiteAssignments(PDO $pdo, int $userId, array $siteIds): bool {
    $stmt_delete = $pdo->prepare("DELETE FROM services WHERE user_id = ?");
    $stmt_delete->execute([$userId]);
    $were_deleted = $stmt_delete->rowCount() > 0;

    $were_inserted = false;
    if (!empty($siteIds)) {
        $stmt_assign = $pdo->prepare("INSERT INTO services (user_id, site_id) VALUES (?, ?)");
        foreach ($siteIds as $site_id) {
            if (filter_var($site_id, FILTER_VALIDATE_INT)) {
                $stmt_assign->execute([$userId, $site_id]);
                $were_inserted = $were_inserted || ($stmt_assign->rowCount() > 0);
            }
        }
    }
    return $were_deleted || $were_inserted;
}

// --- Lógica Principal ---
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = get_pdo_connection();
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'] ?? 'user';
    $current_user_department_id = $_SESSION['department_id'] ?? null;

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;
            switch ($action) {
                case 'list':
                    $sql = "SELECT u.id, u.username, u.role, u.is_active, 
                                   c.name as company_name, b.name as branch_name, 
                                   b.province, d.name as department_name
                            FROM users u
                            LEFT JOIN companies c ON u.company_id = c.id
                            LEFT JOIN branches b ON u.branch_id = b.id
                            LEFT JOIN departments d ON u.department_id = d.id";
                    $params = [];

                    if ($current_user_role === 'admin' && $current_user_department_id) {
                        $sql .= " WHERE u.department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $sql .= " ORDER BY u.username ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($users as &$user) {
                        $user['id'] = (int)$user['id'];
                        $user['is_active'] = (bool)$user['is_active'];
                    }

                    echo json_encode(['success' => true, 'data' => $users]);
                    break;

                case 'get':
                    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        send_json_error(400, 'ID de usuario inválido.');
                    }

                    $sql = "SELECT id, username, role, is_active, company_id, branch_id, department_id, assigned_admin_id FROM users WHERE id = ?";
                    $params = [$id];

                    if ($current_user_role === 'admin' && $current_user_department_id) {
                        $sql .= " AND department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $user['id'] = (int)$user['id'];
                        $user['is_active'] = (bool)$user['is_active'];
                        $user['company_id'] = $user['company_id'] ? (int)$user['company_id'] : null;
                        $user['branch_id'] = $user['branch_id'] ? (int)$user['branch_id'] : null;
                        $user['department_id'] = $user['department_id'] ? (int)$user['department_id'] : null;
                        $user['assigned_admin_id'] = $user['assigned_admin_id'] ? (int)$user['assigned_admin_id'] : null;
                        echo json_encode(['success' => true, 'data' => $user]);
                    } else {
                        send_json_error(404, 'Usuario no encontrado o sin permisos.');
                    }
                    break;

                case 'get_assigned_sites':
                    $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                    if (!$user_id) {
                        send_json_error(400, 'ID de usuario inválido.');
                    }

                    if ($current_user_role === 'admin' && $current_user_department_id) {
                        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND department_id = ?");
                        $stmt_check->execute([$user_id, $current_user_department_id]);
                        if (!$stmt_check->fetch()) {
                            send_json_error(403, 'No tiene permiso para ver los sitios de este usuario.');
                        }
                    }

                    $stmt = $pdo->prepare("SELECT st.id FROM services s JOIN sites st ON s.site_id = st.id WHERE s.user_id = ?");
                    $stmt->execute([$user_id]);
                    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $sites]);
                    break;

                default:
                    send_json_error(400, 'Acción GET no válida.');
                    break;
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
                case 'edit':
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    $username = trim($input['username'] ?? '');
                    $password = $input['password'] ?? null;
                    $role = in_array($input['role'] ?? '', ['user', 'admin']) ? $input['role'] : 'user';
                    $is_active = filter_var($input['is_active'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    $company_id = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $branch_id = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $department_id = filter_var($input['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $assigned_admin_id = filter_var($input['assigned_admin_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

                    if (empty($username)) {
                        send_json_error(400, 'El nombre de usuario es requerido.');
                    }

                    if ($current_user_role === 'admin') {
                        if ($department_id != $current_user_department_id) {
                            send_json_error(403, 'No tiene permiso para gestionar usuarios fuera de su departamento.');
                        }
                        if ($role === 'admin') {
                            send_json_error(403, 'No tiene permiso para crear usuarios administradores.');
                        }
                    }

                    $check_sql = "SELECT id FROM users WHERE username = ?";
                    $check_params = [$username];
                    if ($action === 'edit' && $id) {
                        $check_sql .= " AND id != ?";
                        $check_params[] = $id;
                    }
                    $stmt = $pdo->prepare($check_sql);
                    $stmt->execute($check_params);
                    if ($stmt->fetch()) {
                        send_json_error(409, 'El nombre de usuario ya está en uso.');
                    }

                    if ($action === 'add') {
                        if (empty($password)) {
                            send_json_error(400, 'La contraseña es requerida para crear un nuevo usuario.');
                        }
                        $password_hash = hash_password($password);

                        $sql = "INSERT INTO users (username, password_hash, role, is_active, company_id, branch_id, department_id, assigned_admin_id, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $params = [$username, $password_hash, $role, $is_active, $company_id, $branch_id, $department_id, $assigned_admin_id, $current_user_id];

                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $user_id = (int)$pdo->lastInsertId();

                        updateUserSiteAssignments($pdo, $user_id, $input['assigned_sites'] ?? []);
                        $pdo->commit();

                        log_action($pdo, $current_user_id, null, "user_created: {$username}", $_SERVER['REMOTE_ADDR']);
                        echo json_encode(['success' => true, 'message' => 'Usuario creado con éxito.', 'id' => $user_id]);

                    } else {
                        if (!$id) {
                            send_json_error(400, 'ID de usuario no proporcionado para editar.');
                        }

                        $pdo->beginTransaction();

                        $sql_parts = [
                            "username = ?", "role = ?", "is_active = ?", "company_id = ?",
                            "branch_id = ?", "department_id = ?", "assigned_admin_id = ?"
                        ];
                        $params = [$username, $role, $is_active, $company_id, $branch_id, $department_id, $assigned_admin_id];

                        if (!empty($password)) {
                            $password_hash = hash_password($password);
                            $sql_parts[] = "password_hash = ?";
                            $params[] = $password_hash;
                        }

                        $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                        $params[] = $id;

                        if ($current_user_role === 'admin') {
                            $sql .= " AND department_id = ?";
                            $params[] = $current_user_department_id;
                        }

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $user_data_updated = $stmt->rowCount() > 0;
                        $sites_changed = updateUserSiteAssignments($pdo, $id, $input['assigned_sites'] ?? []);
                        $pdo->commit();

                        if ($user_data_updated || $sites_changed) {
                            log_action($pdo, $current_user_id, null, "user_edited: {$username}", $_SERVER['REMOTE_ADDR']);
                            echo json_encode(['success' => true, 'message' => 'Usuario actualizado con éxito.']);
                        } else {
                            send_json_error(404, 'Usuario no encontrado, sin permisos o sin cambios para aplicar.');
                        }
                    }
                    break;

                case 'delete':
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) {
                        send_json_error(400, 'ID de usuario no válido.');
                    }
                    if ($id === $current_user_id) {
                        send_json_error(403, 'No puedes eliminar tu propia cuenta.');
                    }

                    $stmt_get_name = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt_get_name->execute([$id]);
                    $user_to_delete = $stmt_get_name->fetch();
                    $username_for_log = $user_to_delete ? $user_to_delete['username'] : "ID {$id}";

                    $sql = "DELETE FROM users WHERE id = ?";
                    $params = [$id];

                    if ($current_user_role === 'admin') {
                        $sql .= " AND department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        log_action($pdo, $current_user_id, null, "user_deleted: {$username_for_log}", $_SERVER['REMOTE_ADDR']);
                        echo json_encode(['success' => true, 'message' => 'Usuario eliminado con éxito.']);
                    } else {
                        send_json_error(404, 'Usuario no encontrado o no tiene permiso para eliminarlo.');
                    }
                    break;

                default:
                    send_json_error(400, 'Acción POST no válida.');
                    break;
            }
            break;

        default:
            send_json_error(405, 'Método no permitido.');
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        send_json_error(409, 'Error de integridad de datos. Es posible que el nombre de usuario ya exista.');
    }
    send_json_error(500, 'Error de base de datos.');
} catch (Exception $e) {
    error_log("Error en manage_users.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}