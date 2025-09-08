<?php
if (ob_get_level()) ob_end_clean();
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$type = $input['type'] ?? ''; // 'site' o 'reminder'

if (!$id || !in_array($type, ['site', 'reminder'])) {
    http_response_code(400);
    exit;
}

$pdo = get_pdo_connection();
$user_id = $_SESSION['user_id'];

try {
    if ($type === 'site') {
        $stmt = $pdo->prepare("SELECT username, password_encrypted FROM user_sites WHERE id = ? AND user_id = ?");
    } else { // reminder
        $stmt = $pdo->prepare("SELECT username, password_encrypted FROM user_reminders WHERE id = ? AND user_id = ?");
    }
    
    $stmt->execute([$id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item || empty($item['password_encrypted'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontraron credenciales.']);
        exit;
    }

    $decrypted_password = decrypt_data($item['password_encrypted']);
    echo json_encode(['success' => true, 'data' => ['username' => $item['username'], 'password' => $decrypted_password]]);

} catch (Exception $e) {
    http_response_code(500);
}

ob_end_flush();
exit;