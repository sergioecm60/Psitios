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

// Verificación de la extensión GD, necesaria para la creación de imágenes.
if (!extension_loaded('gd')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die("Error de Configuración: La extensión 'GD' de PHP no está habilitada.\n\nEsta extensión es necesaria para crear imágenes. Descomente la línea 'extension=gd' en su archivo 'php.ini' y reinicie el servidor web.");
}

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

try {
    // Limpiar cualquier salida anterior para asegurar que solo se envíe la imagen.
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Crear un generador de frases más legibles (5 caracteres, sin letras ambiguas).
    $phraseBuilder = new PhraseBuilder(5, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
    
    // Crear una instancia del generador de CAPTCHA con la frase personalizada.
    $builder = new CaptchaBuilder($phraseBuilder->build());

    // Configurar el CAPTCHA para ser más legible y amigable.
    $builder->setDistortion(false); // Desactivar la distorsión ondulada.
    $builder->setMaxBehindLines(0); // Eliminar líneas de ruido de fondo.
    $builder->setMaxFrontLines(0);  // Eliminar líneas de ruido frontales.
    $builder->setBackgroundColor(245, 245, 245); // Usar un fondo gris muy claro.
    $builder->setTextColor(50, 50, 50); // Usar un color de texto oscuro para alto contraste.
    
    // Construir la imagen final del CAPTCHA.
    $builder->build(150, 40, null);

    // Almacenar la frase del CAPTCHA en la sesión para su validación posterior.
    $_SESSION['captcha_phrase'] = $builder->getPhrase();

    // Enviar la imagen JPEG al navegador.
    header('Content-type: image/jpeg');
    $builder->output();
} catch (Throwable $e) {
    // Capturar cualquier otro error inesperado y mostrar un mensaje claro.
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    error_log("Error en captcha_image.php: " . $e->getMessage());
    die("Error al generar la imagen CAPTCHA.\n\nCausa probable: La extensión 'GD' de PHP no está habilitada en tu servidor.\nPor favor, verifica tu archivo 'php.ini' y asegúrate de que la línea ';extension=gd' esté descomentada (sin el ';').\n\nDetalle técnico: " . $e->getMessage());
}

exit;
