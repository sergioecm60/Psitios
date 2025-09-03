<?php

/**
 * api/manage_sites.php
 * API para gestionar la tabla centralizada de sitios.
 * Permite listar, obtener, agregar, editar y eliminar sitios.
 */

require_once '../bootstrap.php';

// --- Seguridad: Solo administradores pueden acceder a esta API ---
require_auth('admin', true);

header('Content-Type: application/json');

$pdo = get_pdo_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // Listar todos los sitios (sin contraseñas)
            $stmt = $pdo->query("
                SELECT 
                    id, 
                    name, 
                    url, 
                    username, 
                    password_needs_update, 
                    notes 
                FROM sites 
                ORDER BY name ASC
            ");
            $sites = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $sites
            ]);

        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Obtener un sitio específico
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new InvalidArgumentException('ID de sitio no válido.');
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
            $site = $stmt->fetch();

            if (!$site) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Sitio no encontrado.']);
            } else {
                echo json_encode(['success' => true, 'data' => $site]);
            }
        } else {
            throw new InvalidArgumentException('Acción GET no válida.');
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Datos JSON no válidos.');
        }

        $action = $data['action'] ?? null;
        
        // ✅ Leer CSRF del header
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF). Recargue la página.']);
            exit;
        }

        if ($action === 'add') {
            // Agregar nuevo sitio
            $name = trim($data['name'] ?? '');
            $url = trim($data['url'] ?? '');
            $username = trim($data['username'] ?? '');
            $password = trim($data['password'] ?? '');
            $password_needs_update = !empty($data['password_needs_update']) ? 1 : 0;
            $notes = trim($data['notes'] ?? '');

            if (empty($name) || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Nombre y URL son requeridos.');
            }

            // ✅ Encriptar contraseña si se proporcionó
            $encrypted_password = null;
            $iv = null;
            if (!empty($password)) {
                $encrypted = encrypt_data($password);
                if ($encrypted === null) {
                    throw new Exception('Error al encriptar la contraseña.');
                }
                $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
                $iv = substr(base64_decode($encrypted), 0, $iv_length);
                $encrypted_password = substr(base64_decode($encrypted), $iv_length);
            }

            $stmt = $pdo->prepare("
                INSERT INTO sites 
                (name, url, username, password_encrypted, iv, password_needs_update, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, 
                $url, 
                $username, 
                $encrypted_password, 
                $iv, 
                $password_needs_update, 
                $notes
            ]);
            echo json_encode(['success' => true, 'message' => 'Sitio agregado correctamente.']);

        } elseif ($action === 'edit' && isset($data['id'])) {
            // Editar sitio existente
            $id = filter_var($data['id'], FILTER_VALIDATE_INT);
            $name = trim($data['name'] ?? '');
            $url = trim($data['url'] ?? '');
            $username = trim($data['username'] ?? '');
            $password = trim($data['password'] ?? '');
            $password_needs_update = !empty($data['password_needs_update']) ? 1 : 0;
            $notes = trim($data['notes'] ?? '');

            if ($id === false || $id <= 0 || empty($name) || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('ID, nombre y URL son requeridos.');
            }

            if (!empty($password)) {
                // Nueva contraseña
                $encrypted = encrypt_data($password);
                if ($encrypted === null) {
                    throw new Exception('Error al encriptar la contraseña.');
                }
                $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
                $iv = substr(base64_decode($encrypted), 0, $iv_length);
                $encrypted_password = substr(base64_decode($encrypted), $iv_length);

                $stmt = $pdo->prepare("
                    UPDATE sites 
                    SET name = ?, 
                        url = ?, 
                        username = ?, 
                        password_encrypted = ?, 
                        iv = ?, 
                        password_needs_update = ?, 
                        notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, 
                    $url, 
                    $username, 
                    $encrypted_password, 
                    $iv, 
                    $password_needs_update, 
                    $notes, 
                    $id
                ]);
            } else {
                // Mantener contraseña actual
                $stmt = $pdo->prepare("
                    UPDATE sites 
                    SET name = ?, 
                        url = ?, 
                        username = ?, 
                        password_needs_update = ?, 
                        notes = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, 
                    $url, 
                    $username, 
                    $password_needs_update, 
                    $notes, 
                    $id
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Sitio actualizado correctamente.']);

        } elseif ($action === 'delete' && isset($data['id'])) {
            // Eliminar sitio
            $id = filter_var($data['id'], FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new InvalidArgumentException('ID de sitio no válido.');
            }

            $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Sitio eliminado correctamente.']);

        } else {
            throw new InvalidArgumentException('Acción POST no válida.');
        }
    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    }

} catch (PDOException $e) {
    error_log("Error de base de datos en manage_sites.php: " . $e->getMessage());
    http_response_code(500);
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Error: Ya existe un sitio con ese nombre.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error inesperado en manage_sites.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
}