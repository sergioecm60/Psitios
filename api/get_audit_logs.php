<?php
/**
 * api/get_audit_logs.php
 * Endpoint de la API para obtener los registros de la bitácora de auditoría.
 * Se utiliza en el panel de administración para mostrar un historial de acciones
 * importantes realizadas en el sistema.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a la bitácora, protegiendo esta información sensible.
require_auth('admin');

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    $pdo = get_pdo_connection();

    // Prepara la consulta para obtener los últimos 100 registros de la bitácora.
    // - LEFT JOIN con `users`: para obtener el nombre de usuario (`username`) que realizó la acción.
    // - LEFT JOIN con `services` y `sites`: para obtener el nombre del sitio (`site_name`) afectado, si lo hubiera.
    // - Se ordena por `timestamp` descendente para mostrar los eventos más recientes primero.
    // - `LIMIT 100` para evitar sobrecargar el frontend y el servidor con demasiados datos.
    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            al.user_id,
            al.service_id,
            al.action,
            al.ip_address,
            al.timestamp,
            u.username,
            s.name as site_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN services svc ON al.service_id = svc.id
        LEFT JOIN sites s ON svc.site_id = s.id
        ORDER BY al.timestamp DESC
        LIMIT 100
    ");
    $stmt->execute();
    // Obtiene todos los resultados como un array de objetos asociativos.
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Itera sobre los resultados para formatear los datos antes de enviarlos como JSON.
    foreach ($logs as &$log) {
        // Asegura que los IDs sean de tipo numérico.
        $log['id'] = (int)$log['id'];
        $log['user_id'] = $log['user_id'] ? (int)$log['user_id'] : null;
        $log['service_id'] = $log['service_id'] ? (int)$log['service_id'] : null;
        // Convierte la fecha y hora a formato ISO 8601 (RFC3339), que es el estándar para APIs
        // y es fácilmente interpretable por JavaScript.
        $log['timestamp'] = date('c', strtotime($log['timestamp']));

        // Si el nombre del sitio no se encontró (porque fue eliminado), intenta extraerlo
        // del string de la acción para una visualización más clara.
        if (empty($log['site_name']) && str_starts_with($log['action'], 'site_deleted:')) {
            $log['site_name'] = trim(str_replace('site_deleted:', '', $log['action']));
            $log['action'] = 'site_deleted';
        }
    }

    // Envía la respuesta JSON con el indicador de éxito y la lista de registros de la bitácora.
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);

} catch (Throwable $e) {
    // Si ocurre una excepción, se registra el error y se envía una respuesta genérica.
    send_json_error_and_exit(500, 'Error al cargar la bitácora.', $e);
}