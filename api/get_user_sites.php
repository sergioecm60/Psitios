<?php
/**
 * api/get_user_sites.php
 * Endpoint de la API para obtener la lista de sitios compartidos asignados al usuario autenticado.
 * Se utiliza en el panel de usuario (panel.js) para mostrar los sitios a los que tiene acceso.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque, que inicia la sesión y carga todas las dependencias y funciones.
require_once '../bootstrap.php';
// `require_auth()` asegura que solo los usuarios autenticados puedan acceder a sus sitios.
require_auth();

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

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

    // 2. Preparar y ejecutar la consulta para obtener los sitios asignados.
    // - `JOIN services svc`: Une la tabla de sitios con la de asignaciones de servicios.
    // - `WHERE svc.user_id = ?`: Esta es la cláusula de seguridad crucial. Asegura que un usuario
    //   solo pueda ver los sitios que le han sido explícitamente asignados.
    // - `s.password_encrypted IS NOT NULL as has_password`: Es una forma segura de indicar al frontend
    //   si existe una contraseña para el sitio sin exponer ningún dato sensible.
    $stmt = $pdo->prepare("
        SELECT 
            svc.id as service_id, 
            s.id as site_id, 
            s.name, 
            s.url, 
            s.username, 
            s.password_needs_update, 
            s.notes, 
            s.password_encrypted IS NOT NULL as has_password
        FROM sites s
        JOIN services svc ON s.id = svc.site_id
        WHERE svc.user_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$user_id]);
    // 3. Obtener todos los resultados como un array de objetos asociativos.
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatear los datos para la respuesta JSON (buena práctica).
    // Asegura que los tipos de datos sean consistentes (ej. `int` y `bool`) para el cliente.
    foreach ($sites as &$site) {
        $site['service_id'] = (int)$site['service_id'];
        $site['site_id'] = (int)$site['site_id'];
        $site['password_needs_update'] = (bool)$site['password_needs_update'];
        $site['has_password'] = (bool)$site['has_password'];
    }

    // 5. Enviar la respuesta exitosa con los datos de los sitios.
    echo json_encode([
        'success' => true,
        'data' => $sites
    ]);

} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica al usuario.
    error_log("Error en get_user_sites.php: " . $e->getMessage());
    send_json_error(500, 'Error al cargar los sitios asignados.');
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) {
    ob_end_flush();
}
exit;