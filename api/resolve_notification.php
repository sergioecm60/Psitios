<?php
/**
 * api/resolve_notification.php
 * Marca una notificación como resuelta y actualiza el estado del servicio.
 */

if (ob_get_level()) {
    ob_end_clean();
}

require_once '../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// Validar CSRF desde header
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

$notification_id = filter_var($data['notification_id'] ?? null, FILTER_VALIDATE_INT);
if (!$notification_id) {
    send_json_error_and_exit(400, 'ID de notificación no válido.');
}

$pdo = get_pdo_connection();
$admin_id = $_SESSION['user_id'];

try {
    // Obtener el site_id de la notificación
    $stmt = $pdo->prepare("SELECT site_id FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();

    if (!$notification) {
        send_json_error_and_exit(404, 'Notificación no encontrada.');
    }

    // Marcar como resuelta
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, resolved_at = NOW(), resolved_by_admin_id = ? 
        WHERE id = ?
    ");
    $stmt->execute([$admin_id, $notification_id]);

    // ✅ Marcar el servicio como actualizado
    $stmt = $pdo->prepare("
        UPDATE services 
        SET password_needs_update = 0 
        WHERE site_id = ? AND password_needs_update = 1
    ");
    $stmt->execute([$notification['site_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Notificación resuelta y servicio actualizado.'
    ]);

} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}