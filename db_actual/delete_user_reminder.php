<?php
if (ob_get_level()) ob_end_clean();
ob_start();

require_once '../bootstrap.php';
require_auth();

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
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de recordatorio no válido.']);
    exit;
}

$pdo = get_pdo_connection();
$stmt = $pdo->prepare("DELETE FROM user_reminders WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

echo json_encode(['success' => true, 'message' => 'Recordatorio eliminado.']);

ob_end_flush();
exit;