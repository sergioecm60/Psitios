<?php
require_once __DIR__ . '/../bootstrap.php';
require_auth('superadmin'); // Solo los superadmin pueden gestionar departamentos

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// Validar CSRF para métodos que modifican datos
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($method === 'POST' && !verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                // Obtener un solo departamento
                $stmt = $pdo->prepare("SELECT id, name, company_id, branch_id FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($department) {
                    echo json_encode(['success' => true, 'data' => $department]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Departamento no encontrado.']);
                }
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
                exit;
            }

            $action = $input['action'] ?? null;
            $id = $input['id'] ?? null;
            $name = trim($input['name'] ?? '');
            $branch_id = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT);

            if (empty($name) || empty($branch_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre y la sucursal son requeridos.']);
                exit;
            }

            if ($action === 'add') {
                // Verificar si ya existe un departamento con ese nombre en la misma sucursal
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND branch_id = ?");
                $stmt->execute([$name, $branch_id]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ya existe un departamento con este nombre en la sucursal seleccionada.']);
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO departments (name, branch_id, company_id) SELECT ?, ?, company_id FROM branches WHERE id = ?");
                $stmt->execute([$name, $branch_id, $branch_id]);
                echo json_encode(['success' => true, 'message' => 'Departamento creado con éxito.']);

            } elseif ($action === 'edit' && $id) {
                // Verificar duplicados, excluyendo el ID actual
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND branch_id = ? AND id != ?");
                $stmt->execute([$name, $branch_id, $id]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ya existe otro departamento con este nombre en la sucursal seleccionada.']);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE departments d JOIN branches b ON d.branch_id = b.id SET d.name = ?, d.branch_id = ?, d.company_id = b.company_id WHERE d.id = ?");
                $stmt->execute([$name, $branch_id, $id]);
                echo json_encode(['success' => true, 'message' => 'Departamento actualizado con éxito.']);

            } elseif ($action === 'delete' && $id) {
                // Verificar si el departamento está en uso
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No se puede eliminar el departamento porque tiene usuarios asignados.']);
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Departamento eliminado con éxito.']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida o ID faltante.']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    }
} catch (PDOException $e) {
    error_log("Error en manage_departments.php: " . $e->getMessage());
    http_response_code(500);
    // No mostrar detalles de la BDD en producción
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>