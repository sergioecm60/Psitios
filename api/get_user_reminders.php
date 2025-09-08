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
require_auth(); // Permite a cualquier usuario autenticado ver sus propios recordatorios

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
            password_encrypted IS NOT NULL as has_password,
            notes,
            reminder_datetime,
            is_completed
        FROM user_reminders 
        WHERE user_id = ?
        ORDER BY is_completed ASC, reminder_datetime ASC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $reminders]);

} catch (Exception $e) {
    error_log("Error en get_user_reminders.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar la agenda.']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;