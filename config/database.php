<?php
/**
 * config/database.php
 * Configuraciones de la base de datos para el proyecto secure_panel.
 */

// Configuraciones de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'secure_panel_db');
define('DB_USER', 'secmpanel');
define('DB_PASS', 'Psitios2025');
define('DB_CHARSET', 'utf8mb4');

// Configuración adicional para MySQL 8.4.3 Percona
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
]);
