<?php
/**
 * install.php
 * Script de instalación interactivo para el proyecto Psitios.
 * Este script automatiza la creación de la base deatos, el usuario,
 * el archivo .env y la instalación de dependencias.
 */

const COLOR_RESET = "\033[0m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_RED = "\033[31m";
const COLOR_CYAN = "\033[36m";

// Helper function to check for terminal color support
function has_color_support() {
    // If running on a non-Windows OS, assume color support if it's a TTY.
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    // On Windows, check for specific environment variables or use the PHP 7.2+ function.
    return (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT))
        || getenv('ANSICON') !== false
        || getenv('ConEmuANSI') === 'ON'
        || getenv('TERM') === 'xterm-256color';
}

// A single global check for color support to avoid re-checking.
$supports_color = has_color_support();

function writeln($text, $color = COLOR_RESET) {
    global $supports_color;
    if ($supports_color) {
        echo $color . $text . COLOR_RESET . PHP_EOL;
    } else {
        echo $text . PHP_EOL;
    }
}

function ask($question, $default = null) {
    global $supports_color;
    $default_text = '';
    if ($default) {
        if ($supports_color) {
            $default_text = " [" . COLOR_YELLOW . $default . COLOR_RESET . "]";
        } else {
            $default_text = " [$default]";
        }
    }
    $prompt = $question . $default_text . ": ";
    $input = readline($prompt);
    return $input ?: $default;
}

function ask_secret($question) {
    echo $question . ": ";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $password = rtrim(shell_exec('powershell -Command "$p=Read-Host -AsSecureString; $b=[System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"'));
    } else {
        $oldStyle = shell_exec('stty -g');
        shell_exec('stty -echo');
        $password = rtrim(fgets(STDIN), "\n");
        shell_exec('stty ' . $oldStyle);
    }
    echo PHP_EOL;
    return $password;
}

function check_extensions() {
    $required = ['pdo_mysql', 'openssl', 'mbstring'];
    $missing = [];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    if (!empty($missing)) {
        writeln("Error: Faltan las siguientes extensiones de PHP: " . implode(', ', $missing), COLOR_RED);
        writeln("Por favor, habilítalas en tu archivo php.ini y vuelve a intentarlo.", COLOR_YELLOW);
        exit(1);
    }
}

function run_command($command, $description) {
    writeln("-> " . $description, COLOR_CYAN);
    $output = [];
    $return_var = 0;
    exec($command . ' 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        writeln("Error ejecutando el comando: " . $command, COLOR_RED);
        echo implode(PHP_EOL, $output) . PHP_EOL;
        exit(1);
    }
    writeln("   Hecho.", COLOR_GREEN);
    return true;
}

// --- INICIO DEL SCRIPT ---

system('cls || clear');
writeln("==================================================", COLOR_GREEN);
writeln("  Asistente de Instalación para el Panel Psitios  ", COLOR_GREEN);
writeln("==================================================", COLOR_GREEN);
writeln("Este script te guiará para configurar el proyecto.");

if (file_exists('.env')) {
    $overwrite = ask("El archivo .env ya existe. ¿Deseas sobrescribirlo y reinstalar? (s/n)", 'n');
    if (strtolower($overwrite) !== 's') {
        writeln("Instalación cancelada.", COLOR_YELLOW);
        exit(0);
    }
}

writeln("\n[Paso 1 de 5] Verificando requisitos del sistema...", COLOR_CYAN);
check_extensions();
if (!file_exists('composer.json')) {
    writeln("Error: No se encuentra 'composer.json'. Asegúrate de ejecutar este script desde la raíz del proyecto.", COLOR_RED);
    exit(1);
}
writeln("   Requisitos cumplidos.", COLOR_GREEN);


writeln("\n[Paso 2 de 5] Configuración de la Base de Datos", COLOR_CYAN);
writeln("Necesito credenciales de un usuario de MySQL con permisos para crear bases de datos y usuarios (ej. 'root').", COLOR_YELLOW);
$root_user = ask("Usuario root de MySQL", 'root');
$root_pass = ask_secret("Contraseña de " . $root_user);
$db_host = 'localhost';

try {
    $pdo_root = new PDO("mysql:host=$db_host", $root_user, $root_pass);
    $pdo_root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    writeln("\nError: No se pudo conectar a MySQL con las credenciales proporcionadas.", COLOR_RED);
    writeln($e->getMessage(), COLOR_RED);
    exit(1);
}

