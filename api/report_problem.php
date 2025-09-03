<?php
/**
 * api/report_problem.php
 * Permite a un usuario reportar un problema con un sitio.
 * Crea una notificación para el administrador.
 */

require_once '../bootstrap.php';
require_auth(); // Solo usuarios autenticados

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Validar CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

// Leer y validar datos
$input = json_decode(file_get_contents('php://input'), true);
$site_id = filter_var($input['site_id'] ?? null, FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!$site_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de sitio no válido.']);
    exit;
}

$pdo = get_pdo_connection();

try {
    // Verificar que el usuario tenga acceso a este sitio
    $stmt = $pdo->prepare("SELECT id FROM services WHERE user_id = ? AND site_id = ?");
    $stmt->execute([$user_id, $site_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes acceso a este sitio.']);
        exit;
    }

    // Obtener nombre del sitio
    $stmt = $pdo->prepare("SELECT name FROM sites WHERE id = ?");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch();
    $site_name = $site ? $site['name'] : 'Sitio desconocido';

    // Mensaje de notificación
    $message = "🚨 El usuario '$username' reportó un problema con el sitio '$site_name'.";

    // Insertar notificación
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, site_id, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$user_id, $site_id, $message]);

    echo json_encode([
        'success' => true,
        'message' => 'Problema reportado al administrador.'
    ]);

} catch (Exception $e) {
    error_log("Error en report_problem.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al reportar el problema.']);
}