<?php
/**
 * api/manage_companies.php
 * Endpoint de la API para gestionar empresas (Crear, Actualizar, Eliminar).
 * Recibe solicitudes POST con una acción específica ('add', 'edit', 'delete').
 * Utilizado por el panel de administración.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    send_json_error(405, 'Método no permitido.');
}

// 2. Validar el token CSRF.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error(400, 'JSON inválido.');
}

// --- Lógica Principal ---
try {
    $pdo = get_pdo_connection();
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'add':
        case 'edit':
            $name = trim($input['name'] ?? '');
            if (empty($name)) {
                send_json_error(400, 'El nombre de la empresa es requerido.');
            }

            // ✅ Prevenir duplicados: verificar si ya existe una empresa con ese nombre.
            $checkSql = "SELECT id FROM companies WHERE name = ?";
            $checkParams = [$name];
            if ($action === 'edit') {
                $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) send_json_error(400, 'ID de empresa inválido para editar.');
                $checkSql .= " AND id != ?";
                $checkParams[] = $id;
            }
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute($checkParams);
            if ($stmt->fetch()) {
                send_json_error(409, 'Ya existe una empresa con este nombre.');
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO companies (name, created_at) VALUES (?, NOW())");
                $stmt->execute([$name]);
                echo json_encode(['success' => true, 'message' => 'Empresa creada exitosamente.', 'id' => (int)$pdo->lastInsertId()]);
            } else { // 'edit'
                $stmt = $pdo->prepare("UPDATE companies SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => true, 'message' => 'Empresa actualizada exitosamente.']);
            }
            break;

        case 'delete':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                send_json_error(400, 'ID de empresa inválido.');
            }

            // ✅ Validación de dependencias: no permitir eliminar si tiene sucursales.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE company_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                send_json_error(400, 'No se puede eliminar la empresa porque tiene sucursales asignadas.');
            }

            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                log_action($pdo, $_SESSION['user_id'], null, 'company_deleted', $_SERVER['REMOTE_ADDR']);
                echo json_encode(['success' => true, 'message' => 'Empresa eliminada.']);
            } else {
                send_json_error(404, 'Empresa no encontrada.');
            }
            break;

        default:
            send_json_error(400, 'Acción no válida.');
            break;
    }
} catch (PDOException $e) {
    error_log("Error de base de datos en manage_companies.php: " . $e->getMessage());
    // Código '23000' es de violación de integridad (ej. clave foránea).
    if ($e->getCode() == '23000') {
        send_json_error(400, 'No se puede eliminar la empresa porque tiene elementos asociados (ej. sucursales).');
    }
    send_json_error(500, 'Error de base de datos.');
} catch (Exception $e) {
    error_log("Error en manage_companies.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;