writeln("\nAhora, define los datos para la nueva base de datos de la aplicación.", COLOR_YELLOW);
$db_name = ask("Nombre de la nueva base de datos", 'secure_panel_db');
$db_user = ask("Nombre del nuevo usuario de BD", 'psitios_user');
$db_pass = ask_secret("Contraseña para " . $db_user);

if (empty($db_pass)) {
    writeln("\nError: La contraseña para el nuevo usuario no puede estar vacía.", COLOR_RED);
    exit(1);
}

try {
    writeln("-> Creando base de datos '$db_name'...", COLOR_CYAN);
    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;");

    writeln("-> Creando usuario '$db_user'...", COLOR_CYAN);
    $pdo_root->exec("CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass';");

    writeln("-> Otorgando privilegios...", COLOR_CYAN);
    $pdo_root->exec("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost';");
    $pdo_root->exec("FLUSH PRIVILEGES;");

    writeln("   Base de datos y usuario creados con éxito.", COLOR_GREEN);
} catch (PDOException $e) {
    writeln("\nError durante la configuración de la base de datos:", COLOR_RED);
    writeln($e->getMessage(), COLOR_RED);
    exit(1);
}


writeln("\n[Paso 3 de 5] Generando archivo de configuración .env", COLOR_CYAN);

$encryption_key = base64_encode(random_bytes(32));

$env_content = <<<EOT
# Archivo de configuración generado automáticamente por install.php

# Configuración de la base de datos
DB_HOST=localhost
DB_NAME=$db_name
DB_USER=$db_user
DB_PASS=$db_pass

# ¡IMPORTANTE! Clave de encriptación para datos sensibles.
# Generada automáticamente. ¡No la compartas!
ENCRYPTION_KEY=$encryption_key

EOT;

if (file_put_contents('.env', $env_content) === false) {
    writeln("Error: No se pudo escribir el archivo .env. Verifica los permisos de escritura.", COLOR_RED);
    exit(1);
}
writeln("   Archivo .env creado con éxito.", COLOR_GREEN);


writeln("\n[Paso 4 de 5] Instalando dependencias y base de datos", COLOR_CYAN);

// Instalar dependencias de Composer
run_command('composer update', 'Instalando dependencias de Composer...');

// Importar el esquema de la base de datos
$sql_file = 'db_actual/secure_panel_db.sql';
if (!file_exists($sql_file)) {
    writeln("Error: No se encuentra el archivo de esquema SQL en '$sql_file'.", COLOR_RED);
    exit(1);
}

try {
    writeln("-> Importando esquema de la base de datos...", COLOR_CYAN);
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo_app = new PDO($dsn, $db_user, $db_pass);
    $pdo_app->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql_content = file_get_contents($sql_file);
    // Eliminar comentarios y dividir en sentencias
    $sql_content = preg_replace('%/\*(?:(?!\*/).)*\*/%s', '', $sql_content); // remove multiline comments
    $sql_content = preg_replace('/^-- .*$/m', '', $sql_content); // remove single line comments
    $statements = explode(';', $sql_content);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo_app->exec($statement);
        }
    }
    writeln("   Esquema importado con éxito.", COLOR_GREEN);
} catch (PDOException $e) {
    writeln("\nError al importar el esquema de la base de datos:", COLOR_RED);
    writeln($e->getMessage(), COLOR_RED);
    exit(1);
}


writeln("\n[Paso 5 de 5] ¡Instalación completada!", COLOR_GREEN);
writeln("==================================================", COLOR_GREEN);
writeln("El proyecto Psitios ha sido instalado y configurado correctamente.");
writeln("\nPróximos pasos:", COLOR_YELLOW);
writeln("1. Asegúrate de que tu servidor web (Apache/Nginx) apunte al directorio del proyecto.");
writeln("2. Accede a la aplicación desde tu navegador.");

writeln("\nCredenciales de acceso por defecto:", COLOR_YELLOW);
writeln("  - Usuario:    " . COLOR_CYAN . "admin");
writeln("  - Contraseña: " . COLOR_CYAN . "admin");

writeln("\n¡IMPORTANTE! Por favor, cambia la contraseña del usuario 'admin' inmediatamente después de iniciar sesión.", COLOR_RED);
writeln("==================================================", COLOR_GREEN);

exit(0);