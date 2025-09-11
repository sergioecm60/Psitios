<?php
require_once 'bootstrap.php';

echo "<h2>🔍 Diagnóstico del Sistema Psitios</h2>";

// 0. Verificar versión de PHP y legibilidad de .env
echo "<p><strong>Versión de PHP:</strong> " . PHP_VERSION;
if (version_compare(PHP_VERSION, '8.1', '<')) {
    echo " <span style='color:orange;'>⚠️ Advertencia: Se recomienda PHP 8.1 o superior.</span>";
} else {
    echo " ✅ OK";
}
echo "</p>";

echo "<p><strong>Archivo .env:</strong> ";
if (is_readable(__DIR__ . '/.env')) {
    echo "✅ Legible";
} else {
    echo "<span style='color:red;'>❌ No es legible. Verifique los permisos del archivo.</span>";
}
echo "</p>";

// 1. Verificar ENCRYPTION_KEY
echo "<p><strong>ENCRYPTION_KEY:</strong> ";
$key = $_ENV['ENCRYPTION_KEY'] ?? '';
if (empty($key)) {
    echo "<span style='color:red;'>❌ No definida en .env</span>";
} else {
    echo "Definida (" . strlen($key) . " caracteres). La validación real ocurre al usar la clave.";
}
echo "</p>";

// 2. Verificar sesión
echo "<p><strong>Sesión iniciada:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Sí' : '❌ No') . "</p>";

// 3. Verificar conexión a BD
try {
    $pdo = get_pdo_connection();
    echo "<p><strong>Conexión a BD:</strong> <span style='color:green;'>✅ OK</span></p>";
} catch (Exception $e) {
    echo "<p><strong>Conexión a BD:</strong> <span style='color:red;'>❌ Error:</span> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Verificar OpenSSL
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? '✅ Cargado' : '❌ No cargado') . "</p>";

// 5. Verificar password_hash
echo "<p><strong>Algoritmo Hashing (PASSWORD_ARGON2ID):</strong> " . (defined('PASSWORD_ARGON2ID') ? '✅ Soportado' : '❌ No soportado') . "</p>";

// 6. Prueba de Cifrado/Descifrado
echo "<p><strong>Prueba de Cifrado/Descifrado:</strong> ";
try {
    $test_plaintext = "Esta es una prueba de cifrado.";
    $encrypted = encrypt_data($test_plaintext);
    if ($encrypted !== null) {
        $decrypted = decrypt_data($encrypted);
        echo ($decrypted === $test_plaintext) 
            ? "<span style='color:green;'>✅ Éxito</span>" 
            : "<span style='color:red;'>❌ Falló (El texto descifrado no coincide)</span>";
    } else {
        echo "<span style='color:red;'>❌ Falló (La función de cifrado devolvió un error)</span>";
    }
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Falló con excepción:</span> " . htmlspecialchars($e->getMessage());
}
echo "</p>";

// 7. Mostrar variables de entorno sensibles
echo "<p><strong>DB_HOST:</strong> " . ($_ENV['DB_HOST'] ?? 'no definido') . "</p>";
echo "<p><strong>DB_NAME:</strong> " . ($_ENV['DB_NAME'] ?? 'no definido') . "</p>";
echo "<p><strong>DB_USER:</strong> " . ($_ENV['DB_USER'] ?? 'no definido') . "</p>";
?>