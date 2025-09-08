<?php
/**
 * api/get_messages.php
 * Obtiene los mensajes entre el usuario y su admin.
 */

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

    // Obtener assigned_admin_id
    $stmt = $pdo->prepare("SELECT assigned_admin_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row || !$row['assigned_admin_id']) {
        echo json_encode([
            'success' => true,
            'messages' => []
        ]);
        ob_end_flush();
        exit;
    }

    $admin_id = $row['assigned_admin_id'];

    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.message,
            m.created_at,
            m.is_read,
            u.username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marcar como leídos (solo los del admin al usuario)
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $stmt->execute([$user_id, $admin_id]);

    // Convertir tipos
    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['is_read'] = (bool)$msg['is_read'];
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    error_log("Error en get_messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar mensajes'
    ]);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;