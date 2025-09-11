<?php
/**
 * api/delete_user_reminder.php
 * Endpoint para que un usuario elimine un recordatorio de su agenda personal.
 */

// Asegura que no haya salida de datos previa para evitar errores en la respuesta JSON.
if (ob_get_level()) ob_end_clean();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_error_and_exit(405, 'Método no permitido.');
    }

    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        send_json_error_and_exit(403, 'Token CSRF inválido.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        send_json_error_and_exit(400, 'ID de recordatorio no válido.');
    }

    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // Obtener el título para el log antes de eliminar
    $stmt_get_title = $pdo->prepare("SELECT title FROM user_reminders WHERE id = ? AND user_id = ?");
    $stmt_get_title->execute([$id, $user_id]);
    $reminder = $stmt_get_title->fetch();
    $reminder_title_for_log = $reminder ? $reminder['title'] : "ID {$id}";

    $stmt = $pdo->prepare("DELETE FROM user_reminders WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);

    log_action($pdo, $user_id, null, "reminder_deleted: {$reminder_title_for_log}", $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => true, 'message' => 'Recordatorio eliminado.']);

} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno al eliminar el recordatorio.', $e);
}