<?php
/**
 * api/get_admins.php
 * Endpoint de la API para obtener una lista de todos los usuarios activos
 * con rol 'admin' o 'superadmin'.
 * Se utiliza en el panel de administración para poblar el menú desplegable
 * "Admin Asignado" al crear o editar un usuario.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();
ob_start();

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta lista, protegiendo la información.
require_auth('admin');

// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // Obtiene una conexión a la base de datos a través de la función de ayuda.
    $pdo = get_pdo_connection();
    
    // Prepara la consulta para seleccionar los campos `id` y `username` de los usuarios
    // que cumplen con los criterios de ser un administrador.
    // Criterios:
    // 1. El rol debe ser 'admin' O 'superadmin'.
    // 2. La cuenta debe estar activa (`is_active = 1`).
    // Se ordena por nombre de usuario para una presentación consistente en el frontend.
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE (role = 'admin' OR role = 'superadmin') AND is_active = 1 ORDER BY username");
    $stmt->execute();
    
    // Obtiene todos los resultados como un array de objetos asociativos.
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Envía la respuesta JSON con el indicador de éxito y la lista de administradores.
    echo json_encode(['success' => true, 'data' => $admins]);
} catch (Exception $e) {
    // Si ocurre una excepción, se registra el error para depuración
    // y se envía una respuesta de error genérica 500 al usuario.
    error_log("Error en get_admins.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al obtener la lista de administradores.']);
}

// Envía el contenido del buffer de salida (la respuesta JSON) y termina la ejecución del script.
if (ob_get_level()) ob_end_flush();
exit;