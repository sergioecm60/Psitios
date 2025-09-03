<?php
/**
 * api/get_company.php
 * Devuelve los datos de una empresa específica.
 * Uso: ?id=1
 */

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    // Obtener ID desde GET
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID de empresa no válido.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, name, created_at FROM companies WHERE id = ?");
    $stmt->execute([$id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Empresa no encontrada.'
        ]);
        exit;
    }

    // Asegurar que created_at esté en formato válido
    $company['created_at'] = date('Y-m-d H:i:s', strtotime($company['created_at']));

    echo json_encode([
        'success' => true,
        'data' => $company
    ]);

} catch (Exception $e) {
    error_log("Error en get_company.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}