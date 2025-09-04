<?php
/**
 * api/get_audit_logs.php
 * Devuelve los registros de auditoría (audit_logs) para el panel de admin.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth('admin'); // Solo admins pueden ver la bitácora

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();

    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            al.user_id,
            al.service_id,
            al.action,
            al.ip_address,
            al.timestamp,
            u.username,
            s.name as service_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN services svc ON al.service_id = svc.id
        LEFT JOIN sites s ON svc.site_id = s.id
        ORDER BY al.timestamp DESC
        LIMIT 100
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos
    foreach ($logs as &$log) {
        $log['id'] = (int)$log['id'];
        $log['user_id'] = $log['user_id'] ? (int)$log['user_id'] : null;
        $log['service_id'] = $log['service_id'] ? (int)$log['service_id'] : null;
        $log['timestamp'] = date('c', strtotime($log['timestamp']));
    }

    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);

} catch (Exception $e) {
    error_log("Error en get_audit_logs.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar la bitácora'
    ]);
}

// Limpiar buffer
if (ob_get_level()) {
    ob_end_flush();
}
exit;