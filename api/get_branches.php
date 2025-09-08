<?php
// api/get_branches.php

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT) ?: null;
    
    if ($company_id) {
        $stmt = $pdo->prepare("
            SELECT b.id, b.name, b.province, b.company_id, c.name as company_name, co.name as country_name
            FROM branches b
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN provinces p ON b.province = p.name
            LEFT JOIN countries co ON p.country_id = co.id
            WHERE b.company_id = ?
            ORDER BY b.name ASC
        ");
        $stmt->execute([$company_id]);
    } else {
        $stmt = $pdo->query("
            SELECT b.id, b.name, b.province, b.company_id, c.name as company_name, co.name as country_name
            FROM branches b
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN provinces p ON b.province = p.name
            LEFT JOIN countries co ON p.country_id = co.id
            ORDER BY c.name ASC, b.name ASC
        ");
    }
    
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($branches as &$branch) {
        $branch['id'] = (int)$branch['id'];
        $branch['company_id'] = (int)$branch['company_id'];
    }

    echo json_encode(['success' => true, 'data' => $branches]);
} catch (Exception $e) {
    error_log("Error en get_branches.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;