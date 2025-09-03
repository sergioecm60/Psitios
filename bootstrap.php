<?php
/**
 * bootstrap.php
 */

declare(strict_types=1);

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php'; // ← Aquí se define require_auth()

define('BASE_URL', '/Psitios/');