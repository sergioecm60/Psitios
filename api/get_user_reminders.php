<?php
/**
 * api/get_user_reminders.php
 * Endpoint de la API para obtener todos los recordatorios del usuario autenticado.
 * Se utiliza en la pestaña "Mi Agenda" del panel de usuario.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth()` asegura que solo los usuarios autenticados puedan acceder a sus propios recordatorios.
require_auth(); // Permite a cualquier usuario autenticado ver sus propios recordatorios

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos y el ID del usuario actual.
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // 2. Obtener parámetros de búsqueda y filtro de la URL (GET).
    $search_term = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?: '');
    $type_filter = trim(filter_input(INPUT_GET, 'type', FILTER_UNSAFE_RAW) ?: '');

    // 3. Construir la consulta SQL dinámicamente.
    $sql = "
        SELECT 
            id, type, title, username, notes, is_pinned, display_order,
            password_encrypted IS NOT NULL as has_password,
            reminder_datetime, is_completed
        FROM user_reminders 
    ";
    
    $where_clauses = ['user_id = ?'];
    $params = [$user_id];

    if (!empty($search_term)) {
        // Buscar en título, notas y nombre de usuario para una búsqueda más completa
        $where_clauses[] = '(title LIKE ? OR notes LIKE ? OR username LIKE ?)';
        $params[] = '%' . $search_term . '%';
        $params[] = '%' . $search_term . '%';
        $params[] = '%' . $search_term . '%';
    }

    if (!empty($type_filter) && in_array($type_filter, ['note', 'credential', 'phone'])) {
        $where_clauses[] = 'type = ?';
        $params[] = $type_filter;
    }

    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);

    // 4. Actualizar el ordenamiento para priorizar recordatorios fijados y el orden manual.
    // 1. Los no completados (`is_completed ASC`)
    // 2. Los fijados (`is_pinned DESC`)
    // 3. El orden manual del usuario (`display_order ASC`)
    // 4. Fecha de creación como último recurso.
    $sql .= " ORDER BY is_completed ASC, is_pinned DESC, display_order ASC, created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 5. Obtener todos los resultados como un array de objetos asociativos.
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Formatear los datos para la respuesta JSON (buena práctica).
    foreach ($reminders as &$reminder) {
        $reminder['id'] = (int)$reminder['id'];
        $reminder['is_pinned'] = (bool)$reminder['is_pinned'];
        $reminder['has_password'] = (bool)$reminder['has_password'];
        $reminder['is_completed'] = (bool)$reminder['is_completed'];
        $reminder['reminder_datetime'] = $reminder['reminder_datetime'] ? date('c', strtotime($reminder['reminder_datetime'])) : null;
    }

    // 7. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $reminders]);

} catch (Throwable $e) {
    // Si ocurre una excepción, se registra el error y se envía una respuesta genérica.
    send_json_error_and_exit(500, 'Error interno del servidor al cargar la agenda.', $e);
}