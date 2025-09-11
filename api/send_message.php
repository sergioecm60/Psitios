<?php
/**
 * api/send_message.php
 * Endpoint para enviar un mensaje de un usuario a otro, con una lógica de permisos
 * estricta basada en roles para asegurar la privacidad de las conversaciones.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación.
require_once __DIR__ . '/../bootstrap.php';
// 'user' permite el acceso a todos los roles autenticados (user, admin, superadmin).
require_auth('user');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// Función de ayuda para estandarizar las respuestas de error.
function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    if (ob_get_level()) ob_end_flush();
    exit;
}

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error(405, 'Método no permitido.');
}

// 2. Validar el token CSRF.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error(400, 'JSON inválido.');
}

// 4. Validar datos de entrada.
$receiver_id = filter_var($input['receiver_id'] ?? null, FILTER_VALIDATE_INT);
$message = trim($input['message'] ?? '');

if (!$receiver_id || !$message) {
    send_json_error(400, 'El destinatario y el mensaje son requeridos.');
}

// --- Lógica Principal ---
try {
    $pdo = get_pdo_connection();

    // 5. Obtener datos del remitente desde la sesión.
    $sender_id = $_SESSION['user_id'];
    $sender_role = $_SESSION['user_role'];
    $sender_department_id = $_SESSION['department_id'] ?? null;

    // 6. Lógica de Seguridad de Permisos (Role Scoping).
    if ($sender_role === 'user') {
        // Un usuario solo puede enviar mensajes a su administrador asignado.
        $stmt = $pdo->prepare("SELECT assigned_admin_id FROM users WHERE id = ?");
        $stmt->execute([$sender_id]);
        $assigned_admin_id = $stmt->fetchColumn();
        if ($receiver_id != $assigned_admin_id) {
            send_json_error(403, 'No tienes permiso para enviar mensajes a este usuario.');
        }
    } elseif ($sender_role === 'admin') {
        // Un admin solo puede enviar mensajes a usuarios de su propio departamento.
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $receiver_department_id = $stmt->fetchColumn();
        if ($receiver_department_id != $sender_department_id) {
            send_json_error(403, 'No tienes permiso para enviar mensajes a usuarios de otro departamento.');
        }
    }
    // Un 'superadmin' no tiene restricciones y puede enviar mensajes a cualquiera.

    // 7. Verificar que el usuario receptor realmente exista (como una comprobación final).
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$receiver_id]);
    if (!$stmt->fetch()) {
        send_json_error(404, 'El usuario destinatario no existe o está inactivo.');
    }

    // 8. Insertar el mensaje en la base de datos.
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$sender_id, $receiver_id, $message]);
    $new_message_id = (int)$pdo->lastInsertId();

    // 9. Enviar una respuesta exitosa con los datos del mensaje enviado.
    // Esto es útil para que el frontend actualice la UI inmediatamente.
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado.',
        'data' => [
            'id' => $new_message_id,
            'sender_id' => (int)$sender_id,
            'receiver_id' => (int)$receiver_id,
            'message' => $message,
            'created_at' => date('c'),
            'sender_username' => $_SESSION['username']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en send_message.php: " . $e->getMessage());
    send_json_error(500, 'Error interno al enviar el mensaje.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;