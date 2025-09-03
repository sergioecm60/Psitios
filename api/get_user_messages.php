<?php
/**
 * api/get_user_messages.php
 * Devuelve los mensajes enviados por usuarios al administrador autenticado.
 */

require_once '../bootstrap.php';
require_auth('admin'); // Solo administradores

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $admin_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.message,
            m.created_at,
            u.username,
            u.id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ? 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$admin_id]);
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $messages
    ]);

} catch (Exception $e) {
    error_log("Error en get_user_messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar mensajes'
    ]);
}