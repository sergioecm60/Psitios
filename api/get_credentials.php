<?php

/**
 * api/get_credentials.php
 * API segura para obtener las credenciales de un sitio,
 * verificando que el usuario tenga acceso a él.
 */

require_once '../bootstrap.php';
require_auth(); // Requiere que el usuario esté logueado

// Validar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ✅ CORRECCIÓN: Usar verify_csrf_token() y pasarle el token
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

header('Content-Type: application/json');

// Obtener los datos del cuerpo de la solicitud POST (JSON)
$input = json_decode(file_get_contents('php://input'), true);

$service_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de servicio no válido o no proporcionado.']);
    exit;
}

$pdo = get_pdo_connection();

try {
    // Unir la consulta para verificar permisos y obtener credenciales en un solo paso
    $stmt = $pdo->prepare(
        "SELECT s.username, s.password_encrypted, s.iv 
         FROM sites s
         JOIN services svc ON s.id = svc.site_id
         WHERE svc.id = ? AND svc.user_id = ?"
    );
    $stmt->execute([$service_id, $user_id]);
    $site_credentials = $stmt->fetch();

    if (!$site_credentials) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Servicio no encontrado o no tiene permiso para verlo.']);
        exit;
    }
    
    // ✅ CORRECCIÓN: Usar decrypt_data() en lugar de decrypt_password()
    $decrypted_password = '';
    if (!empty($site_credentials['password_encrypted']) && !empty($site_credentials['iv'])) {
        $decrypted_password = decrypt_data(base64_encode($site_credentials['iv'] . $site_credentials['password_encrypted']));
    }

    echo json_encode([
        'success' => true, 
        'data' => [
            'username' => $site_credentials['username'], 
            'password' => $decrypted_password ?: 'No disponible'
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_credentials.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}