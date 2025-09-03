<?php
/**
 * api/get_branches.php
 * Devuelve las sucursales de una empresa específica.
 * Uso: ?company_id=1
 */

require_once '../bootstrap.php';
require_auth('admin'); // Solo administradores pueden acceder

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();

    // Obtener company_id desde GET
    $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT);
    if (!$company_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID de empresa no válido.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, name, province FROM branches WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$company_id]);
    $branches = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $branches
    ]);

} catch (Exception $e) {
    error_log("Error en get_branches.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar sucursales'
    ]);
}