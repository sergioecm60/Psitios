<?php
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

$country_id = $_GET['country_id'] ?? null;

try {
    $pdo = get_pdo_connection();
    if ($country_id) {
        $stmt = $pdo->prepare("SELECT id, name FROM provinces WHERE country_id = ? ORDER BY name");
        $stmt->execute([$country_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM provinces ORDER BY name");
        $stmt->execute();
    }
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $provinces]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar provincias']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;