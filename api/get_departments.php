<?php
/**
 * api/get_departments.php
 * Endpoint de la API para obtener una lista de departamentos.
 * Este script tiene un doble propósito:
 * 1. Si se proporciona un `branch_id` en la URL, filtra los departamentos para esa sucursal específica.
 *    (Usado para poblar menús desplegables dependientes en los formularios de usuario).
 * 2. Si no se proporciona, devuelve una lista de todos los departamentos.
 *    (Usado para mostrar la tabla completa en la pestaña "Departamentos" del panel de admin).
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta información.
require_auth('admin'); // Solo admins pueden ver los departamentos
// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener y validar el `branch_id` opcional desde los parámetros GET de la URL.
    $branch_id = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
    // 2. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();
    
    // 3. Construir la consulta SQL dinámicamente.
    // Se parte de una consulta base y se añaden cláusulas según sea necesario.
    $sql = "
        SELECT d.id, d.name, d.company_id, d.branch_id, c.name as company_name, b.name as branch_name
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN branches b ON d.branch_id = b.id
    ";
    // Array para los parámetros que se usarán en la consulta preparada para prevenir inyección SQL.
    $params = [];

    // Si se proporciona un `branch_id`, se añade un filtro `WHERE` a la consulta.
    if ($branch_id) {
        $sql .= " WHERE d.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " ORDER BY d.name ASC";
    
    // 4. Preparar y ejecutar la consulta de forma segura.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    // 5. Obtener todos los resultados.
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($departments as &$dept) {
        $dept['id'] = (int)$dept['id'];
        $dept['company_id'] = $dept['company_id'] ? (int)$dept['company_id'] : null;
        $dept['branch_id'] = $dept['branch_id'] ? (int)$dept['branch_id'] : null;
    }

    // 7. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $departments]);
} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_departments.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor al cargar los departamentos.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;