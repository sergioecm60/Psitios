<?php
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("
        SELECT id, title, username, password_encrypted, notes, created_at 
        FROM user_agenda 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar la agenda']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;