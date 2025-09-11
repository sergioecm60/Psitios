<?php
require_once 'bootstrap.php';

echo "<h2>🔍 Diagnóstico del Sistema</h2>";

// 1. Verificar ENCRYPTION_KEY
echo "<p><strong>ENCRYPTION_KEY:</strong> ";
$key = $_ENV['ENCRYPTION_KEY'] ?? '';
echo htmlspecialchars($key);
echo " (" . strlen($key) . " caracteres)";
echo ($key === 'onnt9m0gB1bVx4V85XG5LmsG3tllvgzs') ? " ✅ Correcta" : " ❌ Incorrecta";
echo "</p>";

// 2. Verificar sesión
echo "<p><strong>Sesión iniciada:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Sí' : '❌ No') . "</p>";
echo "<p><strong>ID de sesión:</strong> " . session_id() . "</p>";

// 3. Verificar conexión a BD
try {
    $pdo = get_pdo_connection();
    echo "<p><strong>Conexión a BD:</strong> ✅ OK</p>";
} catch (Exception $e) {
    echo "<p><strong>Error de BD:</strong> ❌ " . $e->getMessage() . "</p>";
}

// 4. Verificar OpenSSL
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? '✅ Cargado' : '❌ No cargado') . "</p>";

// 5. Verificar password_hash
echo "<p><strong>PASSWORD_ARGON2ID:</strong> " . (defined('PASSWORD_ARGON2ID') ? '✅ Soportado' : '❌ No soportado') . "</p>";

// 6. Intentar desencriptar un dato de prueba
$test_ciphertext = base64_encode(random_bytes(16) . random_bytes(32)); // IV + ciphertext
$decrypted = decrypt_data($test_ciphertext);
echo "<p><strong>Prueba de desencriptación:</strong> " . ($decrypted === null ? '❌ Falló' : '✅ Éxito') . "</p>";

// 7. Mostrar variables de entorno sensibles
echo "<p><strong>DB_HOST:</strong> " . ($_ENV['DB_HOST'] ?? 'no definido') . "</p>";
echo "<p><strong>DB_NAME:</strong> " . ($_ENV['DB_NAME'] ?? 'no definido') . "</p>";
?>