<?php
/**
 * api/notify_expiration.php
 * Notifica al admin que un usuario necesita cambiar la contraseña de un servicio.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth(); // Usuario autenticado

header('Content-Type: application/json');
$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    ob_end_flush();
    exit;
}

// Validar CSRF desde header
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    ob_end_flush();
    exit;
}

// Leer y validar datos
$input = json_decode(file_get_contents('php://input'), true);
$service_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!$service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de servicio no válido.']);
    ob_end_flush();
    exit;
}

try {
    // Verificar que el servicio pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT s.site_id, st.name as site_name 
        FROM services s
        JOIN sites st ON s.site_id = st.id
        WHERE s.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$service_id, $user_id]);
    $service = $stmt->fetch();

    if (!$service) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tiene permiso para modificar este servicio.']);
        ob_end_flush();
        exit;
    }

    // Actualizar estado
    $stmt = $pdo->prepare("UPDATE services SET password_needs_update = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$service_id, $user_id]);

    // ✅ Crear notificación con nombre del usuario y sitio
    $message = "🔐 El usuario '{$username}' necesita cambiar la contraseña del sitio '{$service['site_name']}'.";

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, site_id, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$user_id, $service['site_id'], $message]);

    echo json_encode(['success' => true, 'message' => 'Administrador notificado.']);

} catch (Exception $e) {
    error_log("Error en notify_expiration.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;