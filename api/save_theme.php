<?php
/**
 * api/save_theme.php
 * Endpoint para que un usuario guarde su preferencia de tema (light, dark, etc.).
 * Actualiza la base de datos y la sesión del usuario.
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

// --- Lógica Principal ---
try {
    // 4. Obtener y validar el tema.
    $theme = $input['theme'] ?? 'light';
    $allowed_themes = ['light', 'dark', 'blue', 'green'];
    if (!in_array($theme, $allowed_themes)) {
        send_json_error(400, 'El tema seleccionado no es válido.');
    }

    // 5. Actualizar la base de datos.
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->execute([$theme, $_SESSION['user_id']]);

    // 6. Actualizar la sesión del usuario para reflejar el cambio inmediatamente.
    $_SESSION['user_theme'] = $theme;

    echo json_encode(['success' => true, 'message' => 'Preferencia de tema guardada.']);

} catch (Exception $e) {
    error_log("Error en save_theme.php: " . $e->getMessage());
    send_json_error(500, 'Error interno al guardar el tema.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;