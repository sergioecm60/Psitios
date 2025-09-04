<?php
/**
 * api/send_message.php
 * Envía un mensaje de un usuario a otro.
 */

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_auth('user'); // Permite user, admin, superadmin

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    ob_end_flush();
    exit;
}

// Validar CSRF desde el header (más seguro)
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    ob_end_flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    ob_end_flush();
    exit;
}

$receiver_id = $input['receiver_id'] ?? null;
$message = trim($input['message'] ?? '');

if (!$receiver_id || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    ob_end_flush();
    exit;
}

try {
    $pdo = get_pdo_connection();
    $sender_id = $_SESSION['user_id'];

    // Verificar que el receptor exista
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario receptor no encontrado']);
        ob_end_flush();
        exit;
    }

    // Enviar mensaje
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$sender_id, $receiver_id, $message]);

    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado',
        'data' => [
            'id' => (int)$pdo->lastInsertId(),
            'sender_id' => (int)$sender_id,
            'receiver_id' => (int)$receiver_id,
            'message' => $message,
            'created_at' => date('c')
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en send_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;