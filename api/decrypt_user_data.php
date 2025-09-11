<?php
/**
 * API segura para desencriptar y devolver las credenciales de un elemento personal del usuario.
 * Funciona tanto para 'user_sites' como para 'user_reminders'.
 * El usuario solo puede desencriptar datos que le pertenecen.
 */

// Asegura que no haya salida de datos previa para evitar errores en la respuesta JSON.
if (ob_get_level()) ob_end_clean();

// Carga el archivo de arranque principal que inicia la sesión y carga las configuraciones.
require_once '../bootstrap.php';
// Requiere que el usuario esté autenticado para poder usar esta función.
require_auth();

// Establece la cabecera para indicar que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Verificar que la solicitud se haya hecho usando el método POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// Validar el token CSRF para proteger contra ataques de falsificación de solicitudes entre sitios.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// Leer el cuerpo de la solicitud (que se espera sea JSON) y lo convierte en un array de PHP.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// Obtener y validar los parámetros de entrada: ID y tipo ('site' o 'reminder').
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$type = $input['type'] ?? ''; // 'site' o 'reminder'

if (!$id || !in_array($type, ['site', 'reminder'])) {
    send_json_error_and_exit(400, 'Parámetros inválidos: se requiere un ID y un tipo (site/reminder) válidos.');
}

// Inicia el bloque principal de lógica para manejar la operación de forma segura.
try {
    // Obtiene la conexión a la base de datos.
    $pdo = get_pdo_connection();
    // Obtiene el ID del usuario de la sesión actual.
    $user_id = $_SESSION['user_id'];

    // Determina dinámicamente el nombre de la tabla basado en el tipo de elemento.
    $table_name = ($type === 'site') ? 'user_sites' : 'user_reminders';
    
    // Define los campos a seleccionar. 'url' solo existe en 'user_sites'.
    $fields_to_select = 'username, password_encrypted, notes';
    if ($type === 'site') {
        $fields_to_select .= ', url';
    }

    // Prepara la consulta para obtener los datos. La cláusula `AND user_id = ?` es crucial
    // para la seguridad, ya que asegura que un usuario solo pueda acceder a sus propios datos.
    $stmt = $pdo->prepare("SELECT {$fields_to_select} FROM {$table_name} WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no se encuentra el elemento o no pertenece al usuario, se devuelve un error.
    if (!$item) {
        send_json_error_and_exit(404, 'Elemento no encontrado o no tiene permiso para verlo.');
    }
    
    // Si el elemento existe pero no tiene una contraseña guardada, se informa al usuario.
    if (empty($item['password_encrypted'])) {
        send_json_error_and_exit(404, 'No se encontraron credenciales para este elemento.');
    }

    // Llama a la función de ayuda para desencriptar la contraseña.
    $decrypted_password = decrypt_data($item['password_encrypted']);

    // Verifica si la desencriptación falló (p. ej., por una clave incorrecta o datos corruptos).
    if ($decrypted_password === null) {
        error_log("Fallo de desencriptación para {$type}_id: {$id}");
        send_json_error_and_exit(500, 'Error al procesar las credenciales.');
    }

    // Si todo es correcto, envía la respuesta JSON con el nombre de usuario y la contraseña desencriptada.
    echo json_encode(['success' => true, 'data' => [
        'username' => $item['username'],
        'password' => $decrypted_password,
        'url'      => $item['url'] ?? null, // Usa null coalescing por si es un reminder
        'notes'    => $item['notes'] ?? null
    ]]);

    // Captura cualquier excepción o error inesperado, lo registra y devuelve un error genérico 500.
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}