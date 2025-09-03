<?php
/**
 * config/database.php
 * Maneja la conexión a la base de datos para el proyecto secure_panel.
 */
function get_pdo_connection() {
    $host = 'localhost';
    $db   = 'secure_panel_db'; // El nombre de tu base de datos
    $user = 'secmpanel';       // El usuario de tu base de datos
    $pass = 'Psitios2025'; // <-- ¡IMPORTANTE! Reemplaza esto con tu contraseña real
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}


