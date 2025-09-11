<?php
/**
 * Endpoint de la API para obtener una lista de todos los países disponibles.
 * Se utiliza en el panel de administración, específicamente en el formulario
 * de creación/edición de sucursales, para poblar el menú desplegable de países.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque (`bootstrap.php`), que inicia la sesión y carga
// todas las configuraciones y funciones de ayuda.
require_once '../bootstrap.php';
// `require_auth('admin')` asegura que solo los usuarios con rol 'admin' o superior
// puedan acceder a esta información.
require_auth('admin');
// Informa al cliente que la respuesta de este script será en formato JSON.
header('Content-Type: application/json; charset=utf-8');

// El bloque `try/catch` captura cualquier error inesperado durante la interacción con la base de datos.
try {
    // 1. Obtener una conexión a la base de datos.
    $pdo = get_pdo_connection();

    // 2. Ejecutar la consulta para obtener todos los países.
    // Se usa `query()` porque la consulta es estática y no contiene parámetros de usuario,
    // por lo que no hay riesgo de inyección SQL. Se ordena por nombre para una presentación consistente.
    $stmt = $pdo->query("SELECT id, name FROM countries ORDER BY name");
    
    // 3. Obtener todos los resultados como un array de objetos asociativos.
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatear los datos para la respuesta JSON (buena práctica).
    // Asegura que el ID sea un número entero.
    foreach ($countries as &$country) {
        $country['id'] = (int)$country['id'];
    }

    // 5. Enviar la respuesta exitosa.
    echo json_encode(['success' => true, 'data' => $countries]);
    // Captura cualquier excepción o error inesperado, lo registra y devuelve un error genérico 500.
} catch (Throwable $e) {
    send_json_error_and_exit(500, 'Error interno del servidor al cargar los países.', $e);
}