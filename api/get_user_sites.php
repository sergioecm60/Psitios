<?php
// api/get_user_sites.php

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT svc.id as service_id, s.id as site_id, s.name, s.url, s.username, s.password_needs_update, s.notes, s.password_encrypted IS NOT NULL as has_password
        FROM sites s
        JOIN services svc ON s.id = svc.site_id
        WHERE svc.user_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$user_id]);
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sites as &$s) {
        $s['service_id'] = (int)$s['service_id'];
        $s['site_id'] = (int)$s['site_id'];
        $s['password_needs_update'] = (bool)$s['password_needs_update'];
    }

    echo json_encode([
        'success' => true,
        'data' => $sites
    ]);

} catch (Exception $e) {
    error_log("Error en get_user_sites.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar sitios']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;