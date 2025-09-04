<?php
/**
 * api/resolve_notification.php
 * Marca una notificación como resuelta y actualiza el estado del servicio.
 */

require_once '../bootstrap.php';
require_auth('admin', true);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

// Validar CSRF desde header
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$notification_id = filter_var($data['notification_id'] ?? null, FILTER_VALIDATE_INT);
if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de notificación no válido.']);
    exit;
}

$pdo = get_pdo_connection();
$admin_id = $_SESSION['user_id'];

try {
    // Obtener el site_id de la notificación
    $stmt = $pdo->prepare("SELECT site_id FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();

    if (!$notification) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notificación no encontrada.']);
        exit;
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

} catch (Exception $e) {
    error_log("Error en resolve_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}