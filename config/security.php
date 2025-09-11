<?php

/**
 * config/security.php
 * Contiene funciones de seguridad críticas para la aplicación.
 */

define('ENCRYPTION_CIPHER', 'aes-256-cbc');

/**
 * Obtiene la clave de cifrado desde las variables de entorno.
 *
 * @return string La clave de cifrado (debe ser de 32 bytes).
 * @throws Exception Si la clave no está configurada o no es válida.
 */
function get_encryption_key(): string
{
    static $key = null;
    if ($key === null) {
        $base64_key = $_ENV['ENCRYPTION_KEY'] ?? '';
        if (empty($base64_key)) {
            error_log('CRITICAL: ENCRYPTION_KEY no está definida en .env.');
            throw new Exception('La clave de cifrado no está configurada correctamente. Contacte al administrador.');
        }

        // Decodificar la clave de Base64 a su forma binaria.
        $key = base64_decode($base64_key, true);

        // Validar que la clave decodificada tenga exactamente 32 bytes (256 bits).
        if ($key === false || mb_strlen($key, '8bit') !== 32) {
            // En un entorno real, esto debería solo loguear el error y no exponer detalles.
            error_log('CRITICAL: ENCRYPTION_KEY en .env debe ser una cadena codificada en base64 que resulte en 32 bytes de datos binarios.');
            throw new Exception('La clave de cifrado no está configurada correctamente. Contacte al administrador.');
        }
    }
    return $key;
}


/**
 * Genera un hash seguro de una contraseña (para usuarios del panel).
 *
 * @param string $password Contraseña en texto plano.
 * @return string Hash de la contraseña.
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verifica una contraseña contra su hash.
 *
 * @param string $password Contraseña en texto plano.
 * @param string $hash Hash almacenado.
 * @return bool True si coincide.
 */
function verify_password(string $password, string $hash): bool
{
    if (empty($hash)) return false;
    return password_verify($password, $hash);
}

/**
 * Cifra un texto plano y devuelve el ciphertext y el IV como un array.
 * Diseñado para almacenar en columnas de base de datos separadas.
 *
 * @param string $plaintext El texto a cifrar.
 * @return array|null Un array con ['ciphertext' => ..., 'iv' => ...], o null en caso de error.
 */
function encrypt_to_parts(string $plaintext): ?array
{
    try {
        $key = get_encryption_key();
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
        $iv = random_bytes($iv_length);
        $ciphertext = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return null;
        }

        return ['ciphertext' => $ciphertext, 'iv' => $iv];
    } catch (Exception $e) {
        error_log("Error de encriptación: " . $e->getMessage());
        return null;
    }
}

/**
 * Descifra datos almacenados como partes separadas (ciphertext e IV).
 *
 * @param string $ciphertext El texto cifrado (raw binary).
 * @param string $iv El vector de inicialización (raw binary).
 * @return string|null El texto plano descifrado, o null en caso de error.
 */
function decrypt_from_parts(string $ciphertext, string $iv): ?string
{
    try {
        $key = get_encryption_key();
        $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? null : $decrypted;
    } catch (Exception $e) {
        error_log("Error de desencriptación: " . $e->getMessage());
        return null;
    }
}

/**
 * Cifra un texto plano y devuelve una única cadena base64 que contiene el IV y el ciphertext.
 * Ideal para almacenar en una sola columna de texto.
 *
 * @param string $plaintext El texto a cifrar.
 * @return string|null La cadena cifrada en base64, o null en caso de error.
 */
function encrypt_data(string $plaintext): ?string
{
    try {
        $key = get_encryption_key();
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
        $iv = random_bytes($iv_length);
        $ciphertext = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return null;
        }

        // Concatenar IV y ciphertext, y luego codificar en base64
        return base64_encode($iv . $ciphertext);
    } catch (Exception $e) {
        error_log("Error de encriptación (encrypt_data): " . $e->getMessage());
        return null;
    }
}

/**
 * Descifra una cadena base64 que contiene el IV y el ciphertext.
 *
 * @param string $base64_string La cadena cifrada en base64.
 * @return string|null El texto plano descifrado, o null en caso de error.
 */
function decrypt_data(string $base64_string): ?string
{
    try {
        $key = get_encryption_key();
        $decoded = base64_decode($base64_string, true);
        if ($decoded === false) {
            return null;
        }

        $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
        if (mb_strlen($decoded, '8bit') < $iv_length) {
            return null; // Datos corruptos o muy cortos
        }
        $iv = substr($decoded, 0, $iv_length);
        $ciphertext = substr($decoded, $iv_length);

        if (empty($ciphertext)) {
            return null; // No hay ciphertext para desencriptar
        }

        $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? null : $decrypted;
    } catch (Exception $e) {
        error_log("Error de desencriptación (decrypt_data): " . $e->getMessage());
        return null;
    }
}

/**
 * Genera un token CSRF.
 *
 * @return string Token CSRF.
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF.
 *
 * @param string $token Token recibido.
 * @return bool True si es válido.
 */
function verify_csrf_token(string $token): bool
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}