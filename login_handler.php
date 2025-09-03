<?php
// /Psitios/login_handler.php
require_once __DIR__ . '/bootstrap.php'; // Carga la configuración, sesión y conexión a DB.
require_once __DIR__ . '/includes/auth.php'; // Carga las funciones de autenticación como verify_csrf_token().

// CSP nonce para scripts inline (aunque este handler es principalmente una API)
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$user = $input['username'] ?? $_POST['username'] ?? '';
$pass = $input['password'] ?? $_POST['password'] ?? '';
$csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';

if (empty($user) || empty($pass)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos.']);
    exit();
}
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit();
}

$pdo = get_database_connection($config);
$stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
$stmt->execute([$user]);
$user_data = $stmt->fetch();

if ($user_data && password_verify($pass, $user_data['password_hash'])) {
    // Verificar si el usuario está activo
    if (!$user_data['is_active']) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Este usuario ha sido desactivado. Contacte al administrador.']);
        exit();
    }
    // Login exitoso
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['last_activity'] = time();

    $relative_url = ($user_data['role'] === 'admin') ? 'admin.php' : 'panel.php';
    $redirect_url = BASE_URL . $relative_url;
    
    // Para AJAX
    if (!empty($input)) {
        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
    } else { // Para envío de formulario normal
        header('Location: ' . $redirect_url); // La URL ya es absoluta
    }
    exit();

} else {
    // Login fallido
    http_response_code(401);
    $message = 'Usuario o contraseña incorrectos.';
    if (!empty($input)) {
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        // Redirigir con mensaje de error si no es AJAX
        header('Location: ' . BASE_URL . 'index.php?error=' . urlencode($message));
    }
    exit();
}
