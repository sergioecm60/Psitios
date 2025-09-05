<?php
// api/manage_sites.php

// 🔥 PRIMERA LÍNEA: Iniciar buffer
ob_start();

// 🔥 Mostrar errores (solo en desarrollo)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 🔥 Cargar dependencias
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// 🔥 Headers
header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = get_pdo_connection();
    $current_user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? 'user';

    if (!$current_user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? null;

            if ($action === 'list') {
                $sql = "SELECT id, name, url, username, password_needs_update, notes, created_by FROM sites";
                $params = [];

                if ($user_role === 'admin') {
                    $sql .= " WHERE created_by = ?";
                    $params[] = $current_user_id;
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

            if ($action === 'get') {
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }

                // 🟢 El superadmin ve todos
                if ($user_role === 'superadmin') {
                    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
                    $stmt->execute([$id]);
                }
                // 🟡 El admin solo ve sus sitios
                elseif ($user_role === 'admin') {
                    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ? AND created_by = ?");
                    $stmt->execute([$id, $current_user_id]);
                }
                // 🔴 Acceso denegado
                else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
                    exit;
                }

                $site = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$site) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
                    exit;
                }

                // ✅ Convertir BLOBs a base64 para que sean JSON serializables
                if ($site['password_encrypted'] !== null) {
                    $site['password_encrypted'] = base64_encode($site['password_encrypted']);
                }
                if ($site['iv'] !== null) {
                    $site['iv'] = base64_encode($site['iv']);
                }

                $site['password_needs_update'] = !empty($site['password_needs_update']);
                $site['id'] = (int)$site['id'];

                echo json_encode(['success' => true, 'data' => $site]);
                exit;
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción GET no válida']);
            exit;

        case 'POST':
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verify_csrf_token($csrf_token)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'JSON inválido']);
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

            if ($action === 'delete') {
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                    exit;
                }

                $sql = "DELETE FROM sites WHERE id = ?";
                $params = [$id];

                if ($user_role === 'admin') {
                    $sql .= " AND created_by = ?";
                    $params[] = $current_user_id;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Sitio eliminado']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'No encontrado o sin permisos']);
                }
                exit;
            }

            if ($action === 'add' || $action === 'edit') {
                if (empty($name) || empty($url)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nombre y URL requeridos']);
                    exit;
                }

                $validation_url = $url;
                if (strpos($url, '://') === false) {
                    $validation_url = 'http://' . $url;
                }
                if (!filter_var($validation_url, FILTER_VALIDATE_URL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'URL no válida']);
                    exit;
                }

                // Validar nombre duplicado
                $check_sql = "SELECT id FROM sites WHERE name = ?";
                $check_params = [$name];
                if ($action === 'edit' && $id) {
                    $check_sql .= " AND id != ?";
                    $check_params[] = $id;
                }
                if ($user_role === 'admin') {
                    $check_sql .= " AND created_by = ?";
                    $check_params[] = $current_user_id;
                }

                $stmt = $pdo->prepare($check_sql);
                $stmt->execute($check_params);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Nombre duplicado']);
                    exit;
                }

                // Encriptar contraseña
                $encrypted_data = null;
                if ($password !== null && $password !== '') {
                    if (!function_exists('encrypt_to_parts')) {
                        error_log("ERROR: encrypt_to_parts() no existe");
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Función de encriptación no disponible']);
                        exit;
                    }
                    $encrypted_data = encrypt_to_parts($password);
                    if (!$encrypted_data) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Error al encriptar']);
                        exit;
                    }
                }

                if ($action === 'edit') {
                    if (!$id) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
                        exit;
                    }

                    $sql_parts = ["name = ?", "url = ?", "username = ?", "password_needs_update = ?", "notes = ?"];
                    $params = [$name, $url, $username, $needs_update, $notes];

                    if ($encrypted_data) {
                        $sql_parts[] = "password_encrypted = ?";
                        $sql_parts[] = "iv = ?";
                        $params[] = $encrypted_data['ciphertext'];
                        $params[] = $encrypted_data['iv'];
                    }

                    $sql = "UPDATE sites SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                    $params[] = $id;

                    if ($user_role === 'admin') {
                        $sql .= " AND created_by = ?";
                        $params[] = $current_user_id;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Actualizado']);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'No encontrado o sin cambios']);
                    }

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
                            $needs_update, $notes, $current_user_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO sites (name, url, username, password_needs_update, notes, created_by)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $url, $username, $needs_update, $notes, $current_user_id]);
                    }

                    $newId = $pdo->lastInsertId();
                    echo json_encode(['success' => true, 'message' => 'Creado', 'id' => (int)$newId]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción inválida']);
            }
            exit;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    error_log("ERROR en manage_sites.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
} finally {
    if (ob_get_level() > 0) ob_end_flush();
}