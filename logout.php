<?php
/**
 * /Psitios/logout.php
 *
 * Script para finalizar de forma segura la sesión de un usuario.
 * Sigue las mejores prácticas para la destrucción de sesiones en PHP.
 */

// 1. Cargar el bootstrap. Esto es crucial porque inicia la sesión (`session_start()`)
// y nos da acceso a las constantes como BASE_URL.
require_once 'bootstrap.php';

// 2. Limpiar todas las variables de la superglobal $_SESSION.
$_SESSION = array();

// 3. Invalidar la cookie de sesión en el navegador del cliente.
// Esto es un paso de seguridad importante para asegurar que la cookie se elimine
// y no pueda ser reutilizada.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir todos los datos asociados con la sesión actual en el servidor.
session_destroy();

// 5. Redirigir al usuario a la página de inicio de sesión.
// Se utiliza BASE_URL para asegurar una redirección correcta sin importar la estructura de directorios.
header('Location: ' . BASE_URL . 'index.php?logout=1');
exit;