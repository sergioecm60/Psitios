<?php
/**
 * api/manage_users.php
 * CRUD de usuarios (admin)
 */

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

try {
    switch ($method) {
        case 'GET':
            if (!isset($_GET['action'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
                exit;
            }

            switch ($_GET['action']) {
                case 'list':
                    $stmt = $pdo->query("
                        SELECT 
                            u.id,
                            u.username,
                            u.role,
                            u.is_active,
                            u.created_at,
                            u.company_id,
                            u.branch_id,
                            c.name as company_name,
                            b.name as branch_name,
                            b.province,
                            u.assigned_admin_id,
                            a.username as admin_username
                        FROM users u
                        LEFT JOIN companies c ON u.company_id = c.id
                        LEFT JOIN branches b ON u.branch_id = b.id
                        LEFT JOIN users a ON u.assigned_admin_id = a.id
                        ORDER BY u.username ASC
                    ");
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'data' => $users]);
                    break;

                case 'get':
                    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'ID inválido']);
                        exit;
                    }
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.id,
                            u.username,
                            u.role,
                            u.is_active,
                            u.company_id,
                            u.branch_id,
                            u.assigned_admin_id
                        FROM users u
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                        exit;
                    }
                    echo json_encode(['success' => true, 'data' => $user]);
                    break;

                case 'get_assigned_sites':
                    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
                        exit;
                    }
                    $stmt = $pdo->prepare("
                        SELECT s.id, st.name, st.url, s.username, s.password_needs_update
                        FROM services s
                        JOIN sites st ON s.site_id = st.id
                        WHERE s.user_id = ?
                    ");
                    $stmt->execute([$id]);
                    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'data' => $sites]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                    break;
            }
            break;

        case 'POST':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $username = trim($input['username'] ?? '');
            $role = $input['role'] ?? 'user';
            $is_active = $input['is_active'] ?? 1;
            $company_id = $input['company_id'] ?? null;
            $branch_id = $input['branch_id'] ?? null;
            $password = $input['password'] ?? null;
            $assigned_sites = $input['assigned_sites'] ?? [];

            if (empty($username)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nombre de usuario requerido']);
                exit;
            }

            if ($role !== 'user' && $role !== 'admin') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Rol inválido']);
                exit;
            }

            $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

            if ($id) {
                // Editar usuario
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, role = ?, is_active = ?, company_id = ?, branch_id = ?
                    " . ($password_hash ? ", password_hash = ?" : "") . "
                    WHERE id = ?
                ");
                $params = [$username, $role, $is_active, $company_id, $branch_id];
                if ($password_hash) $params[] = $password_hash;
                $params[] = $id;
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
            } else {
                // Crear usuario
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, role, is_active, company_id, branch_id, password_hash, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $role, $is_active, $company_id, $branch_id, $password_hash, $_SESSION['user_id']]);
                $newId = $pdo->lastInsertId();

                // Asignar admin (el creador)
                $stmt = $pdo->prepare("UPDATE users SET assigned_admin_id = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $newId]);

                echo json_encode(['success' => true, 'message' => 'Usuario creado', 'id' => $newId]);
            }

            // Asignar sitios
            if (!empty($assigned_sites)) {
                $stmt = $pdo->prepare("DELETE FROM services WHERE user_id = ?");
                $stmt->execute([$id ?: $newId]);

                $insertStmt = $pdo->prepare("
                    INSERT INTO services (user_id, site_id, username, password_hash, password_needs_update)
                    SELECT ?, site_id, '', '', 0 FROM sites WHERE id = ?
                ");
                foreach ($assigned_sites as $site_id) {
                    $insertStmt->execute([$id ?: $newId, $site_id]);
                }
            }
            break;

        case 'DELETE':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en manage_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

function validate_csrf_token() {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        throw new Exception('Token CSRF inválido');
    }
}