<?php
/**
 * api/toggle_reminder_pin.php
 * Endpoint para fijar o desfijar un recordatorio de un usuario.
 */

if (ob_get_level()) ob_end_clean();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    send_json_error_and_exit(400, 'ID de recordatorio inválido.');
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();

    // Actualiza el estado `is_pinned` invirtiéndolo (toggle)
    // La cláusula `WHERE user_id = ?` es crucial para la seguridad.
    $stmt = $pdo->prepare(
        "UPDATE user_reminders SET is_pinned = NOT is_pinned WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Estado de fijado actualizado.']);
    } else {
        send_json_error_and_exit(404, 'Recordatorio no encontrado o no tiene permiso para modificarlo.');
    }

} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor al actualizar el estado de fijado.', $e);
}