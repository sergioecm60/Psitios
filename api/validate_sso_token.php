<?php
/**
 * /Psitios/api/validate_sso_token.php
 *
 * Endpoint de API interna para validar un token de SSO.
 * Solo accesible desde localhost.
 */

require_once '../bootstrap.php';
require_once '../config/sso_config.php';

header('Content-Type: application/json; charset=utf-8');

// --- 1. Validación de Seguridad: Solo Peticiones Internas ---
// Este script es un endpoint de servicio interno. NUNCA debe ser accesible desde fuera del servidor.
// La siguiente lista blanca de IPs asegura que solo el propio servidor pueda hacerle peticiones.
// '127.0.0.1' y '::1' son las direcciones de loopback (localhost) para IPv4 e IPv6.
// $_SERVER['SERVER_ADDR'] es la IP principal del servidor. Esto permite que el proxy (sso_login_proxy.php)
// se comunique con este validador, incluso si no usa 'localhost'.
$allowed_ips = ['127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'];
$requester_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!in_array($requester_ip, $allowed_ips)) {
    error_log("Acceso SSO denegado desde IP no permitida: " . $requester_ip);
    http_response_code(403);
    // Este mensaje es intencionadamente genérico para no dar pistas a posibles atacantes.
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo peticiones internas.']);
    exit;
}

$token = $_POST['token'] ?? '';
$requester = $_POST['requester'] ?? '';

if (!$token || $requester !== 'pvytgestiones') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token o solicitante inválido.']);
    exit;
}

try {
    if (!isset($_SESSION['sso_tokens'][$token])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token no encontrado o ya utilizado.']);
        exit;
    }

    $token_data = $_SESSION['sso_tokens'][$token];

    if ($token_data['expires'] < time()) {
        unset($_SESSION['sso_tokens'][$token]);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token expirado.']);
        exit;
    }

    // Si el token es válido, se devuelve la información y se elimina para que sea de un solo uso.
    unset($_SESSION['sso_tokens'][$token]);

    echo json_encode([
        'success' => true,
        'username' => $token_data['username'],
        'password_hash' => $token_data['password_hash'],
        'site_name' => $token_data['site_name'],
        'expires_in' => $token_data['expires'] - time()
    ]);

} catch (Exception $e) {
    error_log("Error validando token SSO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}