<?php
/**
 * api/get_user_reminder_details.php
 * Endpoint para obtener los detalles completos de un recordatorio personal específico.
 * La seguridad se garantiza verificando que el recordatorio pertenezca al usuario autenticado.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
require_auth();

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// 2. Obtener y validar el ID del recordatorio.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    send_json_error_and_exit(400, 'ID de recordatorio no válido.');
}

// --- Lógica Principal ---
try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // Consulta segura que obtiene los detalles y verifica la propiedad del recordatorio.
    $stmt = $pdo->prepare("SELECT id, type, title, username, notes, reminder_datetime FROM user_reminders WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $reminder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reminder) {
        send_json_error_and_exit(404, 'Recordatorio no encontrado o no tiene permiso para verlo.');
    }

    $reminder['id'] = (int)$reminder['id'];

    echo json_encode(['success' => true, 'data' => $reminder]);

} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno al cargar los detalles del recordatorio.', $e);
}