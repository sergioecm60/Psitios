<?php
/**
 * /Psitios/auth/sso_pvyt.php
 *
 * Inicia el proceso de Single Sign-On (SSO) hacia el sistema 'pvytGestiones'.
 *
 * Flujo de trabajo:
 * 1. Valida que el usuario actual de Psitios tenga permisos para el sitio solicitado.
 * 2. Descifra las credenciales del sitio almacenadas en la base de datos.
 * 3. Genera un token de un solo uso (one-time token) con un tiempo de vida corto.
 * 4. Almacena las credenciales descifradas en la sesión del servidor, asociadas a este token.
 * 5. Muestra una página de "cargando" al usuario y auto-envía un formulario POST
 *    hacia el proxy interno (`sso_login_proxy.php`) con el token.
 * 6. El proxy se encargará de completar el login en el sistema externo de forma segura.
 */
require_once '../bootstrap.php';
require_once '../config/sso_config.php';
require_auth();

/**
 * Muestra un mensaje de error estandarizado y termina la ejecución.
 * @param int $http_code Código de estado HTTP (ej. 400, 403, 404, 500).
 * @param string $title Título del error.
 * @param string $message Mensaje para el usuario.
 */
function sso_die(int $http_code, string $title, string $message): void {
    http_response_code($http_code);
    $error_html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de SSO</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f7f9; margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; color: #333; }
        .container { text-align: center; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); max-width: 450px; }
        h2 { color: #dc3545; }
        a { color: #007bff; }
    </style>
</head>
<body><div class="container"><h2>❌ {$title}</h2><p>{$message}</p><p><a href="javascript:history.back()">Volver atrás</a></p></div></body>
</html>
HTML;
    die($error_html);
}

// --- 1. Protección contra intentos masivos (Rate Limiting) ---
if (!isset($_SESSION['sso_attempts'])) {
    $_SESSION['sso_attempts'] = 0;
}

if ($_SESSION['sso_attempts'] >= SSO_MAX_ATTEMPTS && isset($_SESSION['sso_lockout_until']) && time() < $_SESSION['sso_lockout_until']) {
    $wait_time = ceil(($_SESSION['sso_lockout_until'] - time()) / 60);
    sso_die(429, 'Acceso Bloqueado Temporalmente', "Demasiados intentos fallidos. Por favor, espere {$wait_time} minutos.");
}

// --- 2. Validación de Entrada ---
$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    sso_die(400, 'Solicitud Incorrecta', 'El ID del sitio es requerido para iniciar el SSO.');
}

$pdo = get_pdo_connection();
$stmt = $pdo->prepare("
    SELECT us.name, us.username, us.password_encrypted 
    FROM user_sites us 
    WHERE us.id = ? AND us.user_id = ?
");
$stmt->execute([$site_id, $_SESSION['user_id']]);
$site = $stmt->fetch();

if (!$site) {
    sso_die(404, 'Acceso Denegado', 'Sitio no encontrado o no tienes permisos para acceder a él.');
}

// --- 3. Descifrado de Credenciales ---
$password = decrypt_data($site['password_encrypted']);
if (!$password) {
    $_SESSION['sso_attempts']++;
    if ($_SESSION['sso_attempts'] >= SSO_MAX_ATTEMPTS) {
        $_SESSION['sso_lockout_until'] = time() + SSO_LOCKOUT_TIME;
    }
    sso_die(500, 'Error de Configuración', 'No se pudieron descifrar las credenciales. Contacta al administrador.');
}

// Si la desencriptación fue exitosa, reiniciamos el contador de intentos.
$_SESSION['sso_attempts'] = 0;
unset($_SESSION['sso_lockout_until']);

// --- 4. Generación de Token de SSO ---
$token = bin2hex(random_bytes(32));
$expires = time() + SSO_TOKEN_LIFETIME;

// Limpiar tokens expirados para mantener la sesión limpia.
if (!isset($_SESSION['sso_tokens'])) {
    $_SESSION['sso_tokens'] = [];
}
foreach ($_SESSION['sso_tokens'] as $key => $data) {
    if ($data['expires'] < time()) {
        unset($_SESSION['sso_tokens'][$key]);
    }
}

// ADVERTENCIA DE SEGURIDAD: Se almacena la contraseña en texto plano en la sesión.
// Aunque es por un tiempo muy corto (SSO_TOKEN_LIFETIME), sigue siendo un riesgo.
// Una futura mejora sería evitar este paso si es posible.
$_SESSION['sso_tokens'][$token] = [
    'username' => $site['username'],
    'password' => $password,
    'expires' => $expires,
    'site_name' => $site['name'],
    'redirect_url' => rtrim(PVYTGESTIONES_BASE_URL, '/') . '/#/' // URL final a la que el proxy redirigirá.
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciando sesión...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f7f9; margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; color: #333; }
        .container { text-align: center; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); max-width: 400px; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top-color: #007bff; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; display: none; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Accediendo a <?= htmlspecialchars($site['name']) ?></h2>
        <div class="spinner"></div>
        <p id="status">Preparando inicio de sesión seguro...</p>
        
        <!-- Este formulario se enviará automáticamente al proxy de SSO interno -->
        <form id="ssoForm" action="<?= htmlspecialchars(BASE_URL . 'api/sso_login_proxy.php') ?>" method="POST" style="display: none;">
            <input type="hidden" name="sso_token" value="<?= htmlspecialchars($token) ?>">
        </form>
        
        <a href="#" class="btn" id="manualBtn" onclick="event.preventDefault(); submitForm();">Continuar manualmente</a>
    </div>

    <script>
        function submitForm() {
            // Desactiva el botón para evitar envíos múltiples
            document.getElementById('manualBtn').style.pointerEvents = 'none';
            document.getElementById('status').textContent = 'Enviando...';
            const form = document.getElementById('ssoForm');
            form.submit();
        }
        
        let countdown = 3;
        const statusEl = document.getElementById('status');
        
        const interval = setInterval(() => {
            if (countdown > 0) {
                statusEl.textContent = `Iniciando sesión en ${countdown}...`;
                countdown--;
            } else {
                statusEl.textContent = 'Casi listo...';
                clearInterval(interval);
                submitForm();
            }
        }, 1000);
        
        // Si algo falla, muestra un botón para que el usuario continúe manualmente.
        setTimeout(() => {
            statusEl.textContent = 'La redirección automática parece estar tardando.';
            document.getElementById('manualBtn').style.display = 'inline-block';
        }, 10000);
    </script>
</body>
</html>