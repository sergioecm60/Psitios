<?php
/**
 * api/decrypt_password.php
 * Securely decrypts a string on the server.
 */

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth(); // Any authenticated user can decrypt their own data

header('Content-Type: application/json');

// CSRF check for POST
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    ob_end_flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$encrypted = $input['data'] ?? '';
$decrypted_data = '';
$success = false;

if (!empty($encrypted)) {
    try {
        $decrypted_data = decrypt_data($encrypted);
        $success = true;
    } catch (Exception $e) {
        error_log("Decryption failed in decrypt_password.php: " . $e->getMessage());
        $decrypted_data = 'Error al desencriptar.';
    }
}

echo json_encode(['success' => $success, 'data' => $decrypted_data]);

ob_end_flush();
exit;