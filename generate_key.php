<?php
/**
 * generate_key.php
 * Genera una clave de cifrado segura para la variable ENCRYPTION_KEY en el archivo .env.
 *
 * Se generan 24 bytes aleatorios y se codifican en Base64. Esto produce una cadena
 * de exactamente 32 caracteres, que es la longitud requerida por el sistema de cifrado
 * (ver config/security.php) sin perder entropía.
 */
$key = base64_encode(random_bytes(24));
echo "Copia esta clave y pégala en tu archivo <code>.env</code> como valor para <code>ENCRYPTION_KEY</code>:<br><br>";
echo "<strong style='font-family: monospace; background: #f0f0f0; padding: 5px; border-radius: 4px;'>" . htmlspecialchars($key) . "</strong>";
