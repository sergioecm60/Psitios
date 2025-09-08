<?php
/**
 * api/manage_departments.php
 * Gestiona el CRUD para los departamentos.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth('superadmin'); // Solo superadmin puede gestionar departamentos

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// CSRF validation for POST requests
if ($method === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        ob_end_flush();
        exit;
    }
}

try {
    switch ($method) {
        case 'GET':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID de departamento no válido.']);
                ob_end_flush();
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, name, company_id, branch_id FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$department) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Departamento no encontrado.']);
            } else {
                echo json_encode(['success' => true, 'data' => $department]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
                ob_end_flush();
                exit;
            }

            $action = $input['action'] ?? null;
            $id = $input['id'] ?? null;
            $name = trim($input['name'] ?? '');
            $company_id = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $branch_id = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

            if (in_array($action, ['add', 'edit'])) {
                if (empty($name) || !$company_id || !$branch_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nombre, empresa y sucursal son requeridos.']);
                    ob_end_flush();
                    exit;
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO departments (name, company_id, branch_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $company_id, $branch_id]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Departamento creado.', 'id' => (int)$newId]);
            } elseif ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, company_id = ?, branch_id = ? WHERE id = ?");
                $stmt->execute([$name, $company_id, $branch_id, $id]);
                echo json_encode(['success' => true, 'message' => 'Departamento actualizado.']);
            } elseif ($action === 'delete' && $id) {
                // Check if any user is assigned to this department
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    http_response_code(409); // Conflict
                    echo json_encode(['success' => false, 'message' => 'No se puede eliminar. Hay usuarios asignados a este departamento.']);
                    ob_end_flush();
                    exit;
                }

                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Departamento eliminado.']);
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
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Ya existe un departamento con ese nombre en esa sucursal.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
    }
} catch (Exception $e) {
    error_log("Error en manage_departments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}

// Limpiar buffer
if (ob_get_level()) {
    ob_end_flush();
}
exit;