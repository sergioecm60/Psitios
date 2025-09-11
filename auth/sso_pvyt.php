<?php
require_once '../bootstrap.php';
require_once '../config/sso_config.php';
require_auth();

// Protección contra intentos masivos
if (!isset($_SESSION['sso_attempts'])) $_SESSION['sso_attempts'] = 0;
if ($_SESSION['sso_attempts'] >= SSO_MAX_ATTEMPTS) {
    $lockout_until = $_SESSION['sso_lockout_until'] ?? 0;
    if (time() < $lockout_until) {
        die('Demasiados intentos. Espere ' . ceil(($lockout_until - time()) / 60) . ' minutos.');
    } else {
        $_SESSION['sso_attempts'] = 0;
        unset($_SESSION['sso_lockout_until']);
    }
}

$site_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$site_id) {
    header('HTTP/1.1 400 Bad Request');
    die('ID de sitio requerido');
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
    header('HTTP/1.1 404 Not Found');
    die('Sitio no encontrado o sin permisos.');
}

$password = decrypt_data($site['password_encrypted']);
if (!$password) {
    $_SESSION['sso_attempts']++;
    if ($_SESSION['sso_attempts'] >= SSO_MAX_ATTEMPTS) {
        $_SESSION['sso_lockout_until'] = time() + SSO_LOCKOUT_TIME;
    }
    header('HTTP/1.1 500 Internal Server Error');
    die('Error de desencriptación. Verifique la configuración.');
}

$token = bin2hex(random_bytes(32));
$expires = time() + SSO_TOKEN_LIFETIME;

if (!isset($_SESSION['sso_tokens'])) {
    $_SESSION['sso_tokens'] = [];
}

// Limpiar tokens expirados
foreach ($_SESSION['sso_tokens'] as $key => $data) {
    if ($data['expires'] < time()) {
        unset($_SESSION['sso_tokens'][$key]);
    }
}

// Crear hash seguro (nunca enviar password en texto plano)
$password_hash = hash('sha256', $password . $token . SSO_SECRET_KEY);

// Guardar token en sesión
$_SESSION['sso_tokens'][$token] = [
    'username' => $site['username'],
    'password_hash' => $password_hash,
    'expires' => $expires,
    'site_name' => $site['name']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciando sesión...</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; color: white; }
        .container { text-align: center; background: rgba(0, 0, 0, 0.2); padding: 40px; border-radius: 15px; backdrop-filter: blur(10px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
        .spinner { border: 3px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top: 3px solid white; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Accediendo a <?= htmlspecialchars($site['name']) ?></h2>
        <div class="spinner"></div>
        <p id="status">Preparando inicio de sesión seguro...</p>
        
        <form id="ssoForm" method="POST" style="display: none;">
            <input type="hidden" name="sso_token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="origin" value="psitios">
        </form>
        
        <button class="btn" id="manualBtn" onclick="submitForm()">Continuar manualmente</button>
    </div>

    <script>
        function submitForm() {
            const form = document.getElementById('ssoForm');
            form.action = '<?= htmlspecialchars($_ENV['PVYTGESTIONES_BASE_URL']) ?>/auth/sso_validate.php';
            form.submit();
        }
        
        let countdown = 3;
        const statusEl = document.getElementById('status');
        
        const interval = setInterval(() => {
            if (countdown > 0) {
                statusEl.textContent = `Redirigiendo en ${countdown} segundos...`;
                countdown--;
            } else {
                statusEl.textContent = 'Redirigiendo ahora...';
                clearInterval(interval);
                submitForm();
            }
        }, 1000);
        
        setTimeout(() => {
            document.getElementById('manualBtn').style.display = 'inline-block';
        }, 10000);
    </script>
</body>
</html>