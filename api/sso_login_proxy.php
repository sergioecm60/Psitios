<?php
/**
 * /Psitios/api/sso_login_proxy.php
 *
 * Proxy de inicio de sesión para el SSO.
 * Este script actúa como un intermediario seguro.
 *
 * Flujo de trabajo:
 * 1. Recibe un token de un solo uso desde sso_pvyt.php.
 * 2. Valida el token y recupera las credenciales de la sesión de Psitios.
 * 3. Realiza una llamada cURL (servidor a servidor) al endpoint de login de 'pvytGestiones'.
 * 4. Espera una respuesta JSON de 'pvytGestiones' que contenga un token de acceso.
 * 5. Redirige el navegador del usuario a la URL base de 'pvytGestiones', pasando
 *    el token de acceso como un parámetro en la URL.
 * 6. Se asume que el frontend (SPA) de 'pvytGestiones' está preparado para
 *    recibir este token y completar el inicio de sesión.
 */
require_once '../bootstrap.php';
require_once '../config/sso_config.php';
require_auth();

/**
 * Muestra un mensaje de error estandarizado y termina la ejecución.
 * @param int $http_code Código de estado HTTP.
 * @param string $title Título del error para el usuario.
 * @param string $message Mensaje para el usuario.
 * @param ?string $log_message Mensaje detallado para el log de errores del servidor.
 */
function sso_proxy_die(int $http_code, string $title, string $message, ?string $log_message = null): void {
    if ($log_message) {
        error_log("SSO Proxy Error: " . $log_message);
    }
    http_response_code($http_code);
    // Genera una página de error amigable en lugar de un JSON.
    $error_html = <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error de SSO</title><style>body{font-family:sans-serif;background:#f9f9f9;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}.container{text-align:center;padding:2em;background:white;border:1px solid #ddd;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}h2{color:#d9534f;}</style></head><body><div class="container"><h2>❌ {$title}</h2><p>{$message}</p><p><a href="javascript:history.back()">Volver a intentarlo</a></p></div></body></html>
HTML;
    die($error_html);
}

// --- 1. Validación del token interno de Psitios ---
$sso_token = $_POST['sso_token'] ?? '';
if (empty($sso_token) || !isset($_SESSION['sso_tokens'][$sso_token])) {
    sso_proxy_die(401, 'Acceso no autorizado', 'El token de SSO es inválido o ha expirado.', "[SSO PROXY ERROR] Token de Psitios inválido, no encontrado o ya usado.");
}
error_log("[SSO PROXY INFO] Token de Psitios recibido y encontrado en sesión: " . substr($sso_token, 0, 8) . "...");

$token_data = $_SESSION['sso_tokens'][$sso_token];

// Validar expiración del token
if ($token_data['expires'] < time()) {
    unset($_SESSION['sso_tokens'][$sso_token]); // Limpiar token expirado
    sso_proxy_die(401, 'Acceso no autorizado', 'El token de SSO ha expirado. Por favor, intenta de nuevo.', "[SSO PROXY ERROR] Token de Psitios expirado.");
}

// El token es de un solo uso. Invalidarlo inmediatamente para prevenir ataques de repetición.
unset($_SESSION['sso_tokens'][$sso_token]);
error_log("[SSO PROXY INFO] Token de Psitios validado y consumido. Procediendo a llamar a pvytGestiones.");

$username = $token_data['username'];
$password = $token_data['password'];
$redirect_url = $token_data['redirect_url'];
error_log("sso_login_proxy.php: username=". $username . " redirect_url=" . $redirect_url);

// --- 2. Llamada cURL al endpoint de login de pvytGestiones ---
$ch = curl_init();
error_log("[SSO PROXY INFO] Realizando llamada cURL a: " . PVYTGESTIONES_LOGIN_URL);

$post_data = [
    'peticion' => 6,
    'usuario' => [
        'nombreUsuario' => $username,
        'password' => $password
    ]
];
error_log("[SSO PROXY INFO] Datos cURL (sin contraseña): " . json_encode(['peticion' => 6, 'usuario' => ['nombreUsuario' => $username]]));

curl_setopt($ch, CURLOPT_URL, PVYTGESTIONES_LOGIN_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de conexión
curl_setopt($ch, CURLOPT_TIMEOUT, 30);      // Timeout total de la operación

// ADVERTENCIA DE SEGURIDAD: Deshabilitar la verificación SSL solo es aceptable
// para entornos de desarrollo locales con URLs HTTP o certificados autofirmados.
// NUNCA hagas esto en producción con un endpoint HTTPS público.
if (parse_url(PVYTGESTIONES_LOGIN_URL, PHP_URL_SCHEME) === 'https' && strpos(parse_url(PVYTGESTIONES_LOGIN_URL, PHP_URL_HOST), 'localhost') === false) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

$response_body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- 3. Procesamiento de la respuesta de pvytGestiones ---

if ($curl_error) {
    sso_proxy_die(502, 'Error de Comunicación', 'No se pudo conectar con el sistema de destino.', "[SSO PROXY ERROR] cURL error: {$curl_error}");
}

if ($http_code !== 200) {
    sso_proxy_die(502, 'Error de Autenticación Externa', "El sistema de destino devolvió un error (Código: {$http_code}).", "[SSO PROXY ERROR] pvytGestiones devolvió HTTP {$http_code}. Respuesta: {$response_body}");
}

$response_data = json_decode($response_body, true);

error_log("[SSO PROXY INFO] Respuesta de pvytGestiones (HTTP {$http_code}): " . $response_body);
// Verificar si la respuesta es un JSON válido y contiene el token esperado.
if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['token']) || empty($response_data['token'])) {
    sso_proxy_die(500, 'Respuesta Inválida', 'El sistema de destino no devolvió un token de acceso válido. Podrían ser credenciales incorrectas.', "Invalid JSON or missing token from pvytGestiones. Raw Response: " . $response_body);
}

$pvyt_token = $response_data['token'];

error_log("[SSO PROXY OK] Token de pvytGestiones recibido: " . substr($pvyt_token, 0, 8) . "...");
// --- 4. Redirección final al navegador del usuario ---

// Construir la URL de redirección final, añadiendo el token como parámetro.
// Se utiliza parse_url para manejar correctamente los fragmentos (#) de la URL,
// asegurando que los parámetros de la query string se añadan antes del fragmento.
$url_parts = parse_url($redirect_url);
$query_params = [];
if (isset($url_parts['query'])) {
    parse_str($url_parts['query'], $query_params);
}
$query_params['sso_token'] = $pvyt_token;

// Reconstruir la URL base (scheme, host, path)
$final_url = ($url_parts['scheme'] ?? 'http') . '://' . ($url_parts['host'] ?? '');
if (isset($url_parts['port'])) {
    $final_url .= ':' . $url_parts['port'];
}
if (isset($url_parts['path'])) {
    $final_url .= $url_parts['path'];
}

// Añadir el nuevo query string
$final_url .= '?' . http_build_query($query_params);

// Añadir el fragmento si existía
if (isset($url_parts['fragment'])) {
    $final_url .= '#' . $url_parts['fragment'];
}

error_log("[SSO PROXY INFO] Redirigiendo al usuario a: " . $final_url);

// Redirigir al usuario. El navegador recibirá esta cabecera y cargará la nueva URL.
header('Location: ' . $final_url, true, 302);
exit;
