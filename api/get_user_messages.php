<?php
/**
 * Endpoint de la API para que un administrador obtenga todos los mensajes
 * que le han enviado los usuarios.
 * Se utiliza en la pestaña "Mensajes" del panel de administración.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta bandeja de entrada.
require_auth('admin'); // Solo administradores

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos y el ID del administrador actual.
    $pdo = get_pdo_connection();
    $admin_id = $_SESSION['user_id'];

    // 2. Preparar y ejecutar la consulta para obtener los mensajes.
    // - Se seleccionan los mensajes donde el `receiver_id` es el del administrador actual.
    // - Se une con la tabla `users` para obtener el nombre del remitente (`username`).
    // - Se ordena por fecha descendente para mostrar los más recientes primero.
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
    // 3. Obtener todos los resultados como un array de objetos asociativos.
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($messages as &$msg) {
        $msg['id'] = (int)$msg['id'];
        $msg['sender_id'] = (int)$msg['sender_id'];
        $msg['created_at'] = date('c', strtotime($msg['created_at']));
    }

    // 5. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $messages]);
    // Captura cualquier excepción o error inesperado, lo registra y devuelve un error genérico 500.
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor al cargar los mensajes.', $e);
}