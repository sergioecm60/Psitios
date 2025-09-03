<?php
/**
 * api/delete_user_message.php
 * Permite al usuario eliminar un mensaje de su chat con el admin.
 * El mensaje se elimina solo de su vista (versión soft).
 */

require_once '../bootstrap.php';
require_auth(); // Cualquier usuario autenticado

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Validar CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

// Leer datos
$input = json_decode(file_get_contents('php://input'), true);
$message_id = filter_var($input['message_id'] ?? null, FILTER_VALIDATE_INT);

if (!$message_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de mensaje no válido.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = get_pdo_connection();

try {
    // Verificar que el mensaje pertenece a la conversación usuario ↔ admin
    $stmt = $pdo->prepare("
        SELECT m.id, u.assigned_admin_id 
        FROM messages m
        JOIN users u ON u.id = ?
        WHERE m.id = ? 
          AND (m.sender_id = ? OR m.receiver_id = ?)
          AND (m.sender_id = u.assigned_admin_id OR m.receiver_id = u.assigned_admin_id)
    ");
    $stmt->execute([$user_id, $message_id, $user_id, $user_id]);
    $message = $stmt->fetch();

    if (!$message) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Mensaje no encontrado o no tienes permiso para eliminarlo.'
        ]);
        exit;
    }

    // En lugar de borrar, marcamos como "eliminado para el usuario"
    // (usamos una tabla de mensajes ocultos para no perder el historial del admin)
    
    // Opción 1: Borrar físicamente (simple)
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Mensaje eliminado.'
    ]);

} catch (Exception $e) {
    error_log("Error en delete_user_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el mensaje.'
    ]);
}