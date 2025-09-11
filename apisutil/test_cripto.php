<?php
require_once 'bootstrap.php';

$text = "contraseña_secreta_123";
echo "<h3>Texto original:</h3> $text<br><br>";

// Encriptar
$ciphertext = encrypt_data($text);
if (!$ciphertext) {
    die("<h3 style='color:red'>❌ Error: No se pudo encriptar</h3>");
}
echo "<h3>Encriptado (base64):</h3> $ciphertext<br><br>";
echo "<h3>Longitud:</h3> " . strlen($ciphertext) . " caracteres<br><br>";

// Desencriptar
$decrypted = decrypt_data($ciphertext);
if (!$decrypted) {
    die("<h3 style='color:red'>❌ Error: No se pudo desencriptar</h3>");
}
echo "<h3>Desencriptado:</h3> $decrypted<br><br>";

if ($text === $decrypted) {
    echo "<h3 style='color:green'>✅ ¡Éxito! La encriptación funciona.</h3>";
} else {
    echo "<h3 style='color:red'>❌ Falló: Los textos no coinciden.</h3>";
}
