<?php
/**
 * index.php
 * Página de inicio de sesión para Psitios.
 */
require_once 'bootstrap.php';

// Generar un token CSRF para el formulario de login si no existe.
generate_csrf_token();

// Si el usuario ya está logueado, redirigir al panel correspondiente.
if (isset($_SESSION['user_id'])) {
    $redirect_url = ($_SESSION['user_role'] === 'admin') ? 'admin.php' : 'panel.php';
    header('Location: ' . $redirect_url);
    exit;
}
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
        <h1>Panel de Acceso Seguro</h1>
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        <div id="loginError" class="error-message login-error hidden"></div>
    </div>

    <script>
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
                    loginError.textContent = result.message || 'Error desconocido.';
                    loginError.classList.remove('hidden');
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
    </script>
</body>
</html>