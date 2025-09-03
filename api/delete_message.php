<?php
/**
 * api/delete_message.php
 * Permite al administrador eliminar un mensaje.
 */

require_once '../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message_id = filter_var($input['message_id'] ?? null, FILTER_VALIDATE_INT);

if (!$message_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no válido.']);
    exit;
}

$pdo = get_pdo_connection();
$admin_id = $_SESSION['user_id'];

try {
    // Verificar que el mensaje es para este admin
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$message_id, $admin_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);

    echo json_encode(['success' => true, 'message' => 'Mensaje eliminado']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error']);
}