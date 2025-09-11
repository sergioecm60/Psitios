<?php
/**
 * /Psitios/index.php - Página de inicio de sesión.
 *
 * Presenta el formulario de login y maneja la autenticación del usuario
 * de forma asíncrona a través de una llamada al endpoint `api/login.php`.
 */
require_once 'bootstrap.php';

// --- Preparación de datos y seguridad ---

// Generar un nonce para la Política de Seguridad de Contenido (CSP).
$nonce = base64_encode(random_bytes(16));
// Configuración del footer
$config = [
    'footer' => [
        'line1' => 'Soporte - Grupo Pedraza',
        'line2' => 'By Sergio Cabrera | Copyleft (C) 2025',
        'whatsapp_number' => '+5491167598452',
        'whatsapp_svg_path' => 'M12.04 2C6.58 2 2.13 6.45 2.13 12c0 1.8.48 3.47 1.34 4.94L2 22l5.25-1.38c1.4.83 3.01 1.32 4.79 1.32h.01c5.46 0 9.9-4.44 9.9-9.94 0-5.5-4.44-9.91-9.9-9.91zM18.1 16.1c-.28-.14-1.65-.82-1.9-.91-.26-.1-.45-.14-.64.14-.19.28-.72.91-.88 1.1-.16.19-.32.21-.6.07-.28-.14-1.18-.43-2.25-1.39-.83-.75-1.39-1.67-1.56-1.95-.16-.28-.02-.43.12-.57.13-.13.28-.35.42-.52.14-.17.19-.28.28-.47.1-.19.05-.37-.02-.51-.07-.14-.64-1.54-.88-2.1-.24-.56-.48-.48-.64-.48-.17 0-.36-.02-.55-.02-.19 0-.5.07-.76.35-.26.28-1 .99-1 2.4 0 1.41 1.02 2.78 1.17 2.97.14.19 2 3.17 4.93 4.32 2.94 1.15 2.94.77 3.47.72.53-.05 1.65-.68 1.88-1.33.24-.65.24-1.2.17-1.34-.07-.14-.26-.21-.55-.35z',
        'license_url' => 'license.php'
    ]
];

// Generar un token CSRF para el formulario de login si no existe.
generate_csrf_token(); // La función está en config/security.php

// Si el usuario ya está logueado, redirigir al panel correspondiente.
if (isset($_SESSION['user_id'])) {
    // La redirección se basa en el rol para dirigir al panel correcto.
    $redirect_url = ($_SESSION['user_role'] === 'superadmin' || $_SESSION['user_role'] === 'admin') 
        ? 'admin.php' 
        : 'panel.php';
    header('Location: ' . $redirect_url);
    exit;
}

// --- Headers de Seguridad ---
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self';");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Acceso Seguro</title>
    <link rel="icon" href="<?= BASE_URL ?>favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="login-page">
    <div class="login-container">
        <img src="<?= BASE_URL ?>assets/images/pedraza-logo.png" alt="Logo Grupo Pedraza" class="logo">
        <h1>Acceso a Psitios</h1>
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <!-- CAPTCHA Numérico -->
            <div class="form-group captcha-group">
                <label for="captcha" id="captcha-question-label">Verificación...</label>
                <input type="text" id="captcha" name="captcha" required autocomplete="off" placeholder="Respuesta">
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        <div id="loginError" class="error-message login-error hidden"></div>
    </div>

    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
        <div class="footer-contact-line">
            <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
            <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                    <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="<?= $config['footer']['whatsapp_svg_path'] ?>"/>
                    </svg>
                    <span><?= htmlspecialchars($config['footer']['whatsapp_number']) ?></span>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones (Licencia GNU GPL v3)</a>
    </footer>

    <script nonce="<?= $nonce ?>">
        // Función para cargar la pregunta del CAPTCHA desde la API.
        async function loadCaptcha() {
            const captchaLabel = document.getElementById('captcha-question-label');
            try {
                // Se añade un timestamp para evitar que el navegador cachee la respuesta.
                const response = await fetch('api/captcha_generator.php?' + new Date().getTime());
                const data = await response.json();
                if (data.question) {
                    captchaLabel.textContent = data.question;
                } else {
                    captchaLabel.textContent = 'No se pudo cargar la verificación.';
                }
            } catch (error) {
                captchaLabel.textContent = 'Error de conexión.';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const loginError = document.getElementById('loginError');
            const submitButton = this.querySelector('button[type="submit"]');

            loginError.classList.add('hidden');

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            submitButton.disabled = true;
            submitButton.textContent = 'Verificando...';

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Asegurarse de que la redirección sea a una URL relativa segura
                    window.location.href = result.redirect;
                } else {
                    // Si hay un error, se muestra el mensaje y se recarga el CAPTCHA.
                    loginError.textContent = result.message || 'Error desconocido.';
                    loginError.classList.remove('hidden');
                    loadCaptcha();
                    document.getElementById('captcha').value = ''; // Limpiar el campo
                }
            } catch (error) {
                console.error('Error de red o de parseo:', error);
                loginError.textContent = 'Error de conexión. Intente de nuevo.';
                loginError.classList.remove('hidden');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Iniciar Sesión';
            }
        });

        // Cargar el CAPTCHA inicial cuando la página esté lista.
        document.addEventListener('DOMContentLoaded', loadCaptcha);
    </script>
</body>
</html>