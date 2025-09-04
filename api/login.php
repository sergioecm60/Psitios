<?php
/**
 * api/login.php
 * API para iniciar sesión. Devuelve JSON.
 */

// Asegurar salida limpia
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    ob_end_flush();
    exit;
}

// Validar CSRF desde el header
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    ob_end_flush();
    exit;
}

// Leer y validar datos
$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos.']);
    ob_end_flush();
    exit;
}

try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            username, 
            password_hash, 
            role, 
            company_id, 
            branch_id 
        FROM users 
        WHERE username = ? AND is_active = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Iniciar sesión
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['username'] = $user['username'];

        // Decidir redirección
        $redirect = ($user['role'] === 'superadmin' || $user['role'] === 'admin') 
            ? 'admin.php' 
            : 'panel.php';

        echo json_encode([
            'success' => true,
            'redirect' => $redirect
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Usuario o contraseña incorrectos.'
        ]);
    }
} catch (Exception $e) {
    error_log("Error en login.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor.'
    ]);
}

ob_end_flush();
exit;