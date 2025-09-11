<?php
/**
 * Endpoint de la API para que un administrador elimine un mensaje que ha recibido.
 * La seguridad se garantiza al verificar que el `receiver_id` del mensaje
 * coincida con el ID del administrador que realiza la solicitud.
 */

// Asegura que no haya salida de datos previa para evitar errores en la respuesta JSON.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque principal que inicia la sesión y carga las configuraciones.
require_once '../bootstrap.php';
// Requiere que el usuario tenga rol de 'admin' o superior para acceder a esta función.
require_auth('admin');

// Establece la cabecera para indicar que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Verifica que la solicitud se haya hecho usando el método POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// Valida el token CSRF para proteger contra ataques de falsificación de solicitudes entre sitios.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// Lee el cuerpo de la solicitud (que se espera sea JSON) y lo convierte en un array de PHP.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// Obtiene y valida el ID del mensaje que se quiere eliminar.
$message_id = filter_var($input['message_id'] ?? null, FILTER_VALIDATE_INT);

if (!$message_id) {
    send_json_error_and_exit(400, 'ID de mensaje no válido.');
}

// Inicia el bloque principal de lógica para manejar la operación de forma segura.
try {
    // Obtiene la conexión a la base de datos.
    $pdo = get_pdo_connection();
    // Obtiene el ID del administrador de la sesión actual.
    $admin_id = $_SESSION['user_id'];

    // Prepara la consulta para eliminar el mensaje.
    // Esta es una consulta atómica y segura: combina la autorización y la eliminación en un solo paso.
    // La cláusula `AND receiver_id = ?` es la clave de la seguridad, ya que asegura
    // que un administrador solo pueda borrar los mensajes que le han sido enviados a él.
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$message_id, $admin_id]);

    // `rowCount()` devuelve el número de filas afectadas. Si es mayor que 0, la eliminación fue exitosa.
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Mensaje eliminado con éxito.']);
    } else {
        // Si `rowCount()` es 0, significa que no se encontró ningún mensaje con ese ID
        // que además perteneciera a este administrador.
        send_json_error_and_exit(404, 'Mensaje no encontrado o no tiene permiso para eliminarlo.');
    }

    // Captura cualquier excepción o error inesperado, lo registra y devuelve un error genérico 500.
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor al intentar eliminar el mensaje.', $e);
}