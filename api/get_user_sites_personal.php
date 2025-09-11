<?php
/**
 * api/get_user_sites_personal.php
 * Endpoint de la API para obtener los sitios personales de un usuario.
 * Tiene dos modos de operación:
 * 1. Sin `id` en la URL: Devuelve una lista de todos los sitios personales del usuario.
 * 2. Con `id` en la URL: Devuelve los detalles de un sitio personal específico.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
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
    // 1. Obtener conexión a la BD, ID de usuario y el ID opcional del sitio.
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        // MODO 1: Obtener un sitio personal específico por su ID.
        // La cláusula `AND user_id = ?` es crucial para la seguridad, asegurando que un usuario
        // solo pueda solicitar datos de un sitio que le pertenece.
        $stmt = $pdo->prepare("SELECT id, name, url, username, notes FROM user_sites WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no se encuentra el sitio, devolver un error 404.
        if (!$site) {
            send_json_error(404, 'Sitio no encontrado o no tiene permiso para verlo.');
        }

        // Formatear los datos para asegurar tipos consistentes en la respuesta JSON.
        $site['id'] = (int)$site['id'];

        echo json_encode(['success' => true, 'data' => $site]);
    } else {
        // MODO 2: Obtener todos los sitios personales del usuario.
        $stmt = $pdo->prepare(
            "SELECT id, name, url, password_encrypted IS NOT NULL as has_password
             FROM user_sites 
             WHERE user_id = ? 
             ORDER BY name ASC"
        );
        $stmt->execute([$user_id]);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
        // Formatear los datos para asegurar tipos consistentes en la respuesta JSON.
        foreach ($sites as &$site) {
            $site['id'] = (int)$site['id'];
            // Asegurarse de que el frontend sepa si hay una contraseña para mostrar el botón "Ver".
            $site['has_password'] = (bool)$site['has_password'];
        }

        echo json_encode(['success' => true, 'data' => $sites]);
    }
} catch (Exception $e) {
    error_log("Error en get_user_sites_personal.php: " . $e->getMessage());
    send_json_error(500, 'Error al cargar los sitios personales.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;