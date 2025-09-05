<?php
// api/manage_sites.php

// 🔥 ACTIVAR ERRORES (ES LO MÁS IMPORTANTE)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

// Validar CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

try {
    // 🔍 Verificar conexión
    if (!$pdo) {
        throw new Exception('No se pudo obtener la conexión PDO');
    }

    // 🔍 Verificar usuario
    $created_by = $_SESSION['user_id'] ?? null;
    if (!$created_by) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    switch ($method) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'list') {
                $user_role = $_SESSION['user_role'];
                $user_id = $_SESSION['user_id'];

                $sql = "SELECT id, name, url, username, password_needs_update, notes, created_by FROM sites";
                $params = [];

                if ($user_role === 'admin') {
                    $sql .= " WHERE created_by = ?";
                    $params[] = $user_id;
                }

                $sql .= " ORDER BY name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($sites as &$site) {
                    $site['password_needs_update'] = !empty($site['password_needs_update']);
                    $site['id'] = (int)$site['id'];
                    $site['created_by'] = $site['created_by'] ? (int)$site['created_by'] : null;
                }

                echo json_encode(['success' => true, 'data' => $sites]);
                exit;
            }

            if (isset($_GET['action']) && $_GET['action'] === 'get') {
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }

                $user_role = $_SESSION['user_role'];
                $user_id = $_SESSION['user_id'];

                if ($user_role === 'admin') {
                    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ? AND created_by = ?");
                    $stmt->execute([$id, $user_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
                    $stmt->execute([$id]);
                }

                $site = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$site) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
                    exit;
                }

                $site['password_needs_update'] = !empty($site['password_needs_update']);
                $site['id'] = (int)$site['id'];
                echo json_encode(['success' => true, 'data' => $site]);
                exit;
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
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

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
                exit;
            }

            if (empty($url)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'La URL es requerida']);
                exit;
            }

            // ✅ Permitir IPs sin http://
            $validation_url = $url;
            if (strpos($url, '://') === false) {
                $validation_url = 'http://' . $url;
            }
            if (!filter_var($validation_url, FILTER_VALIDATE_URL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'URL no válida']);
                exit;
            }

            // ✅ Validar nombre duplicado
            $stmt = $pdo->prepare("SELECT id FROM sites WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id ?? 0]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Ya existe un sitio con ese nombre. Por favor, elija otro.']);
                exit;
            }

            // ✅ Encriptar contraseña
            $encrypted_data = null;
            if ($password !== null && $password !== '') {
                if (!function_exists('encrypt_to_parts')) {
                    error_log("ERROR: Función encrypt_to_parts() no encontrada");
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Función de encriptación no disponible']);
                    exit;
                }
                $encrypted_data = encrypt_to_parts($password);
                if (!$encrypted_data) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error al encriptar la contraseña']);
                    exit;
                }
            }

            if ($action === 'delete') {
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
                exit;
            }

            if ($id) {
                if ($user_role === 'admin') {
                    $stmt = $pdo->prepare("SELECT id FROM sites WHERE id = ? AND created_by = ?");
                    $stmt->execute([$id, $created_by]);
                    if ($stmt->rowCount() === 0) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
                        exit;
                    }
                }

                if ($encrypted_data) {
                    $stmt = $pdo->prepare("
                        UPDATE sites 
                        SET name = ?, url = ?, username = ?, password_encrypted = ?, iv = ?, password_needs_update = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $url, $username,
                        $encrypted_data['ciphertext'],
                        $encrypted_data['iv'],
                        $needs_update, $notes, $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE sites 
                        SET name = ?, url = ?, username = ?, password_needs_update = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $url, $username, $needs_update, $notes, $id]);
                }

                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
                    exit;
                }
                echo json_encode(['success' => true, 'message' => 'Sitio actualizado']);
            } else {
                if ($encrypted_data) {
                    $stmt = $pdo->prepare("
                        INSERT INTO sites (name, url, username, password_encrypted, iv, password_needs_update, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name, $url, $username,
                        $encrypted_data['ciphertext'],
                        $encrypted_data['iv'],
                        $needs_update, $notes, $created_by
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO sites (name, url, username, password_needs_update, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $url, $username, $needs_update, $notes, $created_by]);
                }

                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Sitio creado', 'id' => (int)$newId]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("ERROR EN manage_sites.php: " . $e->getMessage());
    error_log("ARCHIVO: " . $e->getFile() . " LÍNEA: " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
exit;