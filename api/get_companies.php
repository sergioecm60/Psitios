<?php
// ===============================
// api/get_companies.php
// ===============================
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->query("SELECT * FROM companies ORDER BY name ASC");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $companies]);
} catch (Exception $e) {
    error_log("Error en get_companies.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>
