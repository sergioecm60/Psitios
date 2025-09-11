
<?php
/**
 * api/get_company.php
 * Endpoint de la API para obtener los detalles de una empresa específica por su ID.
 * Se utiliza en el panel de administración para poblar el formulario de edición de empresas.
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

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener y validar el ID de la empresa desde los parámetros GET de la URL.
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        send_json_error(400, 'ID de empresa inválido.');
    }

    // 2. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();
    // 3. Preparar y ejecutar la consulta para obtener los datos de la empresa.
    // Se usa una consulta preparada para prevenir inyección SQL.
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Verificar si se encontró la empresa.
    if (!$company) {
        send_json_error(404, 'Empresa no encontrada.');
    }

    // 5. Formatear los datos para la respuesta JSON.
    // Es una buena práctica asegurar que el ID sea un número entero.
    $company['id'] = (int)$company['id'];

    // 6. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $company]);
} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_company.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor al obtener la empresa.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;