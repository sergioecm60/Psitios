<?php
/**
 * reset_admin_password.php - Versión corregida
 */
require_once __DIR__ . '/bootstrap.php';

$username_to_reset = 'admin';
$new_password = 'admin'; // ← Cambia después

echo "Reseteando contraseña para: $username_to_reset\n";

try {
    // Asegurarse de que hash_password() exista
    if (!function_exists('hash_password')) {
        throw new Exception("Función hash_password() no encontrada. Verifica config/security.php");
    }

    $pdo = get_pdo_connection();
    $new_hash = hash_password($new_password);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$new_hash, $username_to_reset]);

    if ($stmt->rowCount()) {
        echo "[ÉXITO] Contraseña actualizada.\n";
        echo "Usuario: $username_to_reset\n";
        echo "Contraseña: $new_password\n";
        echo "\n⚠️  ¡ELIMINA ESTE ARCHIVO AHORA!\n";
    } else {
        echo "[ERROR] Usuario no encontrado o sin cambios.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}