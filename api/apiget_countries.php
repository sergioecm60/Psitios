<?php
require_once '../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->query("SELECT id, name FROM countries ORDER BY name");
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $countries]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar países']);
}