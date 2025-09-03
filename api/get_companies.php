<?php
/**
 * api/get_companies.php
 * Devuelve la lista de empresas para el selector en el modal de usuario.
 */

require_once '../bootstrap.php';
require_auth('admin'); // Solo administradores pueden acceder

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    $stmt = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC");
    $companies = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $companies
    ]);

} catch (Exception $e) {
    error_log("Error en get_companies.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar empresas'
    ]);
}