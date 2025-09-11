<?php
/**
 * api/manage_departments.php
 * Endpoint de la API para gestionar departamentos (Crear, Obtener, Actualizar, Eliminar).
 * Recibe solicitudes GET para obtener un departamento y POST para las demás acciones.
 * Utilizado por el panel de administración.
 * Restringido solo para 'superadmin'.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación de superadministrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('superadmin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Lógica Principal ---
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = get_pdo_connection();

    switch ($method) {
        case 'GET':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                send_json_error(400, 'ID de departamento no proporcionado.');
            }

            // Obtener un solo departamento para el formulario de edición.
            $stmt = $pdo->prepare("SELECT id, name, company_id, branch_id FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($department) {
                $department['id'] = (int)$department['id'];
                $department['company_id'] = (int)$department['company_id'];
                $department['branch_id'] = (int)$department['branch_id'];
                echo json_encode(['success' => true, 'data' => $department]);
            } else {
                send_json_error(404, 'Departamento no encontrado.');
            }
            break;

        case 'POST':
            // Validar CSRF para todas las acciones POST.
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verify_csrf_token($csrf_token)) {
                send_json_error(403, 'Token CSRF inválido.');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                send_json_error(400, 'JSON inválido.');
            }

            // Determinar la acción a realizar.
            $action = $input['action'] ?? null;
            switch ($action) {
                case 'add':
                case 'edit':
                    $name = trim($input['name'] ?? '');
                    $branch_id = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT);

                    if (empty($name) || !$branch_id) {
                        send_json_error(400, 'El nombre y la sucursal son requeridos.');
                    }

                    // Obtener el company_id de la sucursal para mantener la consistencia.
                    $stmt = $pdo->prepare("SELECT company_id FROM branches WHERE id = ?");
                    $stmt->execute([$branch_id]);
                    $branch = $stmt->fetch();
                    if (!$branch) send_json_error(404, 'La sucursal seleccionada no existe.');
                    $company_id = $branch['company_id'];

                    // Prevenir duplicados en la misma sucursal.
                    $checkSql = "SELECT id FROM departments WHERE name = ? AND branch_id = ?";
                    $checkParams = [$name, $branch_id];
                    if ($action === 'edit') {
                        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                        if (!$id) send_json_error(400, 'ID de departamento inválido para editar.');
                        $checkSql .= " AND id != ?";
                        $checkParams[] = $id;
                    }
                    $stmt = $pdo->prepare($checkSql);
                    $stmt->execute($checkParams);
                    if ($stmt->fetch()) {
                        send_json_error(409, 'Ya existe un departamento con este nombre en la sucursal.');
                    }

                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO departments (name, branch_id, company_id) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $branch_id, $company_id]);
                        echo json_encode(['success' => true, 'message' => 'Departamento creado.', 'id' => (int)$pdo->lastInsertId()]);
                    } else { // 'edit'
                        $stmt = $pdo->prepare("UPDATE departments SET name = ?, branch_id = ?, company_id = ? WHERE id = ?");
                        $stmt->execute([$name, $branch_id, $company_id, $id]);
                        if ($stmt->rowCount() > 0) {
                            echo json_encode(['success' => true, 'message' => 'Departamento actualizado.']);
                        } else {
                            send_json_error(404, 'Departamento no encontrado o sin cambios.');
                        }
                    }
                    break;

                case 'delete':
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) send_json_error(400, 'ID de departamento inválido.');

                    // ✅ Validación de dependencias: no permitir eliminar si tiene usuarios o sitios.
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        send_json_error(400, 'No se puede eliminar: tiene usuarios asignados.');
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE department_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        send_json_error(400, 'No se puede eliminar: tiene sitios asignados.');
                    }

                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Departamento eliminado.']);
                    } else {
                        send_json_error(404, 'Departamento no encontrado.');
                    }
                    break;

                default:
                    send_json_error(400, 'Acción no válida.');
            }
            break;

        default:
            send_json_error(405, 'Método no permitido.');
    }
} catch (PDOException $e) {
    error_log("Error de base de datos en manage_departments.php: " . $e->getMessage());
    if ($e->getCode() == '23000') {
        send_json_error(400, 'No se puede eliminar el departamento porque tiene elementos asociados.');
    }
    send_json_error(500, 'Error de base de datos.');
} catch (Exception $e) {
    error_log("Error general en manage_departments.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;