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

$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$name = trim($input['name'] ?? '');
$url = trim($input['url'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? null;
$notes = trim($input['notes'] ?? '');

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre del sitio es requerido.']);
    exit;
}

$encrypted_password = null;
if (!empty($password)) {
    $encrypted_password = encrypt_data($password);
}

$pdo = get_pdo_connection();

try {
    if ($id) { // Editar
        $sql = "UPDATE user_sites SET name = ?, url = ?, username = ?, notes = ?";
        $params = [$name, $url, $username, $notes];
        
        if ($encrypted_password !== null) {
            $sql .= ", password_encrypted = ?";
            $params[] = $encrypted_password;
        }
        
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Sitio actualizado.']);

    } else { // Agregar
        $stmt = $pdo->prepare(
            "INSERT INTO user_sites (user_id, name, url, username, password_encrypted, notes) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $name, $url, $username, $encrypted_password, $notes]);
        echo json_encode(['success' => true, 'message' => 'Sitio agregado.']);
    }
} catch (Exception $e) {
    error_log("Error en save_user_site.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el sitio.']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;