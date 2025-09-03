<?php
/**
 * api/send_message.php
 * Permite enviar un mensaje a otro usuario (ej: usuario → admin o admin → usuario).
 * Si no se especifica receiver_id, se envía al admin asignado (para usuarios).
 */

require_once '../bootstrap.php';
require_auth(); // Cualquier usuario autenticado puede enviar

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

// Decodificar cuerpo JSON
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$receiver_id = filter_var($input['receiver_id'] ?? null, FILTER_VALIDATE_INT);

$user_id = $_SESSION['user_id'];
$pdo = get_pdo_connection();

try {
    // Si es un usuario normal y no especifica receptor, usar su admin asignado
    if (!$receiver_id && $_SESSION['role'] === 'user') {
        $stmt = $pdo->prepare("SELECT assigned_admin_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $admin_id = $stmt->fetchColumn();

        if (!$admin_id) {
            throw new Exception('No tienes un administrador asignado.');
        }
        $receiver_id = $admin_id;
    }

    // Validar que el receptor existe y es válido
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND id != ?");
    $stmt->execute([$receiver_id, $user_id]);
    $receiver = $stmt->fetch();

    if (!$receiver) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Destinatario no válido.'
        ]);
        exit;
    }

    // Guardar mensaje
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$user_id, $receiver_id, $message]);

    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente.'
    ]);

} catch (Exception $e) {
    error_log("Error en send_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el mensaje.'
    ]);
}