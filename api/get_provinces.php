<?php
/**
 * api/get_provinces.php
 * Endpoint de la API para obtener una lista de provincias.
 * Si se proporciona un `country_id`, filtra las provincias para ese país.
 * Se utiliza en el formulario de sucursales para el menú desplegable de provincias.
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
require_auth('admin');
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
    // 1. Obtener y validar el `country_id` opcional desde los parámetros GET de la URL.
    $country_id = filter_input(INPUT_GET, 'country_id', FILTER_VALIDATE_INT) ?: null;

    // 2. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();

    // 3. Construir la consulta SQL dinámicamente.
    $sql = "SELECT id, name FROM provinces";
    $params = [];

    // Si se proporciona un `country_id`, se añade un filtro `WHERE` a la consulta.
    if ($country_id) {
        $sql .= " WHERE country_id = ?";
        $params[] = $country_id;
    }
    $sql .= " ORDER BY name ASC";

    // 4. Preparar y ejecutar la consulta de forma segura.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // 5. Obtener todos los resultados.
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($provinces as &$province) {
        $province['id'] = (int)$province['id'];
    }

    // 7. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $provinces]);
} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_provinces.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor al cargar las provincias.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;