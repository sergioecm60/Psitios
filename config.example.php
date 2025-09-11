<?php
// Psitios/config.example.php
//
// Este es un archivo de ejemplo. Copia este archivo a `config.php`
// y rellena los valores correctos para tu entorno de desarrollo o producción.
// El archivo `config.php` real no debe ser subido al repositorio de Git.

return [
    // Genera una nueva clave ejecutando en tu terminal: echo base64_encode(random_bytes(32));
    'encryption_key' => 'REEMPLAZA_CON_TU_CLAVE_DE_CIFRADO',

    'database' => [
        'host' => 'localhost',
        'name' => 'secure_panel_db',
        'user' => 'tu_usuario_de_bd',
        'pass' => 'tu_contraseña_de_bd',
    ],
    'session' => [
        'timeout_seconds' => 1800, // 30 minutos
        'name' => 'SECURE_PANEL_SESSID',
    ],
];