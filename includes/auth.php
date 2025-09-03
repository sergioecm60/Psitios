<?php
// /Psitios/includes/auth.php

if (!function_exists('require_auth')) {
    function require_auth(string $required_role = 'user', bool $is_api = false) {
        global $config;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            if ($is_api) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No autenticado.']);
            } else {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'index.php?error=auth');
            }
            exit;
        }

        if (!$is_api && isset($config['session']['timeout_seconds'])) {
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $config['session']['timeout_seconds'])) {
                session_unset();
                session_destroy();
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'index.php?timeout=1');
                exit;
            }
            $_SESSION['last_activity'] = time();
        }

        $user_role = $_SESSION['user_role'] ?? 'guest';

        if ($required_role === 'admin' && $user_role !== 'admin' && $user_role !== 'superadmin') {
            if ($is_api) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
            } else {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'panel.php?error=perms');
            }
            exit;
        }
    }
}