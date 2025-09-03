<?php
/**
 * api/create_notification.php
 * API para que un usuario reporte un problema con un sitio.
 */

require_once '../bootstrap.php';

require_auth('user', true);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON no válidos.']);
    exit;
}

if (!verify_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

$site_id = filter_var($data['site_id'] ?? null, FILTER_VALIDATE_INT);
if (!$site_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de sitio no válido.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND site_id = ? AND is_read = 0");
    $stmt->execute([$user_id, $site_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Ya existe una notificación abierta para este sitio.']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, site_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $site_id]);
    echo json_encode(['success' => true, 'message' => 'Notificación enviada correctamente.']);
} catch (\PDOException $e) {
    error_log("Error de DB en create_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}