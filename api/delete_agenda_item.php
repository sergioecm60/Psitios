<?php
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../bootstrap.php';
require_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no válido.']);
    exit;
}

$pdo = get_pdo_connection();
try {
    // Comprobación de seguridad: el usuario solo puede borrar sus propios recordatorios.
    $stmt = $pdo->prepare("DELETE FROM user_agenda WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Recordatorio eliminado.']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Recordatorio no encontrado o no tienes permiso.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el recordatorio.']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;