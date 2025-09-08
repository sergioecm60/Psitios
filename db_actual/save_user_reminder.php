<?php
if (ob_get_level()) ob_end_clean();
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

$action = $input['action'] ?? 'save';
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

$pdo = get_pdo_connection();

try {
    if ($action === 'toggle_complete') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de recordatorio requerido.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE user_reminders SET is_completed = NOT is_completed WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Estado actualizado.']);
        exit;
    }

    // Para 'add' y 'edit'
    $type = $input['type'] ?? '';
    $title = trim($input['title'] ?? '');
    $username = ($type === 'credential') ? trim($input['username'] ?? '') : null;
    $password = ($type === 'credential') ? ($input['password'] ?? null) : null;
    $notes = trim($input['notes'] ?? '');
    $reminder_datetime = !empty($input['reminder_datetime']) ? $input['reminder_datetime'] : null;

    if (empty($title) || !in_array($type, ['credential', 'note'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El tipo y el título son requeridos.']);
        exit;
    }

    $encrypted_password = null;
    if ($type === 'credential' && !empty($password)) {
        $encrypted_password = encrypt_data($password);
    }

    if ($id) { // Editar
        $sql = "UPDATE user_reminders SET type = ?, title = ?, username = ?, notes = ?, reminder_datetime = ?";
        $params = [$type, $title, $username, $notes, $reminder_datetime];
        
        if ($type === 'credential' && $password !== null) { // Permite borrar la contraseña enviando un string vacío
            $sql .= ", password_encrypted = ?";
            $params[] = $encrypted_password;
        }
        
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado.']);

    } else { // Agregar
        $stmt = $pdo->prepare(
            "INSERT INTO user_reminders (user_id, type, title, username, password_encrypted, notes, reminder_datetime) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $type, $title, $username, $encrypted_password, $notes, $reminder_datetime]);
        echo json_encode(['success' => true, 'message' => 'Recordatorio agregado.']);
    }
} catch (Exception $e) {
    error_log("Error en save_user_reminder.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el recordatorio.']);
}

ob_end_flush();
exit;