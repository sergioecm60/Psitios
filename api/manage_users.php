<?php
/**
 * api/manage_users.php
 * Endpoint de la API para gestionar usuarios (CRUD).
 * Utilizado por el panel de administración.
 */
// api/manage_users.php

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';
// ✅ MEJORA DE SEGURIDAD:  Solo los administradores pueden gestionar usuarios.
// En este caso no queremos que un superadmin cree otro superadmin por accidente
// Por lo que require_auth('admin') es suficiente y el superadmin también podrá gestionar
// a través de este endpoint

require_auth('admin');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// Validar CSRF
// ✅ Seguridad:  Validar CSRF para evitar ataques.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($method === 'POST' && !verify_csrf_token($csrf_token)) {
    // ✅ Código de estado HTTP 403:  Para prohibido.
    http_response_code(403);
    // ✅ Respuesta JSON:  Para indicar el fallo.
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    ob_end_flush();
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // ✅ Acción:  Obtener un usuario.
            $action = $_GET['action'] ?? null;
            // ✅ ID:  del usuario
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            // ✅ Acción: Listar usuarios
            if ($action === 'list') {
                // ✅ Rol: del usuario solicitante
                $user_role = $_SESSION['user_role'] ?? 'user';
                // ✅ Query base: la misma query para admin y superadmin
                $base_query = "
                    SELECT u.id, u.username, u.role, u.is_active, u.created_at, u.department_id,
                           c.name as company_name, b.name as branch_name, b.province, d.name as department_name
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    // ✅ Joins: de las diferentes tablas
                    LEFT JOIN branches b ON u.branch_id = b.id
                    // ✅ Join: tabla departamentos
                    LEFT JOIN departments d ON u.department_id = d.id";

                if ($user_role === 'admin' && !empty($_SESSION['department_id'])) {
                    // Admin solo ve usuarios de su departamento
                    $stmt = $pdo->prepare($base_query . " WHERE u.department_id = ? ORDER BY u.username ASC");
                    $stmt->execute([$_SESSION['department_id']]);
                } else { // SuperAdmin ve todo
                    $stmt = $pdo->query($base_query . " ORDER BY u.username ASC");
                }

                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // ✅ Iteración: para asegurar el tipo de datos
                foreach ($users as &$user) {
                    $user['id'] = (int)$user['id'];
                    // ✅ is_active: como booleano
                    $user['is_active'] = (bool)$user['is_active'];
                }

                // ✅ Imprimir resultado
                echo json_encode(['success' => true, 'data' => $users]);
                // ✅ Acción: Obtener usuario por id
            } elseif ($action === 'get' && $id) {
                // ✅ Rol: del usuario solicitante
                $user_role = $_SESSION['user_role'] ?? 'user';
                // ✅ Query:
                $sql = "
                    SELECT 
                        id, username, role, is_active, company_id, branch_id, department_id, assigned_admin_id
                    FROM users 
                    WHERE id = ?";
                // ✅ Parametros:
                $params = [$id];

                // ✅ MEJORA DE SEGURIDAD: Un admin solo puede ver usuarios de su departamento.
                if ($user_role === 'admin' && !empty($_SESSION['department_id'])) {
                    // ✅ Query: solo del departamento del admin
                    $sql .= " AND department_id = ?";
                    $params[] = $_SESSION['department_id'];
                }

                // ✅ Ejecutar query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    ob_end_flush();
                    exit;
                    // ✅ Tipos:
                }
                $user['is_active'] = (bool)$user['is_active'];
                $user['id'] = (int)$user['id'];
                $user['company_id'] = $user['company_id'] ? (int)$user['company_id'] : null;
                $user['branch_id'] = $user['branch_id'] ? (int)$user['branch_id'] : null;
                $user['department_id'] = $user['department_id'] ? (int)$user['department_id'] : null;
                $user['assigned_admin_id'] = $user['assigned_admin_id'] ? (int)$user['assigned_admin_id'] : null;
                echo json_encode(['success' => true, 'data' => $user]);
                // ✅ Acción:  get_assigned_sites
            } elseif ($action === 'get_assigned_sites' && $id) {
                // ✅ Query: para obtener los sitios asignados
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name 
                    FROM services svc
                    JOIN sites s ON svc.site_id = s.id
                    WHERE svc.user_id = ?
                ");
                // ✅ Ejecutar
                $stmt->execute([$id]);
                // ✅ Resultados:
                $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // ✅ Imprimir resultados
                echo json_encode(['success' => true, 'data' => $sites]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // ✅ Código de estado HTTP 400: Bad Request
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido']);
                ob_end_flush();
                exit;
            }

            $action = $input['action'] ?? null;
            // ✅ ID: del usuario
            $id = $input['id'] ?? null;
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? null;
            $role = $input['role'] ?? 'user';
            $is_active = !empty($input['is_active']) ? 1 : 0;
            $company_id = $input['company_id'] ?? null;
            $branch_id = $input['branch_id'] ?? null;
            // ✅ Sanitize department_id to ensure it's an integer or null, preventing errors with empty strings.
            $department_id = filter_var($input['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $assigned_admin_id = filter_var($input['assigned_admin_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $assigned_sites = $input['assigned_sites'] ?? [];
            $current_user_role = $_SESSION['user_role'];

            // Un admin solo puede crear usuarios en su propio departamento
            if ($current_user_role === 'admin') {
                $company_id = $_SESSION['company_id'];
                $branch_id = $_SESSION['branch_id'];
                $department_id = $_SESSION['department_id'];
                $role = 'user'; // Un admin solo puede crear usuarios, no otros admins.
            }

 
            if ($action === 'delete') {
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido para eliminar.']);
                    ob_end_flush();
                    exit;
                }
                if ($id == $_SESSION['user_id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propia cuenta.']);
                    ob_end_flush();
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([(int)$id]);

                if ($stmt->rowCount() > 0) {
                    log_action($pdo, $_SESSION['user_id'], null, 'user_deleted', $_SERVER['REMOTE_ADDR']);
                    echo json_encode(['success' => true, 'message' => 'Usuario eliminado.']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
                }
                ob_end_flush();
                exit;
            }
 
            if (in_array($action, ['add', 'edit']) && empty($username)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Usuario es requerido']);
                ob_end_flush();
                exit;
            }

            if ($id) {
                // Editar
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Usuario ya existe']);
                    ob_end_flush();
                    exit;
                }

                $sql = "UPDATE users SET username = ?, role = ?, is_active = ?, company_id = ?, branch_id = ?, department_id = ?";
                $params = [$username, $role, $is_active, $company_id, $branch_id, $department_id];

                if ($current_user_role === 'superadmin') {
                    $sql .= ", assigned_admin_id = ?";
                    $params[] = $assigned_admin_id;
                }

                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = ?";
                    $params[] = $password_hash;
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

                // ✅ MEJORA DE SEGURIDAD: Un admin solo puede editar usuarios de su departamento.
                if ($current_user_role === 'admin' && !empty($_SESSION['department_id'])) {
                    $sql .= " AND department_id = ?";
                    $params[] = $_SESSION['department_id'];
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Actualizar sitios asignados
                $stmt = $pdo->prepare("DELETE FROM services WHERE user_id = ?");
                $stmt->execute([$id]);

                foreach ($assigned_sites as $site_id) {
                    $stmt = $pdo->prepare("INSERT INTO services (user_id, site_id) VALUES (?, ?)");
                    $stmt->execute([$id, $site_id]);
                }

                echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
            } else {
                // Crear
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Usuario ya existe']);
                    ob_end_flush();
                    exit;
                }

                $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : password_hash('temp123', PASSWORD_DEFAULT);

                $created_by = $_SESSION['user_id'];
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, is_active, company_id, branch_id, department_id, created_by, assigned_admin_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Only superadmin can set assigned_admin_id on creation
                $insert_assigned_admin_id = ($current_user_role === 'superadmin') ? $assigned_admin_id : null;

                $stmt->execute([$username, $password_hash, $role, $is_active, $company_id, $branch_id, $department_id, $created_by, $insert_assigned_admin_id]);
                $newId = $pdo->lastInsertId();

                // Asignar sitios
                foreach ($assigned_sites as $site_id) {
                    $stmt = $pdo->prepare("INSERT INTO services (user_id, site_id) VALUES (?, ?)");
                    $stmt->execute([$newId, $site_id]);
                }

                echo json_encode(['success' => true, 'message' => 'Usuario creado', 'id' => (int)$newId]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en manage_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

if (ob_get_level()) {
    ob_end_flush();
}// ✅ Final del archivo
exit;