<?php
/**
 * api/save_theme.php
 * Guarda la preferencia de tema del usuario en la base de datos.
 */

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth(); // Solo para usuarios autenticados

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
$theme = $input['theme'] ?? 'light';

// Validar que el tema sea uno de los permitidos
$allowed_themes = ['light', 'dark', 'blue', 'green'];
if (!in_array($theme, $allowed_themes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tema no válido.']);
    exit;
}

$pdo = get_pdo_connection();
$stmt = $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?");
$stmt->execute([$theme, $_SESSION['user_id']]);

echo json_encode(['success' => true, 'message' => 'Tema guardado.']);

ob_end_flush();
exit;