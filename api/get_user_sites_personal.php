<?php
/**
 * api/get_user_sites_personal.php
 * Devuelve los sitios personales creados por el usuario.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth(); // Cualquier usuario autenticado puede ver sus propios sitios

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT id, name, url, username, password_encrypted IS NOT NULL as has_password, notes 
        FROM user_sites 
        WHERE user_id = ?
        ORDER BY name ASC
    ");
    $stmt->execute([$user_id]);
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $sites]);

} catch (Exception $e) {
    error_log("Error en get_user_sites_personal.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar tus sitios personales.']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;