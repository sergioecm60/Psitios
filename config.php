<?php
// Psitios/config.php

return [
    // Clave de cifrado segura. ¡Guarda este archivo con cuidado!
    // Puedes generar una nueva ejecutando en tu terminal PHP: echo base64_encode(random_bytes(32));
    'encryption_key' => 'aStwMjJpYUllZnRNVjRXS3RCdGtVQT09',

    'database' => [
        'host' => 'localhost',
        'name' => 'secure_panel_db',
        'user' => 'secmpanel',
        'pass' => 'Psitios2025',
    ],
    'session' => [
        'timeout_seconds' => 1800, // 30 minutos
        'name' => 'SECURE_PANEL_SESSID',
    ],
];