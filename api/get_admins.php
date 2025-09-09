<?php
require_once '../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE (role = 'admin' OR role = 'superadmin') AND is_active = 1 ORDER BY username");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $admins]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}