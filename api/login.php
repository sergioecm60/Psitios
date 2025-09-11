<?php
/**
 * api/login.php
 * Endpoint de la API para la autenticación de usuarios.
 * Recibe credenciales (usuario y contraseña) a través de una solicitud POST,
 * las valida contra la base de datos y, si son correctas, inicia una sesión de usuario.
 * Devuelve una respuesta JSON indicando el éxito y la URL de redirección.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque, que inicia la sesión y carga todas las dependencias y funciones.
require_once __DIR__ . '/../bootstrap.php';

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// --- Validación de la Solicitud ---

// 1. Verificar el método HTTP. Este endpoint solo debe aceptar solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error_and_exit(405, 'Método no permitido.');
}

// 2. Validar el token CSRF para proteger contra ataques de falsificación de solicitudes.
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    send_json_error_and_exit(403, 'Token CSRF inválido.');
}

// 3. Leer, decodificar y validar los datos de entrada (JSON).
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error_and_exit(400, 'JSON inválido.');
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$captcha_answer = trim($input['captcha'] ?? '');

if (empty($username) || empty($password)) {
    send_json_error_and_exit(400, 'Usuario y contraseña son requeridos.');
}

// --- Validación del CAPTCHA ---
if (empty($captcha_answer)) {
    send_json_error_and_exit(400, 'Por favor, complete la verificación.');
}

// Compara la respuesta del usuario con la almacenada en la sesión.
if (!isset($_SESSION['captcha_answer']) || $captcha_answer != $_SESSION['captcha_answer']) {
    unset($_SESSION['captcha_answer']); // Limpiar para el próximo intento.
    send_json_error_and_exit(401, 'La respuesta de verificación es incorrecta.');
}

// Si el CAPTCHA es correcto, se limpia para que no pueda ser reutilizado.
unset($_SESSION['captcha_answer']);

// --- Lógica Principal de Autenticación ---

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // Obtiene una conexión a la base de datos.
    $pdo = get_pdo_connection();
    
    // Prepara la consulta para buscar al usuario.
    // Es crucial incluir `is_active = 1` para prevenir que usuarios desactivados inicien sesión.
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            username, 
            password_hash, 
            role, 
            company_id,
            branch_id,
            department_id
        FROM users 
        WHERE username = ? AND is_active = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica si se encontró un usuario y si la contraseña coincide con el hash almacenado.
    if ($user && password_verify($password, $user['password_hash'])) {
        // Éxito en la autenticación.
        
        // 1. Regenerar el ID de sesión para prevenir ataques de fijación de sesión.
        session_regenerate_id(true);
        
        // 2. Almacenar los datos del usuario en la sesión.
        // Estos datos se usarán en toda la aplicación para autorización y personalización.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['last_activity'] = time(); // Para gestionar el tiempo de inactividad de la sesión.

        // 3. Determinar la página de destino según el rol del usuario.
        $redirect = ($user['role'] === 'superadmin' || $user['role'] === 'admin') 
            ? 'admin.php' 
            : 'panel.php';

        // 4. Enviar la respuesta exitosa al cliente.
        echo json_encode([
            'success' => true,
            'redirect' => $redirect
        ]);
    } else {
        // Fallo en la autenticación (usuario no encontrado, inactivo o contraseña incorrecta).
        send_json_error_and_exit(401, 'Usuario o contraseña incorrectos.');
    }
} catch (Throwable $e) {
    // Si ocurre una excepción, se registra el error y se envía una respuesta genérica.
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}