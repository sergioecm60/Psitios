<?php

/**
 * config/security.php
 * Contiene funciones de seguridad críticas para la aplicación.
 */

// Clave maestra para encriptar datos sensibles (como contraseñas de sitios)
define('ENCRYPTION_KEY', 'k3n9z1x8c5v6b7n4m2l1j2h3g4f5d6s7'); // ¡NUNCA la cambies!
define('ENCRYPTION_CIPHER', 'aes-256-cbc');

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
 * Encripta un valor usando AES-256-CBC.
 *
 * @param string $value Valor a encriptar.
 * @return string|false Valor encriptado en base64, o false si falla.
 */
function encrypt_data(string $value): ?string
{
    $cipher = ENCRYPTION_CIPHER;
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    
    $encrypted = openssl_encrypt($value, $cipher, $key, 0, $iv);
    if ($encrypted === false) {
        return null;
    }
    
    return base64_encode($iv . $encrypted);
}

/**
 * Desencripta un valor encriptado con encrypt_data.
 *
 * @param string $value Valor encriptado en base64.
 * @return string|false Valor desencriptado o false si falla.
 */
function decrypt_data(string $value): ?string
{
    $cipher = ENCRYPTION_CIPHER;
    $key = hash('sha256', ENCRYPTION_KEY, true);
    
    $data = base64_decode($value);
    if ($data === false) return null;
    
    $iv_length = openssl_cipher_iv_length($cipher);
    if (strlen($data) < $iv_length) return null;
    
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    $decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    if ($decrypted === false) return null;
    
    return $decrypted;
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