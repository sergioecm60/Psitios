<?php
/**
 * /Psitios/config/sso_config.php
 *
 * Configuración centralizada para el sistema de Single Sign-On (SSO).
 * con el sistema externo 'pvytGestiones'.
 *
 * Este archivo define las constantes utilizadas para la comunicación y seguridad
 * del proceso de SSO. Se asume que bootstrap.php ya ha sido cargado y ha
 * inicializado las variables de entorno.
 */

/**
 * @const SSO_SECRET_KEY
 * Clave secreta para firmar tokens de SSO.
 * DEBE ser una cadena criptográficamente segura y coincidir con la clave
 * esperada por el sistema receptor.
 * Se recomienda encarecidamente establecerla en el archivo .env.
 * ¡El valor por defecto es solo para desarrollo y DEBE cambiarse en producción!
 */
define('SSO_SECRET_KEY', $_ENV['SSO_SECRET_KEY'] ?? '744bdc680035022bf3da0f892a2a794e63428d5474c0960384bcf45dd82df8db');

/**
 * @const SSO_TOKEN_LIFETIME
 * Tiempo de vida (en segundos) de un token de SSO.
 * Un valor corto (ej. 60-120 segundos) es más seguro, ya que limita la ventana
 * de oportunidad para un ataque de repetición.
 */
define('SSO_TOKEN_LIFETIME', 120); // 2 minutos

/**
 * @const SSO_MAX_ATTEMPTS
 * Número máximo de intentos fallidos de SSO (p. ej., por error de desencriptación)
 * antes de bloquear temporalmente la funcionalidad para un usuario.
 * Ayuda a prevenir ataques de sondeo o intentos masivos.
 */
define('SSO_MAX_ATTEMPTS', 5);

/**
 * @const SSO_LOCKOUT_TIME
 * Tiempo de bloqueo (en segundos) después de alcanzar SSO_MAX_ATTEMPTS.
 */
define('SSO_LOCKOUT_TIME', 300); // 5 minutos

/**
 * @const PVYTGESTIONES_LOGIN_URL
 * URL del endpoint de login del sistema pvytGestiones.
 * Es la dirección a la que el proxy de SSO enviará las credenciales.
 * Esta es la configuración más importante para la comunicación backend-a-backend.
 *
 * EJEMPLOS DE CONFIGURACIÓN EN .env:
 * --------------------------------------------------------------------------------
 * # 1. Para desarrollo local (ambos sistemas en la misma máquina):
 * PVYTGESTIONES_LOGIN_URL=http://localhost/pvytGestiones/php/servicios/servicioUsuarios.php
 *
 * # 2. Para acceso en la misma red local (LAN), usando la IP del servidor:
 * PVYTGESTIONES_LOGIN_URL=http://192.168.0.6/pvytGestiones/php/servicios/servicioUsuarios.php
 *
 * # 3. Para acceso desde internet, usando un dominio público y puerto:
 * PVYTGESTIONES_LOGIN_URL=http://pedrazaviajes.dyndns.org:55063/pvytGestiones/php/servicios/servicioUsuarios.php
 * --------------------------------------------------------------------------------
 */
define('PVYTGESTIONES_LOGIN_URL', $_ENV['PVYTGESTIONES_LOGIN_URL'] ?? 'http://localhost/pvytGestiones/php/servicios/servicioUsuarios.php');

/**
 * @const PVYTGESTIONES_BASE_URL
 * URL base del sistema pvytGestiones.
 * Se utiliza para construir las URL de redirección después de un login exitoso si la construcción dinámica falla.
 * NOTA: El script `sso_pvyt.php` ahora intenta construir esta URL dinámicamente,
 * lo que hace que esta constante sea principalmente un respaldo.
 *
 * EJEMPLOS DE CONFIGURACIÓN EN .env:
 * PVYTGESTIONES_BASE_URL=http://localhost/pvytGestiones
 * PVYTGESTIONES_BASE_URL=http://192.168.0.6/pvytGestiones
 * PVYTGESTIONES_BASE_URL=http://pedrazaviajes.dyndns.org:55063/pvytGestiones
 */
define('PVYTGESTIONES_BASE_URL', $_ENV['PVYTGESTIONES_BASE_URL'] ?? 'http://localhost/pvytGestiones');