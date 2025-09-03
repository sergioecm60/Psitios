<?php
/**
 * api/login.php
 * Maneja el proceso de autenticación de usuarios.
 */
// Configurar las cabeceras para respuesta JSON y control de cache
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// bootstrap.php se encarga de iniciar la sesión y cargar dependencias
require_once '../bootstrap.php';
// Solo aceptar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Datos de entrada no válidos.']);
    exit;
}
$username = trim($data['username']);
$password = $data['password'];
try {
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    // Usar password_verify para comparar la contraseña con el hash de forma segura
    if ($user && password_verify($password, $user['password_hash'])) {
        // Contraseña correcta. Iniciar sesión.
        session_regenerate_id(true); // Previene la fijación de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        // Determinar a dónde redirigir
        $redirect_url = ($user['role'] === 'admin') ? 'admin.php' : 'panel.php';
        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
        exit;
    } else {
        // Usuario no encontrado o contraseña incorrecta
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error en login.php: " . $e->getMessage());
    http_response_code(500);
    // Para depuración, mostramos el error real. En producción, es mejor usar un mensaje genérico.
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}