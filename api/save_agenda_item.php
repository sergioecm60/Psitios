<?php
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$title = trim($input['title'] ?? '');
$username = $input['username'] ?? null;
$password = $input['password'] ?? null; // Password comes in plaintext
$notes = $input['notes'] ?? null;

// ✅ Encrypt the password on the server
$encryptedPass = null;
if ($password) {
    $encryptedPass = encrypt_data($password);
}

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Título es requerido']);
    exit;
}

$pdo = get_pdo_connection();
$stmt = $pdo->prepare("
    INSERT INTO user_agenda (user_id, title, username, password_encrypted, notes)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $_SESSION['user_id'],
    $title,
    $username,
    $encryptedPass,
    $notes
]);

echo json_encode(['success' => true, 'message' => 'Recordatorio guardado']);

if (ob_get_level()) {
    ob_end_flush();
}
exit;