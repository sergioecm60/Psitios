<?php
/**
 * api/get_user_reminders.php
 * Endpoint de la API para obtener todos los recordatorios del usuario autenticado.
 * Se utiliza en la pestaña "Mi Agenda" del panel de usuario.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth()` asegura que solo los usuarios autenticados puedan acceder a sus propios recordatorios.
require_auth(); // Permite a cualquier usuario autenticado ver sus propios recordatorios

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos y el ID del usuario actual.
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // 2. Preparar y ejecutar la consulta para obtener los recordatorios del usuario.
    // - `password_encrypted IS NOT NULL as has_password`: Es una forma segura de saber si existe una
    //   contraseña sin necesidad de enviar el hash encriptado.
    // - `WHERE user_id = ?`: Cláusula de seguridad crucial que asegura que solo se obtengan
    //   los recordatorios del usuario que realiza la solicitud.
    // - `ORDER BY`: Ordena los resultados de forma lógica para la UI:
    //   1. Los no completados (`is_completed ASC`) aparecen primero.
    //   2. Luego, se ordenan por la fecha del recordatorio (`reminder_datetime ASC`).
    //   3. Finalmente, por fecha de creación para un orden consistente.
    $stmt = $pdo->prepare("
        SELECT 
            id, type, title, notes,
            password_encrypted IS NOT NULL as has_password,
            reminder_datetime, is_completed
        FROM user_reminders 
        WHERE user_id = ?
        ORDER BY is_completed ASC, reminder_datetime ASC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    // 3. Obtener todos los resultados como un array de objetos asociativos.
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($reminders as &$reminder) {
        $reminder['id'] = (int)$reminder['id'];
        $reminder['has_password'] = (bool)$reminder['has_password'];
        $reminder['is_completed'] = (bool)$reminder['is_completed'];
        $reminder['reminder_datetime'] = $reminder['reminder_datetime'] ? date('c', strtotime($reminder['reminder_datetime'])) : null;
    }

    // 5. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $reminders]);

} catch (Throwable $e) {
    // Si ocurre una excepción, se registra el error y se envía una respuesta genérica.
    send_json_error_and_exit(500, 'Error interno del servidor al cargar la agenda.', $e);
}