<?php
// Genera una clave segura de 32 caracteres y la muestra en pantalla.
$key = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(32))), 0, 32);
echo "Tu clave de encriptación segura es:<br><br>";
echo "<strong>" . htmlspecialchars($key) . "</strong>";
