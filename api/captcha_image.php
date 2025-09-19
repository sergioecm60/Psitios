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

// Limpiar cualquier salida anterior para asegurar que solo se envíe la imagen.
if (ob_get_level()) {
    ob_end_clean();
}

// Crear una instancia del generador de CAPTCHA.
$builder = new CaptchaBuilder;

// Construir el CAPTCHA con 150x40 píxeles.
$builder->build(150, 40);

// Almacenar la frase del CAPTCHA en la sesión para su validación posterior.
$_SESSION['captcha_phrase'] = $builder->getPhrase();

// Establecer la cabecera para indicar que la salida es una imagen JPEG.
header('Content-type: image/jpeg');

// Enviar la imagen al navegador.
$builder->output();

exit;

