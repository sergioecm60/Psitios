<?php
if (ob_get_level()) ob_end_clean();
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("
        SELECT id, type, title, username, password_encrypted IS NOT NULL as has_password, password_encrypted, notes, reminder_datetime, is_completed 
        FROM user_reminders 
        WHERE user_id = ? 
        ORDER BY is_completed ASC, reminder_datetime ASC, created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
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