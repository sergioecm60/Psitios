<?php
/**
 * api/delete_user_site.php
 * Endpoint de la API para que un usuario autenticado elimine uno de sus sitios personales.
 * La seguridad se garantiza mediante una consulta atómica que verifica la propiedad del sitio
 * antes de eliminarlo.
 */

// Inicia el control del buffer de salida. `ob_end_clean()` limpia cualquier salida
// accidental previa (ej. espacios en blanco) para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que es responsable de:
// - Iniciar la sesión.
// - Cargar las variables de entorno (.env).
// - Cargar las configuraciones y funciones de ayuda (seguridad, base de datos).
require_once '../bootstrap.php';
// `require_auth()` es una función de seguridad que verifica si hay una sesión de usuario activa.
// Si no, detiene la ejecución, protegiendo el endpoint de accesos no autorizados.
require_auth();

// Informa al cliente (navegador) que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// Función de ayuda para estandarizar las respuestas de error.
// Centraliza la lógica de enviar un código de estado HTTP y un mensaje JSON.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP. Este endpoint solo debe aceptar solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error(405, 'Método no permitido.');
}

// 2. Verificar el token CSRF (Cross-Site Request Forgery).
// Esto asegura que la solicitud provenga de nuestra propia aplicación y no de un sitio malicioso.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error(400, 'JSON inválido.');
}

// 4. Obtener y validar el ID del sitio a eliminar.
// `filter_var` con `FILTER_VALIDATE_INT` asegura que sea un número entero válido.
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    send_json_error(400, 'ID de sitio no válido.');
}

// --- Lógica Principal ---

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // Obtiene una conexión a la base de datos a través de la función de ayuda.
    $pdo = get_pdo_connection();
    
    // Prepara la consulta de eliminación. Esta es una consulta ATÓMICA y SEGURA.
    // La cláusula `AND user_id = ?` es la pieza de seguridad más importante aquí.
    // Combina la acción (DELETE) y la autorización (el sitio debe pertenecer al usuario de la sesión)
    // en una sola operación, evitando condiciones de carrera.
    $stmt = $pdo->prepare("DELETE FROM user_sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);

    // `rowCount()` devuelve el número de filas afectadas.
    // Si es mayor que 0, la eliminación fue exitosa.
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Sitio eliminado con éxito.']);
    } else {
        // Si es 0, significa que no se encontró un sitio con ese ID que además perteneciera al usuario.
        // Se devuelve un error 404 genérico para no revelar si el sitio existe pero pertenece a otro usuario.
        send_json_error(404, 'Sitio no encontrado o no tienes permiso para eliminarlo.');
    }
} catch (Exception $e) {
    // Si ocurre una excepción (ej. fallo de conexión a la BD), se registra el error
    // para depuración y se envía una respuesta de error genérica al usuario.
    error_log("Error en delete_user_site.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor al intentar eliminar el sitio.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
ob_end_flush();
exit;