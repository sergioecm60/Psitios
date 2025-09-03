<?php
/**
 * api/send_message.php
 * Envía un mensaje de un usuario a otro.
 */

require_once __DIR__ . '/../bootstrap.php';
require_auth('user'); // Permite user, admin, superadmin

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

validate_csrf_token();

$input = json_decode(file_get_contents('php://input'), true);
$receiver_id = $input['receiver_id'] ?? null;
$message = trim($input['message'] ?? '');

if (!$receiver_id || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$pdo = get_pdo_connection();
$sender_id = $_SESSION['user_id'];

// Verificar que el receptor exista
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$receiver_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuario receptor no encontrado']);
    exit;
}

// Enviar mensaje
$stmt = $pdo->prepare("
    INSERT INTO messages (sender_id, receiver_id, message)
    VALUES (?, ?, ?)
");
$stmt->execute([$sender_id, $receiver_id, $message]);

echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);

function validate_csrf_token() {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        throw new Exception('Token CSRF inválido');
    }
}