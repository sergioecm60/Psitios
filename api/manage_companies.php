<?php
/**
 * api/manage_companies.php
 * Endpoint para la gestión CRUD de Empresas.
 * Solo accesible por administradores.
 */

require_once '../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');
$pdo = get_pdo_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, name, created_at FROM companies ORDER BY name ASC");
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $companies]);
            break;

        case 'POST':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $id = $input['id'] ?? null;

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre de la empresa es requerido.']);
                exit;
            }

            if ($id) {
                // Actualizar empresa existente
                $stmt = $pdo->prepare("UPDATE companies SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Empresa actualizada correctamente.']);
            } else {
                // Crear nueva empresa
                $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
                $stmt->execute([$name]);
                $new_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Empresa creada correctamente.', 'id' => $new_id]);
            }
            break;

        case 'DELETE':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID de empresa no proporcionado.']);
                exit;
            }

            // Verificar si la empresa tiene usuarios asignados antes de borrar
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar la empresa porque tiene usuarios asignados.']);
                exit;
            }

            // Si no hay usuarios, proceder con la eliminación (las sucursales se borran en cascada)
            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Empresa eliminada correctamente.']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'La empresa no fue encontrada.']);
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;
    }
} catch (PDOException $e) {
    // Manejo de errores específicos de la base de datos
    if ($e->errorInfo[1] == 1062) { // Código de error para entrada duplicada (UNIQUE constraint)
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Ya existe una empresa con ese nombre.']);
    } else {
        http_response_code(500); // Internal Server Error
        // En un entorno de producción, sería bueno loggear $e->getMessage() en lugar de mostrarlo
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    // Captura de otros errores (ej. CSRF)
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Valida el token CSRF y termina la ejecución si no es válido.
 */
function validate_csrf_token() {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        throw new Exception('Token CSRF inválido o ausente.');
    }
}

?>