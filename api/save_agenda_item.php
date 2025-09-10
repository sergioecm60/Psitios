<?php
/**
 * api/save_agenda_item.php
 * Endpoint para guardar (crear o actualizar) un elemento en la agenda personal del usuario.
 * NOTA: Este script parece ser una versión anterior o alternativa de `save_user_reminder.php`.
 * Se recomienda unificar la lógica en un solo endpoint para evitar redundancia.
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
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];

    // 4. Obtener y validar los datos de entrada.
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim($input['title'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? null;
    $notes = trim($input['notes'] ?? '');

    if (empty($title)) {
        send_json_error(400, 'El título es requerido.');
    }

    // 5. Encriptar la contraseña si se proporciona.
    $encryptedPass = null;
    if ($password !== null) { // Permite guardar una contraseña vacía si se desea.
        $encryptedPass = encrypt_data($password);
    }

    if ($id) {
        // --- MODO EDICIÓN ---
        $sql = "UPDATE user_agenda SET title = ?, username = ?, notes = ?";
        $params = [$title, $username, $notes];

        if ($password !== null) {
            $sql .= ", password_encrypted = ?";
            $params[] = $encryptedPass;
        }

        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado.']);
        } else {
            send_json_error(404, 'Recordatorio no encontrado o sin permisos para editar.');
        }
    } else {
        // --- MODO CREACIÓN ---
        $stmt = $pdo->prepare("INSERT INTO user_agenda (user_id, title, username, password_encrypted, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $username, $encryptedPass, $notes]);
        echo json_encode(['success' => true, 'message' => 'Recordatorio guardado.', 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    error_log("Error en save_agenda_item.php: " . $e->getMessage());
    send_json_error(500, 'Error interno al guardar el recordatorio.');
}

// Envía el contenido del buffer de salida y termina la ejecución.
if (ob_get_level()) {
    ob_end_flush();
}
exit;