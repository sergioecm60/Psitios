<?php
/**
 * api/resolve_notification.php
 * API para marcar una notificación como resuelta.
 */
require_once '../bootstrap.php';

require_auth('admin', true); // Solo admins, respuesta JSON
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON no válidos.']);
    exit;
}

// Verificación CSRF
if (!verify_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

$notification_id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de notificación no válido.']);
    exit;
}

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, resolved_at = NOW(), resolved_by_admin_id = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $notification_id]);
    echo json_encode(['success' => true, 'message' => 'Notificación marcada como resuelta.']);
} catch (\PDOException $e) {
    error_log("Error de DB al resolver notificación: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al resolver la notificación.']);
}