<?php
/**
 * api/get_companies.php
 * Endpoint de la API para obtener una lista de todas las empresas.
 * Se utiliza en el panel de administración para poblar menús desplegables
 * y la tabla de gestión de empresas.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once __DIR__ . '/../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta información.
require_auth('admin');
// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();

    // 2. Ejecutar la consulta para obtener todas las empresas.
    // Se usa `query()` porque la consulta es estática y no contiene parámetros de usuario, por lo que no hay riesgo de inyección SQL.
    // Se ordena por nombre para una presentación consistente en el frontend.
    $stmt = $pdo->query("SELECT * FROM companies ORDER BY name ASC");
    
    // 3. Obtener todos los resultados como un array de objetos asociativos.
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatear los datos para la respuesta JSON.
    // Es una buena práctica asegurar que los tipos de datos sean correctos (ej. `int` en lugar de `string`).
    foreach ($companies as &$company) {
        $company['id'] = (int)$company['id'];
    }

    // 5. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $companies]);
} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_companies.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al cargar las empresas.']);
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;
