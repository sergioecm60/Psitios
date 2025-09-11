<?php
/**
 * /Psitios/config/sso_config.php
 *
 * Configuración centralizada para el sistema de Single Sign-On (SSO).
 * Carga las variables desde el entorno (.env) y define constantes.
 */

// Carga las variables de entorno si aún no se ha hecho.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

define('SSO_SECRET_KEY', $_ENV['SSO_SECRET_KEY'] ?? 'clave_por_defecto_insegura');
define('SSO_TOKEN_LIFETIME', 120); // 2 minutos
define('SSO_MAX_ATTEMPTS', 5);
define('SSO_LOCKOUT_TIME', 300); // 5 minutos