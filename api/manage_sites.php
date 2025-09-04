<?php
// api/manage_sites.php

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

// Validar CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (($method === 'POST' || $method === 'DELETE') && !verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    ob_end_flush();
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'list') {
                $user_role = $_SESSION['user_role'];
                $user_id = $_SESSION['user_id'];

                $sql = "SELECT id, name, url, username, password_needs_update, notes, created_by FROM sites";
                $params = [];

                if ($user_role === 'admin') {
                    // Admin ve sus sitios privados y los compartidos
                    $sql .= " WHERE (created_by = ? AND visibility = 'private') OR visibility = 'shared'";
                    $params[] = $user_id;
                }
                // SuperAdmin ve todo, no necesita WHERE

                $sql .= " ORDER BY name ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Asegurar que no haya valores nulos
                foreach ($sites as &$site) {
                    $site['password_needs_update'] = !empty($site['password_needs_update']);
                    $site['id'] = (int)$site['id'];
                    $site['created_by'] = $site['created_by'] ? (int)$site['created_by'] : null;
                }

                echo json_encode(['success' => true, 'data' => $sites]);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'get') {
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    ob_end_flush();
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
                    ob_end_flush();
                    exit;
                }
                $site['password_needs_update'] = !empty($site['password_needs_update']);
                $site['id'] = (int)$site['id'];
                echo json_encode(['success' => true, 'data' => $site]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido']);
                ob_end_flush();
                exit;
            }

            $action = $input['action'] ?? null;
            $id = $input['id'] ?? null;
            $name = trim($input['name'] ?? '');
            $url = trim($input['url'] ?? '');
            $username = $input['username'] ?? null;
            $password = $input['password'] ?? null;
            $needs_update = !empty($input['password_needs_update']) ? 1 : 0;
            $notes = $input['notes'] ?? null;
            $created_by = $_SESSION['user_id'];
            $visibility = ($_SESSION['user_role'] === 'superadmin' && !empty($input['visibility'])) ? $input['visibility'] : 'private';

            if ($action === 'delete') {
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                    ob_end_flush();
                    exit;
                }
                $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Sitio eliminado']);
                exit;
            }

            if (empty($name) || empty($url)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nombre y URL son requeridos']);
                ob_end_flush();
                exit;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'URL no válida']);
                ob_end_flush();
                exit;
            }

            if ($id) {
                // Editar
                $stmt = $pdo->prepare("
                    UPDATE sites 
                    SET name = ?, url = ?, username = ?, password_needs_update = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $url, $username, $needs_update, $notes, $id]);
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
                    ob_end_flush();
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Sitio actualizado']);
            } else {
                // Crear
                $stmt = $pdo->prepare("
                    INSERT INTO sites (name, url, username, password_needs_update, notes, created_by, visibility)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $url, $username, $needs_update, $notes, $created_by, $visibility]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Sitio creado', 'id' => (int)$newId]);
            }
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                ob_end_flush();
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

if (ob_get_level()) {
    ob_end_flush();
}
exit;