
<?php
//** */===============================
//* api/manage_companies.php
//* ===============================

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        $action = $input['action'] ?? '';
        $name = trim($input['name'] ?? '');
        
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nombre de empresa requerido']);
            exit;
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO companies (name, created_at) VALUES (?, NOW())");
            $stmt->execute([$name]);
            echo json_encode(['success' => true, 'message' => 'Empresa creada exitosamente']);
        } elseif ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE companies SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            echo json_encode(['success' => true, 'message' => 'Empresa actualizada exitosamente']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en manage_companies.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>
