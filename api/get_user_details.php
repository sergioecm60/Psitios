<?php
header('Content-Type: application/json');

// Carga centralizada de configuración, dependencias y funciones.
// Este archivo se encarga de iniciar la sesión, cargar .env, etc.
require_once '../bootstrap.php';

// Función de ayuda para verificar la autenticación y el rol.
// Asumimos que esta función está definida en bootstrap.php o functions.php
require_auth('admin');

// Obtener y validar el ID del usuario de la URL
$user_id_to_fetch = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id_to_fetch) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'ID de usuario no válido o no proporcionado.']);
    exit;
}

$pdo = get_pdo_connection();

try {
    // Consulta para obtener los detalles del usuario y el nombre del admin asignado
    $stmt = $pdo->prepare(
        "SELECT 
            u.id, 
            u.username, 
            u.role, 
            u.is_active, 
            u.created_at, 
            u.assigned_admin_id,
            a.username AS assigned_admin_name
         FROM users u
         LEFT JOIN users a ON u.assigned_admin_id = a.id
         WHERE u.id = ?"
    );
    $stmt->execute([$user_id_to_fetch]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Convertir valores numéricos a su tipo correcto para JSON
        $user['is_active'] = (bool) $user['is_active'];
        $user['assigned_admin_id'] = $user['assigned_admin_id'] ? (int) $user['assigned_admin_id'] : null;
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error al consultar la base de datos: ' . $e->getMessage()]);
}