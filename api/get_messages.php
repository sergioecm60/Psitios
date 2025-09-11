<?php
/**
 * api/get_messages.php
 * Endpoint de la API para que un usuario obtenga el historial de mensajes
 * de la conversación con su administrador asignado.
 * También marca los mensajes recibidos como leídos.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth()` asegura que solo los usuarios autenticados puedan acceder.
require_auth();

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos y el ID del usuario actual.
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // 2. Obtener el ID del administrador asignado al usuario actual.
    // Esto es necesario para saber con quién es la conversación.
    $stmt = $pdo->prepare("SELECT assigned_admin_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el usuario no tiene un administrador asignado, no hay conversación que mostrar.
    // Se devuelve una respuesta exitosa con una lista de mensajes vacía.
    if (!$user_data || !$user_data['assigned_admin_id']) {
        echo json_encode(['success' => true, 'data' => []]);
        if (ob_get_level()) ob_end_flush();
        exit; // Termina la ejecución aquí.
    }

    $admin_id = $user_data['assigned_admin_id'];

    // 3. Obtener todos los mensajes de la conversación entre el usuario y su administrador.
    // La cláusula WHERE busca mensajes en ambas direcciones:
    // - (sender = usuario AND receiver = admin)
    // - (sender = admin AND receiver = usuario)
    // Se ordena por fecha de creación ascendente para mostrar el chat en orden cronológico.
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.message,
            m.created_at,
            m.is_read,
            u.username AS sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Marcar como leídos todos los mensajes que el usuario ha recibido del admin.
    // Esto se hace después de obtener los mensajes para que el estado `is_read` en la respuesta
    // refleje el estado *antes* de que el usuario los viera.
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $stmt->execute([$user_id, $admin_id]);

    // 5. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['is_read'] = (bool)$msg['is_read'];
        $msg['created_at'] = date('c', strtotime($msg['created_at']));
    }

    // 6. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $messages]);

} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_messages.php: " . $e->getMessage());
    send_json_error(500, 'Error interno del servidor al cargar los mensajes.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;