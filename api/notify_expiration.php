<?php

/**
 * api/notify_expiration.php
 * API para que un usuario notifique que la contraseña de un servicio ha expirado.
 */

require_once '../bootstrap.php';
require_auth(); // Requiere que el usuario esté logueado

header('Content-Type: application/json');
$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !verify_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida o error de seguridad.']);
    exit;
}

$service_id = filter_var($data['id'], FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de servicio no válido.']);
    exit;
}

try {
    // Actualiza el servicio, pero SOLO si pertenece al usuario actual para seguridad.
    $stmt = $pdo->prepare("UPDATE services SET password_needs_update = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$service_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Administrador notificado.']);
    } else {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'No tiene permiso para modificar este servicio.']);
    }
} catch (Exception $e) {
    error_log("Error en notify_expiration.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}