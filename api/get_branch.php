
<?php
/**
 * api/get_branch.php
 * Endpoint de la API para obtener los detalles de una sucursal específica por su ID.
 * Se utiliza en el panel de administración para poblar el formulario de edición de sucursales.
 */

// Inicia el control del buffer de salida. `ob_end_clean()` limpia cualquier salida
// accidental previa (ej. espacios en blanco) para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que es responsable de:
// - Iniciar la sesión.
// - Cargar las variables de entorno (.env).
// - Cargar las configuraciones y funciones de ayuda (seguridad, base de datos).
require_once __DIR__ . '/../bootstrap.php';
// `require_auth('admin')` es una función de seguridad que verifica si el usuario está autenticado
// y tiene al menos el rol 'admin'. Si no, detiene la ejecución.
require_auth('admin');
// Informa al cliente (navegador) que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// Función de ayuda para estandarizar las respuestas de error.
// Centraliza la lógica de enviar un código de estado HTTP y un mensaje JSON, y termina el script.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Lógica Principal ---

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener y validar el ID de la sucursal desde los parámetros GET de la URL.
    // `filter_input` con `FILTER_VALIDATE_INT` es la forma segura de obtener y validar un número entero.
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        send_json_error(400, 'ID de sucursal inválido.');
    }

    // 2. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();

    // 3. Preparar y ejecutar la consulta SQL.
    // Se usa un `LEFT JOIN` con la tabla `provinces` para obtener el `country_id` asociado a la provincia.
    // Este `country_id` es crucial para que el frontend pueda preseleccionar correctamente
    // los menús desplegables de "País" y "Provincia" en el formulario de edición.
    $stmt = $pdo->prepare("
        SELECT b.*, p.country_id
        FROM branches b
        LEFT JOIN provinces p ON b.province = p.name
        WHERE b.id = ?
    ");
    // Se ejecuta la consulta de forma segura usando parámetros para prevenir inyección SQL.
    $stmt->execute([$id]);
    // Se obtiene el resultado como un array asociativo.
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Verificar si se encontró la sucursal.
    // Si `$branch` es `false`, significa que no existe una sucursal con ese ID.
    if (!$branch) {
        send_json_error(404, 'Sucursal no encontrada.');
    }

    // 5. Formatear los datos para la respuesta JSON.
    // Es una buena práctica asegurar que los tipos de datos sean correctos (ej. `int` en lugar de `string`).
    // Esto evita problemas de tipado en el frontend (JavaScript).
    $branch['id'] = (int)$branch['id'];
    $branch['company_id'] = (int)$branch['company_id'];
    $branch['country_id'] = $branch['country_id'] ? (int)$branch['country_id'] : null;

    // 6. Enviar la respuesta exitosa.
    // Se devuelve un objeto JSON con `success: true` y los datos de la sucursal.
    echo json_encode(['success' => true, 'data' => $branch]);
} catch (Exception $e) {
    // Si ocurre una excepción (ej. fallo de conexión a la BD), se registra el error
    // para depuración y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_branch.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;
?>