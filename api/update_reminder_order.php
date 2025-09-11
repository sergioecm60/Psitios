<?php
/**
 * api/update_reminder_order.php
 * Endpoint para actualizar el orden de los recordatorios de un usuario.
 * Recibe un array de IDs en el nuevo orden.
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
if (json_last_error() !== JSON_ERROR_NONE || !isset($input['order']) || !is_array($input['order'])) {
    send_json_error_and_exit(400, 'JSON inválido o formato de datos incorrecto. Se esperaba un array `order`.');
}

$ordered_ids = $input['order'];
$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE user_reminders SET display_order = ? WHERE id = ? AND user_id = ?"
    );

    foreach ($ordered_ids as $index => $id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) throw new Exception('ID de recordatorio inválido encontrado.');
        $stmt->execute([$index, $id, $user_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Orden de recordatorios actualizado.']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    send_json_error_and_exit(500, 'Error interno del servidor al actualizar el orden.', $e);
}