<?php
require_once __DIR__ . '/../bootstrap.php';
require_auth(); // Requiere que el usuario esté logueado

header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    $user_id = $_SESSION['user_id'];
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        // Obtener un sitio personal específico por su ID
        $stmt = $pdo->prepare("SELECT id, name, url, username, notes FROM user_sites WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $site]);
    } else {
        // Obtener todos los sitios personales del usuario
        $stmt = $pdo->prepare(
            "SELECT id, name, url, username, (password_encrypted IS NOT NULL AND password_encrypted != '') AS has_password 
             FROM user_sites 
             WHERE user_id = ? 
             ORDER BY name ASC"
        );
        $stmt->execute([$user_id]);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sites]);
    }
} catch (Exception $e) {
    error_log("Error en get_user_sites_personal.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
?>