<?php
/**
 * api/captcha_image.php
 * Genera y muestra una imagen CAPTCHA usando gregwar/captcha.
 * Almacena la respuesta correcta en la sesión.
 */

require_once __DIR__ . '/../bootstrap.php';

// Verificación de la existencia de la clase para una depuración más clara.
if (!class_exists('Gregwar\Captcha\CaptchaBuilder')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Error Crítico: La librería para generar CAPTCHA no se encuentra.\n\nPor favor, ejecute 'composer update' en la terminal desde la raíz del proyecto (C:\\laragon\\www\\Psitios) para instalar las dependencias necesarias.");
}

use Gregwar\Captcha\CaptchaBuilder;

try {
    // Limpiar cualquier salida anterior para asegurar que solo se envíe la imagen.
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Crear una instancia del generador de CAPTCHA y construir la imagen.
    // Esto puede fallar si falta la extensión GD de PHP.
    $builder = new CaptchaBuilder;
    $builder->build(150, 40);

    // Almacenar la frase del CAPTCHA en la sesión para su validación posterior.
    $_SESSION['captcha_phrase'] = $builder->getPhrase();

    // Enviar la imagen al navegador.
    header('Content-type: image/jpeg');
    $builder->output();
} catch (Throwable $e) {
    // Capturar cualquier error (ej. falta de extensión GD) y mostrar un mensaje claro.
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    error_log("Error en captcha_image.php: " . $e->getMessage());
    die("Error al generar la imagen CAPTCHA.\n\nCausa probable: La extensión 'GD' de PHP no está habilitada en tu servidor.\nPor favor, verifica tu archivo 'php.ini' y asegúrate de que la línea ';extension=gd' esté descomentada (sin el ';').\n\nDetalle técnico: " . $e->getMessage());
}

exit;
