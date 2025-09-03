<?php
/**
 * api/manage_sites.php
 * CRUD de sitios (admin)
 */

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'list') {
                $stmt = $pdo->query("
                    SELECT 
                        id,
                        name,
                        url,
                        username,
                        password_needs_update,
                        notes,
                        created_by
                    FROM sites 
                    ORDER BY name ASC
                ");
                $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $sites]);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'get') {
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        name,
                        url,
                        username,
                        password_needs_update,
                        notes
                    FROM sites 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                $site = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$site) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $site]);
            }
            break;

        case 'POST':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $name = trim($input['name'] ?? '');
            $url = trim($input['url'] ?? '');
            $username = $input['username'] ?? null;
            $password = $input['password'] ?? null;
            $needs_update = $input['password_needs_update'] ? 1 : 0;
            $notes = $input['notes'] ?? null;

            if (empty($name) || empty($url)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nombre y URL son requeridos']);
                exit;
            }

            $created_by = $_SESSION['user_id'] ?? 1; // Por defecto admin

            if ($id) {
                // Editar
                $stmt = $pdo->prepare("
                    UPDATE sites 
                    SET name = ?, url = ?, username = ?, password_needs_update = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $url, $username, $needs_update, $notes, $id]);
                echo json_encode(['success' => true, 'message' => 'Sitio actualizado']);
            } else {
                // Crear
                $stmt = $pdo->prepare("
                    INSERT INTO sites (name, url, username, password_needs_update, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $url, $username, $needs_update, $notes, $created_by]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Sitio creado', 'id' => $newId]);
            }
            break;

        case 'DELETE':
            validate_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Sitio eliminado']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en manage_sites.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

function validate_csrf_token() {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        throw new Exception('Token CSRF inválido');
    }
}