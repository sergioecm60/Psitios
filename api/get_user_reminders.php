<?php
/**
 * api/get_user_reminders.php
 * Devuelve todos los recordatorios del usuario actual.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth('user'); // Solo usuarios autenticados

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT 
            id,
            type,
            title,
            username,
            password_encrypted,
            notes,
            reminder_datetime,
            is_completed,
            created_at
        FROM user_reminders 
        WHERE user_id = ?
        ORDER BY is_completed, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $reminders]);

} catch (Exception $e) {
    error_log("Error en get_user_reminders.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;