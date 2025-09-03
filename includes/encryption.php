<?php
// secure_panel/includes/encryption.php

/**
 * Obtiene la clave de cifrado desde la configuración, la valida y la cachea.
 * @return string La clave de cifrado decodificada (binaria).
 */
function get_encryption_key_from_config(): string {
    static $decoded_key = null;

    if ($decoded_key === null) {
        global $config;
        $key_b64 = $config['encryption_key'] ?? '';

        if (empty($key_b64) || strlen(base64_decode($key_b64)) !== 32) {
            die('Error: La clave de cifrado (encryption_key) no está definida o no es válida en config.php. Debe ser una cadena de 32 bytes codificada en base64.');
        }
        $decoded_key = base64_decode($key_b64);
    }
    return $decoded_key;
}

const CIPHER_METHOD = 'aes-256-cbc';

/**
 * Cifra un texto plano.
 * @param string $plaintext El texto a cifrar.
 * @return array|false Un array con 'ciphertext' y 'iv', o false en caso de error.
 */
function encrypt_data(string $plaintext): array|false
{
    $key = get_encryption_key_from_config();
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = random_bytes($iv_length);
    $ciphertext = openssl_encrypt($plaintext, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        return false;
    }

    return ['ciphertext' => $ciphertext, 'iv' => $iv];
}

/**
 * Descifra datos.
 * @param string $ciphertext El texto cifrado (raw binary).
 * @param string $iv El vector de inicialización (raw binary).
 * @return string|false El texto plano descifrado, o false en caso de error.
 */
function decrypt_data(string $ciphertext, string $iv): string|false
{
    $key = get_encryption_key_from_config();
    return openssl_decrypt($ciphertext, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
}
