<?php
/**
 * reset_admin_password.php
 * Script para resetear de forma segura la contraseña del usuario administrador.
 *
 * USO RECOMENDADO (más seguro):
 * 1. Sube este archivo a la raíz de tu proyecto en el servidor.
 * 2. Conéctate al servidor por SSH y navega al directorio del proyecto.
 * 3. Ejecuta: php reset_admin_password.php
 * 4. ¡ELIMINA ESTE ARCHIVO DEL SERVIDOR INMEDIATAMENTE DESPUÉS DE USARLO!
 */

// Cargar el entorno de la aplicación
require_once __DIR__ . '/bootstrap.php';

// --- CONFIGURACIÓN ---
$username_to_reset = 'admin';
$new_password = 'admin'; // Puedes cambiar esto a otra contraseña temporal si lo deseas
// ---------------------

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ADVERTENCIA: Este script está diseñado para ser ejecutado desde la línea de comandos (CLI).\n\n";
}

echo "Intentando resetear la contraseña para el usuario: '{$username_to_reset}'...\n";

try {
    $pdo = get_pdo_connection();
    $new_password_hash = hash_password($new_password);

    if (!$new_password_hash) {
        throw new Exception("No se pudo generar el hash de la contraseña.");
    }

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$new_password_hash, $username_to_reset]);

    if ($stmt->rowCount() > 0) {
        echo "\n[ÉXITO]\nLa contraseña para '{$username_to_reset}' ha sido reseteada a '{$new_password}'.\n";
        echo "\n==================================================================\n";
        echo "== ¡ADVERTENCIA DE SEGURIDAD! ==\n";
        echo "== Elimina este archivo (reset_admin_password.php) del servidor INMEDIATAMENTE. ==\n";
        echo "==================================================================\n";
    } else {
        echo "\n[ERROR]\nNo se encontró ningún usuario con el nombre '{$username_to_reset}'.\n";
    }
} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO]\nHa ocurrido un error: " . $e->getMessage() . "\n";
    echo "Asegúrate de que tu archivo .env esté configurado correctamente en el servidor.\n";
}