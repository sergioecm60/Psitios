<?php
// /Psitios/includes/auth.php

/**
 * Verifica si el usuario está autenticado y tiene el rol requerido.
 * Finaliza la ejecución si no se cumplen los requisitos.
 *
 * @param string $required_role El rol mínimo requerido ('user' o 'admin').
 * @param bool $is_api Si es true, devuelve una respuesta JSON en caso de error. Si es false, redirige.
 */
function require_auth(string $required_role = 'user', bool $is_api = false) {
    global $config; // Acceder a la configuración global para el timeout

    // Asegurarse de que la sesión esté iniciada.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Verificar si el usuario está logueado
    if (!isset($_SESSION['user_id'])) {
        if ($is_api) {
            http_response_code(401); // Unauthorized
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No autenticado. Se requiere iniciar sesión.']);
        } else {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'index.php?error=auth');
        }
        exit;
    }

    // 2. Verificar inactividad de la sesión (solo para peticiones de página, no para APIs)
    if (!$is_api && isset($config['session']['timeout_seconds'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $config['session']['timeout_seconds'])) {
            session_unset();     // Eliminar todas las variables de sesión
            session_destroy();   // Destruir la sesión
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'index.php?timeout=1'); // Redirigir con un mensaje de timeout
            exit;
        }
        // Actualizar el tiempo de última actividad solo para páginas
        $_SESSION['last_activity'] = time();
    }

    // 3. Verificar el rol del usuario
    $user_role = $_SESSION['user_role'] ?? 'guest';

    if ($required_role === 'admin' && $user_role !== 'admin') {
        if ($is_api) {
            http_response_code(403); // Forbidden
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere rol de administrador.']);
        } else {
            // Si se requiere admin y el usuario no lo es, redirigir a su panel
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'panel.php?error=perms');
        }
        exit;
    }
}