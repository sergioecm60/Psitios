<?php
/**
 * generate_key.php
 * Genera una clave de cifrado segura de 256 bits (32 bytes) para la variable ENCRYPTION_KEY en el archivo .env.
 *
 * Se generan 32 bytes aleatorios y se codifican en Base64. Esto produce una cadena
 * de 44 caracteres que, al ser decodificada, proporciona la clave de 32 bytes requerida
 * por el cifrado AES-256-CBC.
 */
$key = base64_encode(random_bytes(32));
echo "Copia esta clave y pégala en tu archivo <code>.env</code> como valor para <code>ENCRYPTION_KEY</code>:<br><br>";
echo "<strong style='font-family: monospace; background: #f0f0f0; padding: 5px; border-radius: 4px;'>" . htmlspecialchars($key) . "</strong>";
