<?php
// ===============================
// api/manage_branches.php
// ===============================
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// Validar CSRF para POST
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($method === 'POST' && !verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            exit;
        }
        
        $action = $input['action'] ?? '';
        $name = trim($input['name'] ?? '');
        $company_id = (int)($input['company_id'] ?? 0);
        $province = trim($input['province'] ?? '');

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                log_action($pdo, $_SESSION['user_id'], null, 'branch_deleted', $_SERVER['REMOTE_ADDR']);
                echo json_encode(['success' => true, 'message' => 'Sucursal eliminada']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada.']);
            }
            exit;
        }
        
        if (empty($name) || !$company_id || empty($province)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
            exit;
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO branches (name, company_id, province) VALUES (?, ?, ?)");
            $stmt->execute([$name, $company_id, $province]);
            echo json_encode(['success' => true, 'message' => 'Sucursal creada exitosamente']);
        } elseif ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE branches SET name = ?, company_id = ?, province = ? WHERE id = ?");
            $stmt->execute([$name, $company_id, $province, $id]);
            echo json_encode(['success' => true, 'message' => 'Sucursal actualizada exitosamente']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en manage_branches.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>