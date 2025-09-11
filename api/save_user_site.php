<?php
/**
 * api/save_user_site.php
 * Endpoint para guardar (crear o actualizar) un sitio en la agenda personal del usuario.
 * Utilizado por el panel de usuario.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
require_auth();

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// 2. Validar el token CSRF.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// 3. Leer y decodificar el cuerpo de la solicitud JSON.
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

// --- Lógica Principal ---
try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // 4. Obtener y validar los datos de entrada.
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim($input['name'] ?? '');
    $url = trim($input['url'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? null; // `null` si no se envía, `string` si se envía.
    $notes = trim($input['notes'] ?? '');

    if (empty($name)) send_json_error_and_exit(400, 'El nombre del sitio es requerido.');
    if (!empty($url) && !filter_var('http://' . preg_replace('#^https?://#', '', $url), FILTER_VALIDATE_URL)) {
        send_json_error_and_exit(400, 'La URL proporcionada no es válida.');
    }

    // 5. Prevenir duplicados: verificar si el usuario ya tiene un sitio con ese nombre.
    $checkSql = "SELECT id FROM user_sites WHERE name = ? AND user_id = ?";
    $checkParams = [$name, $user_id];
    if ($id) { // Si estamos editando, excluimos el ID actual de la comprobación.
        $checkSql .= " AND id != ?";
        $checkParams[] = $id;
    }
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        send_json_error_and_exit(409, 'Ya tienes un sitio guardado con este nombre.');
    }

    if ($id) {
        // --- MODO EDICIÓN ---
        $sql = "UPDATE user_sites SET name = ?, url = ?, username = ?, notes = ?";
        $params = [$name, $url, $username, $notes];

        // Solo actualiza la contraseña si se proporcionó en la solicitud.
        if ($password !== null) {
            $encrypted_password = !empty($password) ? encrypt_data($password) : null;
            $sql .= ", password_encrypted = ?";
            $params[] = $encrypted_password;
        }

        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Sitio actualizado.']);
        } else {
            send_json_error_and_exit(404, 'Sitio no encontrado o sin permisos para editar.');
        }
    } else {
        // --- MODO CREACIÓN ---
        $encrypted_password = !empty($password) ? encrypt_data($password) : null;
        $stmt = $pdo->prepare("INSERT INTO user_sites (user_id, name, url, username, password_encrypted, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $url, $username, $encrypted_password, $notes]);
        echo json_encode(['success' => true, 'message' => 'Sitio agregado.', 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno al guardar el sitio.', $e);
}