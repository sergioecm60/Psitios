<?php
/**
 * api/get_credentials.php
 * Endpoint de la API seguro para obtener y desencriptar las credenciales de un sitio compartido.
 * La seguridad se garantiza verificando que el usuario que realiza la solicitud
 * tenga una asignación de servicio (`services`) para el sitio solicitado.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth()` asegura que solo los usuarios autenticados puedan acceder.
require_auth(); // Requiere que el usuario esté logueado

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP. Este endpoint solo debe aceptar solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// 2. Verificar el token CSRF (Cross-Site Request Forgery).
// Esto asegura que la solicitud provenga de nuestra propia aplicación y no de un sitio malicioso.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// 4. Obtener y validar el ID del servicio.
$service_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
if (!$service_id) {
    send_json_error_and_exit(400, 'ID de servicio no válido o no proporcionado.');
}

// --- Lógica Principal ---

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // Prepara la consulta para obtener las credenciales. Esta es una consulta ATÓMICA y SEGURA.
    // - `JOIN services svc`: Une la tabla de sitios con la de asignaciones de servicios.
    // - `WHERE svc.id = ? AND svc.user_id = ?`: Esta es la clave de la seguridad.
    //   Verifica que el ID del servicio exista Y que esté asignado al usuario actual.
    //   Esto previene que un usuario pueda solicitar credenciales de un servicio que no le pertenece.
    $stmt = $pdo->prepare(
        "SELECT s.username, s.password_encrypted, s.url, s.notes
         FROM sites s
         JOIN services svc ON s.id = svc.site_id
         WHERE svc.id = ? AND svc.user_id = ?"
    );
    $stmt->execute([$service_id, $user_id]);
    $site_details = $stmt->fetch(PDO::FETCH_ASSOC);
 
    // Si no se encuentra ninguna fila, el servicio no existe o el usuario no tiene permiso.
    if (!$site_details) {
        send_json_error_and_exit(403, 'Servicio no encontrado o no tiene permiso para verlo.');
    }
    
    // Desencriptar la contraseña usando el método unificado.
    $decrypted_password = '';
    if (!empty($site_details['password_encrypted'])) {
        $decrypted_password = decrypt_data($site_details['password_encrypted']);
        
        // Verificar si la desencriptación falló (p. ej., por una clave de encriptación incorrecta o datos corruptos).
        if ($decrypted_password === null) {
            error_log("Fallo de desencriptación para service_id: {$service_id}");
            send_json_error_and_exit(500, 'Error al procesar las credenciales.');
        }
    }

    // Enviar la respuesta exitosa con los datos desencriptados.
    echo json_encode([
        'success' => true, 
        'data' => [
            'username' => $site_details['username'], 
            'password' => $decrypted_password,
            'url' => $site_details['url'],
            'notes' => $site_details['notes']
        ]
    ]);

} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}