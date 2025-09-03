<?php
// secure_panel/logout.php

// 1. Cargar el bootstrap para acceder a la configuración de la sesión.
require_once 'bootstrap.php';

// 2. Eliminar todas las variables de sesión.
$_SESSION = array();

// 3. Borrar la cookie de sesión.
// Esto destruirá la sesión, y no solo los datos de la sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión.
session_destroy();

// 5. Redirigir al usuario a la página de inicio de sesión.
header('Location: index.php');
exit;