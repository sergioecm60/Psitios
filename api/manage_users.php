<?php
/**
 * api/manage_users.php
 * CRUD de usuarios (admin)
 */

// Usa __DIR__ para evitar errores de ruta
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
                            u.assigned_admin_id
                        FROM users u
                        LEFT JOIN companies c ON u.company_id = c.id
                        LEFT JOIN branches b ON u.branch_id = b.id
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

                    // Verificar si existe la tabla `services`
                    $tables = $pdo->query("SHOW TABLES LIKE 'services'")->fetchAll();
                    if (empty($tables)) {
                        echo json_encode(['success' => true, 'data' => []]); // Tabla no existe = no hay sitios
                        exit;
                    }

                    $stmt = $pdo->prepare("
                        SELECT 
                            s.id, 
                            st.name, 
                            st.url, 
                            s.username, 
                            s.password_needs_update
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