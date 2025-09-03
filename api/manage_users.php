<?php

/**
 * api/manage_users.php
 * API para gestionar los usuarios del sistema (CRUD y cambio de estado).
 */

require_once '../bootstrap.php';

// Seguridad: Solo los administradores pueden acceder a esta API.
require_auth('admin');

header('Content-Type: application/json');
$pdo = get_pdo_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // Listar todos los usuarios excepto el propio admin para evitar que se auto-elimine.
            $stmt = $pdo->prepare("SELECT id, username, role, is_active, created_at FROM users WHERE id != ? ORDER BY username ASC");
            $stmt->execute([$_SESSION['user_id']]);
            $users = $stmt->fetchAll();

            // ✅ CAMBIO: Envolver la respuesta en "success" y "data"
            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            exit;

        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Obtener los datos de un usuario específico para editar.
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('ID de usuario no válido.');
            
            $stmt = $pdo->prepare("SELECT id, username, role, is_active FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            } else {
                // Respuesta consistente: siempre envolvemos los datos.
                echo json_encode(['success' => true, 'data' => $user]);
            }
            exit;
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new InvalidArgumentException('Datos JSON no válidos.');

        // Leer el token CSRF desde la cabecera HTTP, que es la práctica estándar.
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($csrf_token)) {
            http_response_code(403); // Forbidden
            throw new Exception('Error de seguridad (CSRF). La solicitud ha sido rechazada.');
        }

        $action = $data['action'] ?? null;

        switch ($action) {
            case 'add':
            case 'edit':
                $username = trim($data['username'] ?? '');
                $password = $data['password'] ?? '';
                $role = $data['role'] ?? 'user';                
                // Usar filter_var para una validación booleana más robusta.
                $is_active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

                if (empty($username) || !in_array($role, ['admin', 'user'])) {
                    throw new InvalidArgumentException('Nombre de usuario y rol son requeridos.');
                }

                if ($action === 'add') {
                    if (empty($password)) throw new InvalidArgumentException('La contraseña es requerida para nuevos usuarios.');
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $role, $is_active]);
                    echo json_encode(['success' => true, 'message' => 'Usuario creado.', 'new_user_id' => $pdo->lastInsertId()]);
                } else { // edit
                    $id = filter_var($data['id'], FILTER_VALIDATE_INT);
                    if (!$id) throw new InvalidArgumentException('ID de usuario no válido para editar.');

                    $sql = "UPDATE users SET username = ?, role = ?, is_active = ?";
                    $params = [$username, $role, $is_active];

                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        $sql .= ", password_hash = ?";
                        $params[] = $password_hash;
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    echo json_encode(['success' => true, 'message' => 'Usuario actualizado.']);
                }
                break;

            case 'delete':
                $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException('ID de usuario no válido.');
                // Añadir verificación para evitar la auto-eliminación
                if ($id === $_SESSION['user_id']) {
                    http_response_code(400); // Bad Request
                    throw new InvalidArgumentException('No puedes eliminar tu propia cuenta de administrador.');
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Usuario eliminado.']);
                break;

            case 'toggle_status':
                $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException('ID de usuario no válido.');
                $is_active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active, $id]);
                echo json_encode(['success' => true, 'message' => 'Estado actualizado.']);
                break;

            default:
                throw new InvalidArgumentException('Acción POST no válida o no especificada.');
        }
        exit;
    }
} catch (InvalidArgumentException $e) {
    // Errores causados por datos incorrectos del cliente (400 Bad Request)
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    // Errores de base de datos (500 Internal Server Error)
    error_log("PDOException in manage_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al procesar la solicitud.']);
} catch (Exception $e) {
    // Otros errores inesperados
    error_log("Exception in manage_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();