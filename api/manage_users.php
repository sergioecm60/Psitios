<?php
// api/manage_users.php

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// Validar CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($method === 'POST' && !verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    ob_end_flush();
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if ($action === 'list') {
                $stmt = $pdo->query("
                    SELECT 
                        u.id, u.username, u.role, u.is_active, u.created_at,
                        c.name as company_name,
                        b.name as branch_name,
                        b.province
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN branches b ON u.branch_id = b.id
                    ORDER BY u.username ASC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as &$user) {
                    $user['id'] = (int)$user['id'];
                    $user['is_active'] = (bool)$user['is_active'];
                }

                echo json_encode(['success' => true, 'data' => $users]);
            } elseif ($action === 'get' && $id) {
                $stmt = $pdo->prepare("
                    SELECT 
                        id, username, role, is_active, company_id, branch_id
                    FROM users 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    ob_end_flush();
                    exit;
                }
                $user['is_active'] = (bool)$user['is_active'];
                $user['id'] = (int)$user['id'];
                $user['company_id'] = $user['company_id'] ? (int)$user['company_id'] : null;
                $user['branch_id'] = $user['branch_id'] ? (int)$user['branch_id'] : null;
                echo json_encode(['success' => true, 'data' => $user]);
            } elseif ($action === 'get_assigned_sites' && $id) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.name 
                    FROM services svc
                    JOIN sites s ON svc.site_id = s.id
                    WHERE svc.user_id = ?
                ");
                $stmt->execute([$id]);
                $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $sites]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido']);
                ob_end_flush();
                exit;
            }

            $action = $input['action'] ?? null;
            $id = $input['id'] ?? null;
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? null;
            $role = $input['role'] ?? 'user';
            $is_active = !empty($input['is_active']) ? 1 : 0;
            $company_id = $input['company_id'] ?? null;
            $branch_id = $input['branch_id'] ?? null;
            $assigned_sites = $input['assigned_sites'] ?? [];
 
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

                $sql = "UPDATE users SET username = ?, role = ?, is_active = ?, company_id = ?, branch_id = ?";
                $params = [$username, $role, $is_active, $company_id ?: null, $branch_id ?: null];

                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = ?";
                    $params[] = $password_hash;
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

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

                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, is_active, company_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $password_hash, $role, $is_active, $company_id ?: null, $branch_id ?: null]);
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
}
exit;