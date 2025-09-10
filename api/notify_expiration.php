<?php
/**
 * api/notify_expiration.php
 * Endpoint para que un usuario notifique a los administradores que la contraseña de un sitio
 * ha expirado o no funciona. Esto establece una marca en el servicio y crea una notificación.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
require_auth();

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error(405, 'Método no permitido.');
}

// 2. Validar el token CSRF.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error(400, 'JSON inválido.');
}

// 4. Validar el ID del servicio.
$service_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
if (!$service_id) {
    send_json_error(400, 'ID de servicio no válido.');
}

// --- Lógica Principal ---
$pdo = null;
try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Iniciar una transacción para asegurar la atomicidad de las operaciones.
    $pdo->beginTransaction();

    // 1. Seguridad: Verificar que el servicio pertenece al usuario y obtener datos.
    // También se obtiene `password_needs_update` para la comprobación de idempotencia.
    $stmt = $pdo->prepare("
        SELECT s.site_id, st.name as site_name, s.password_needs_update
        FROM services s
        JOIN sites st ON s.site_id = st.id
        WHERE s.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$service_id, $user_id]);
    $service = $stmt->fetch();

    if (!$service) send_json_error(403, 'No tiene permiso para notificar sobre este servicio.');

    // 2. Idempotencia: Si ya se ha notificado, no hacer nada más.
    if ($service['password_needs_update']) {
        echo json_encode(['success' => true, 'message' => 'Ya se ha notificado previamente sobre este sitio.']);
    } else {
        // 3. Actualizar el estado del servicio para indicar que la contraseña necesita atención.
        $stmt = $pdo->prepare("UPDATE services SET password_needs_update = 1 WHERE id = ?");
        $stmt->execute([$service_id]);

        // 4. Crear una notificación para los administradores.
        $message = "🔐 El usuario '{$username}' ha indicado que la contraseña del sitio '{$service['site_name']}' ha expirado o no funciona.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, site_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $service['site_id'], $message]);

        echo json_encode(['success' => true, 'message' => 'Se ha notificado al administrador. Gracias.']);
    }

    // Si todo fue bien, confirmar los cambios en la base de datos.
    $pdo->commit();

} catch (Exception $e) {
    // Si algo falla, revertir todos los cambios de la transacción.
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en notify_expiration.php: " . $e->getMessage());
    send_json_error(500, 'Error interno al notificar.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}