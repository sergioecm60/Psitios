<?php
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth('admin'); // Solo admins pueden ver los departamentos
header('Content-Type: application/json');

try {
    $branch_id = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
    $pdo = get_pdo_connection();
    
    $sql = "
        SELECT d.id, d.name, d.company_id, d.branch_id, c.name as company_name, b.name as branch_name
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN branches b ON d.branch_id = b.id
    ";
    $params = [];

    if ($branch_id) {
        $sql .= " WHERE d.branch_id = ?";
        $params[] = $branch_id;
    }
    $sql .= " ORDER BY d.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $departments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar departamentos']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;