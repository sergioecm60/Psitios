<?php
/**
 * api/manage_branches.php
 * Endpoint de la API para gestionar sucursales (Crear, Actualizar, Eliminar).
 * Recibe solicitudes POST con una acción específica ('add', 'edit', 'delete').
 * Utilizado por el panel de administración.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();}

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// 2. Validar el token CSRF.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// --- Lógica Principal ---

try {
    $pdo = get_pdo_connection();
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'add':
        case 'edit':
            // Validación de campos para 'add' y 'edit'
            $name = trim($input['name'] ?? '');
            $company_id = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
            $province = trim($input['province'] ?? '');

            if (empty($name) || !$company_id || empty($province)) {
                send_json_error_and_exit(400, 'Nombre, empresa y provincia son requeridos.');
            }

            // ✅ Prevenir duplicados: verificar si ya existe una sucursal con ese nombre en la misma empresa.
            $checkSql = "SELECT id FROM branches WHERE name = ? AND company_id = ?";
            $checkParams = [$name, $company_id];
            if ($action === 'edit') {
                $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) send_json_error_and_exit(400, 'ID de sucursal inválido para editar.');
                $checkSql .= " AND id != ?";
                $checkParams[] = $id;
            }
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute($checkParams);
            if ($stmt->fetch()) {
                send_json_error_and_exit(409, 'Ya existe una sucursal con este nombre en la empresa seleccionada.');
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO branches (name, company_id, province) VALUES (?, ?, ?)");
                $stmt->execute([$name, $company_id, $province]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Sucursal creada exitosamente.', 'id' => (int)$newId]);
            } else { // 'edit'
                $stmt = $pdo->prepare("UPDATE branches SET name = ?, company_id = ?, province = ? WHERE id = ?");
                $stmt->execute([$name, $company_id, $province, $id]);
                echo json_encode(['success' => true, 'message' => 'Sucursal actualizada exitosamente.']);
            }
            break;

        case 'delete':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                send_json_error_and_exit(400, 'ID de sucursal inválido.');
            }

            // ✅ Validación de dependencias: no permitir eliminar si tiene departamentos.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE branch_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                send_json_error_and_exit(400, 'No se puede eliminar la sucursal porque tiene departamentos asignados.');
            }

            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                log_action($pdo, $_SESSION['user_id'], null, 'branch_deleted', $_SERVER['REMOTE_ADDR']);
                echo json_encode(['success' => true, 'message' => 'Sucursal eliminada.']);
            } else {
                send_json_error_and_exit(404, 'Sucursal no encontrada.');
            }
            break;

        default:
            send_json_error_and_exit(400, 'Acción no válida.');
            break;
    }
} catch (Throwable $e) {
    // Si es una excepción de PDO por violación de integridad, damos un mensaje específico.
    if ($e instanceof PDOException && $e->getCode() == '23000') {
        send_json_error_and_exit(400, 'No se puede realizar la operación porque hay elementos asociados (ej. usuarios, departamentos).');
    }
    // Para cualquier otro error, se registra y se devuelve un error genérico.
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}