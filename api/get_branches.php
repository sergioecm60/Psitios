<?php
/**
 * api/get_branches.php
 * Endpoint de la API para obtener una lista de sucursales.
 * Este script tiene un doble propósito:
 * 1. Si se proporciona un `company_id` en la URL, filtra las sucursales para esa empresa específica.
 *    (Usado para poblar menús desplegables dependientes en los formularios).
 * 2. Si no se proporciona, devuelve una lista de todas las sucursales.
 *    (Usado para mostrar la tabla completa en la pestaña "Sucursales" del panel de admin).
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once __DIR__ . '/../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta información.
require_auth('admin');
// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// --- Lógica Principal ---

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();
    
    // 2. Obtener y validar el `company_id` opcional desde los parámetros GET de la URL.
    $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT) ?: null;
    
    // 3. Construir la consulta SQL dinámicamente.
    // Se parte de una consulta base y se añaden cláusulas según sea necesario.
    // Esto evita la duplicación de código (principio DRY).
    $sql = "
        SELECT b.id, b.name, b.province, b.company_id, c.name as company_name, co.name as country_name
        FROM branches b
        LEFT JOIN companies c ON b.company_id = c.id
        LEFT JOIN provinces p ON b.province = p.name
        LEFT JOIN countries co ON p.country_id = co.id
    ";
    // Array para los parámetros que se usarán en la consulta preparada, para prevenir inyección SQL.
    $params = [];

    // Si se proporciona un `company_id`, se añade un filtro `WHERE` a la consulta.
    if ($company_id) {
        $sql .= " WHERE b.company_id = ?";
        $params[] = $company_id;
        $sql .= " ORDER BY b.name ASC";
    } else {
        // Si no hay filtro, se ordena por nombre de compañía y luego por nombre de sucursal para la tabla general.
        $sql .= " ORDER BY c.name ASC, b.name ASC";
    }
    
    // 4. Preparar y ejecutar la consulta de forma segura.
    // `prepare` y `execute` con parámetros es el método estándar para prevenir inyección SQL.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 5. Obtener todos los resultados.
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Formatear los datos para la respuesta JSON.
    // Es una buena práctica asegurar que los tipos de datos sean correctos (ej. `int` en lugar de `string`).
    foreach ($branches as &$branch) {
        $branch['id'] = (int)$branch['id'];
        $branch['company_id'] = (int)$branch['company_id'];
    }

    // 7. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $branches]);
} catch (Throwable $e) {
    // Si ocurre una excepción, se registra el error y se envía una respuesta genérica.
    send_json_error_and_exit(500, 'Error interno del servidor al cargar las sucursales.', $e);
}