<?php
/**
 * api/report_problem.php
 * Endpoint para que un usuario reporte un problema general con un sitio.
 * Crea una notificación para los administradores.
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
header('Content-Type: application/json; charset=utf-8');

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

// 4. Validar el ID del sitio.
$site_id = filter_var($input['site_id'] ?? null, FILTER_VALIDATE_INT);
if (!$site_id) {
    send_json_error(400, 'ID de sitio no válido.');
}

// --- Lógica Principal ---
$pdo = null;
try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Iniciar una transacción para asegurar la atomicidad.
    $pdo->beginTransaction();

    // 1. Seguridad y eficiencia: Verificar acceso y obtener nombre del sitio en una sola consulta.
    $stmt = $pdo->prepare("
        SELECT st.name as site_name
        FROM services s
        JOIN sites st ON s.site_id = st.id
        WHERE s.user_id = ? AND s.site_id = ?
    ");
    $stmt->execute([$user_id, $site_id]);
    $site = $stmt->fetch();

    if (!$site) send_json_error(403, 'No tienes permiso para reportar un problema sobre este sitio.');

    // 2. Idempotencia: Verificar si ya existe una notificación activa para evitar duplicados.
    $stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND site_id = ? AND resolved_at IS NULL");
    $stmt->execute([$user_id, $site_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Ya se ha reportado un problema para este sitio. El administrador ha sido notificado.']);
        $pdo->commit(); // Cerrar la transacción aunque no se hagan cambios.
        if (ob_get_level()) ob_end_flush();
        exit;
    }

    // 3. Crear el mensaje y la notificación.
    $message = "🚨 El usuario '{$username}' ha reportado un problema con el sitio '{$site['site_name']}'.";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, site_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $site_id, $message]);

    // Confirmar los cambios en la base de datos.
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Problema reportado. Gracias por tu colaboración.'
    ]);

} catch (Exception $e) {
    // Si algo falla, revertir todos los cambios de la transacción.
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en report_problem.php: " . $e->getMessage());
    send_json_error(500, 'Error interno al reportar el problema.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;