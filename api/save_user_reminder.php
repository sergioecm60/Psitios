<?php
/**
 * api/save_user_reminder.php
 * Endpoint para gestionar los recordatorios personales de un usuario.
 * Permite crear, editar y marcar como completado un recordatorio.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) ob_end_clean();

// Carga el archivo de arranque y requiere autenticación.
require_once '../bootstrap.php';
require_auth();

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json');

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
    $action = $input['action'] ?? null;

    switch ($action) { // El caso 'default' ahora maneja la lógica de 'add' y 'edit'.
        case 'toggle_complete':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) send_json_error_and_exit(400, 'ID de recordatorio requerido.');

            $stmt = $pdo->prepare("UPDATE user_reminders SET is_completed = NOT is_completed WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Estado actualizado.']);
            } else {
                send_json_error_and_exit(404, 'Recordatorio no encontrado o sin permisos.');
            }
            break;

        default: // Maneja 'add' y 'edit' basándose en la presencia de un ID.
            // Validación de campos
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $type = $input['type'] ?? '';
            $title = trim($input['title'] ?? '');
            $username = ($type === 'credential') ? trim($input['username'] ?? '') : null;
            $password = ($type === 'credential') ? ($input['password'] ?? null) : null;
            $notes = trim($input['notes'] ?? '');
            $reminder_datetime = !empty($input['reminder_datetime']) ? date('Y-m-d H:i:s', strtotime($input['reminder_datetime'])) : null;

            if (empty($title) || !in_array($type, ['credential', 'note', 'phone'])) {
                send_json_error_and_exit(400, 'El tipo y el título son requeridos.');
            }

            // Lógica específica para el tipo 'phone'
            // Si el tipo es 'phone', el número de teléfono viene en su propio campo.
            // Lo asignamos a la variable $notes para guardarlo en la columna correcta de la BD.
            if ($type === 'phone') {
                $phone_number = trim($input['phone'] ?? '');
                if (empty($phone_number)) {
                    send_json_error_and_exit(400, 'El número de teléfono es obligatorio.');
                }
                // Sobrescribimos la variable $notes con el número de teléfono.
                $notes = $phone_number;
            }

            // Prevenir duplicados
            $checkSql = "SELECT id FROM user_reminders WHERE title = ? AND user_id = ?";
            $checkParams = [$title, $user_id];
            if ($id) { // Si estamos editando, excluimos el ID actual de la comprobación.
                $checkSql .= " AND id != ?";
                $checkParams[] = $id;
            }
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute($checkParams);
            if ($stmt->fetch()) {
                send_json_error_and_exit(409, 'Ya tienes un recordatorio con este título.');
            }

            if (!$id) { // Modo Creación (no hay ID)
                $encrypted_password = ($type === 'credential' && !empty($password)) ? encrypt_data($password) : null;
                $stmt = $pdo->prepare("INSERT INTO user_reminders (user_id, type, title, username, password_encrypted, notes, reminder_datetime) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $type, $title, $username, $encrypted_password, $notes, $reminder_datetime]);
                echo json_encode(['success' => true, 'message' => 'Recordatorio agregado.', 'id' => (int)$pdo->lastInsertId()]);
            } else { // Modo Edición (hay ID)
                $sql = "UPDATE user_reminders SET type = ?, title = ?, username = ?, notes = ?, reminder_datetime = ?";
                $params = [$type, $title, $username, $notes, $reminder_datetime];

                if ($type === 'credential' && $password !== null) {
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
                    echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado.']);
                } else {
                    // Para mejorar la experiencia, verificamos si el registro existe.
                    // Si existe, significa que no se hicieron cambios. Esto no es un error.
                    $check_stmt = $pdo->prepare("SELECT id FROM user_reminders WHERE id = ? AND user_id = ?");
                    $check_stmt->execute([$id, $user_id]);
                    if ($check_stmt->fetch()) {
                        echo json_encode(['success' => true, 'message' => 'No se realizaron cambios.']);
                    } else {
                        send_json_error_and_exit(404, 'Recordatorio no encontrado.');
                    }
                }
            }
            break;
    }
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno al guardar el recordatorio.', $e);
}