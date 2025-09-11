<?php
/**
 * api/manage_users.php
 * Endpoint de la API para gestionar usuarios (CRUD).
 * Permite listar, obtener, crear, editar y eliminar usuarios.
 * Utilizado por el panel de administración, con lógica de permisos para 'admin' y 'superadmin'.
 */

// Inicia el control del buffer de salida para garantizar una respuesta JSON pura.
if (ob_get_level()) {
    ob_end_clean();
}

// Carga el archivo de arranque y requiere autenticación de administrador.
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

// Informa al cliente que la respuesta será en formato JSON.
header('Content-Type: application/json; charset=utf-8');


// --- Lógica Principal ---
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = get_pdo_connection();
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'] ?? 'user';
    $current_user_department_id = $_SESSION['department_id'] ?? null;

    switch ($method) {
        case 'POST':
            // Validar token CSRF para todas las operaciones que modifican datos.
            $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!verify_csrf_token($csrf_token)) {
                send_json_error_and_exit(403, 'Token CSRF inválido.');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                send_json_error_and_exit(400, 'JSON inválido.');
            }

            $action = $input['action'] ?? null;
            switch ($action) {
                case 'add':
                case 'edit':
                    // --- Recolección y Validación de Datos ---
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    $username = trim($input['username'] ?? '');
                    $password = $input['password'] ?? null;
                    $role = in_array($input['role'] ?? '', ['user', 'admin']) ? $input['role'] : 'user';
                    $is_active = filter_var($input['is_active'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    $company_id = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $branch_id = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $department_id = filter_var($input['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $assigned_admin_id = filter_var($input['assigned_admin_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

                    if (empty($username)) {
                        send_json_error_and_exit(400, 'El nombre de usuario es requerido.');
                    }

                    // --- Lógica de Seguridad y Permisos ---
                    // Un admin solo puede crear/editar usuarios dentro de su propio departamento.
                    if ($current_user_role === 'admin') {
                        if ($department_id != $current_user_department_id) {
                            send_json_error_and_exit(403, 'No tiene permiso para gestionar usuarios fuera de su departamento.');
                        }
                        // Un admin no puede crear otros admins.
                        if ($role === 'admin') {
                             send_json_error_and_exit(403, 'No tiene permiso para crear usuarios administradores.');
                        }
                    }

                    // --- Lógica de Negocio ---
                    // Validar nombre de usuario duplicado.
                    $check_sql = "SELECT id FROM users WHERE username = ?";
                    $check_params = [$username];
                    if ($action === 'edit' && $id) {
                        $check_sql .= " AND id != ?";
                        $check_params[] = $id;
                    }
                    $stmt = $pdo->prepare($check_sql);
                    $stmt->execute($check_params);
                    if ($stmt->fetch()) {
                        send_json_error_and_exit(409, 'El nombre de usuario ya está en uso.');
                    }

                    // --- Construcción de la Consulta ---
                    if ($action === 'add') {
                        if (empty($password)) {
                            send_json_error_and_exit(400, 'La contraseña es requerida para crear un nuevo usuario.');
                        }
                        // Hashear la contraseña usando la función de seguridad centralizada.
                        $password_hash = hash_password($password);

                        $sql = "INSERT INTO users (username, password_hash, role, is_active, company_id, branch_id, department_id, assigned_admin_id, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $params = [$username, $password_hash, $role, $is_active, $company_id, $branch_id, $department_id, $assigned_admin_id, $current_user_id];
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        echo json_encode(['success' => true, 'message' => 'Usuario creado con éxito.', 'id' => (int)$pdo->lastInsertId()]);

                    } else { // 'edit'
                        if (!$id) {
                            send_json_error_and_exit(400, 'ID de usuario no proporcionado para editar.');
                        }

                        $sql_parts = [
                            "username = ?", "role = ?", "is_active = ?", "company_id = ?",
                            "branch_id = ?", "department_id = ?", "assigned_admin_id = ?"
                        ];
                        $params = [$username, $role, $is_active, $company_id, $branch_id, $department_id, $assigned_admin_id];

                        // Solo actualizar la contraseña si se proporcionó una nueva.
                        if (!empty($password)) {
                            // **PUNTO CLAVE**: Usar la función `hash_password` para hashear la contraseña.
                            // Este era el punto probable del error 500.
                            $password_hash = hash_password($password);
                            $sql_parts[] = "password_hash = ?";
                            $params[] = $password_hash;
                        }

                        $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                        $params[] = $id;

                        // Un admin solo puede editar usuarios de su departamento.
                        if ($current_user_role === 'admin') {
                            $sql .= " AND department_id = ?";
                            $params[] = $current_user_department_id;
                        }

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        if ($stmt->rowCount() > 0) {
                            echo json_encode(['success' => true, 'message' => 'Usuario actualizado con éxito.']);
                        } else {
                            send_json_error_and_exit(404, 'Usuario no encontrado, sin permisos o sin cambios para aplicar.');
                        }
                    }
                    break;

                case 'delete':
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) {
                        send_json_error_and_exit(400, 'ID de usuario no válido.');
                    }
                    if ($id === $current_user_id) {
                        send_json_error_and_exit(403, 'No puedes eliminar tu propia cuenta.');
                    }

                    $sql = "DELETE FROM users WHERE id = ?";
                    $params = [$id];

                    // Un admin solo puede eliminar usuarios de su departamento.
                    if ($current_user_role === 'admin') {
                        $sql .= " AND department_id = ?";
                        $params[] = $current_user_department_id;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Usuario eliminado con éxito.']);
                    } else {
                        send_json_error_and_exit(404, 'Usuario no encontrado o no tiene permiso para eliminarlo.');
                    }
                    break;

                default:
                    send_json_error_and_exit(400, 'Acción POST no válida.');
            }
            break;

        default:
            send_json_error_and_exit(405, 'Método no permitido.');
    }
} catch (PDOException $e) {
    // Manejo de errores específicos de la base de datos.
    if ($e->getCode() == '23000') {
        send_json_error_and_exit(409, 'Error de integridad de datos. Es posible que el nombre de usuario ya exista.', $e);
    }
    send_json_error_and_exit(500, 'Error de base de datos.', $e);
} catch (Throwable $e) {
    // Captura de cualquier otro error para evitar respuestas vacías.
    send_json_error_and_exit(500, 'Error interno del servidor.', $e);
}

?>
```

### 2. Mejora de Calidad en `panel.php`

He simplificado la lógica para generar el token de seguridad en `panel.php`, eliminando código redundante y haciéndolo más claro.

```diff
--- a/c:/laragon/www/Psitios/panel.php
+++ b/c:/laragon/www/Psitios/panel.php
@@ -14,15 +14,8 @@
 $user_theme = $user_data['theme'] ?? 'light';
 
 $nonce = base64_encode(random_bytes(16));
-// Generar token CSRF solo si no existe para mantenerlo estable durante la sesión del usuario.
-$csrf_token = generate_csrf_token();
-// Se asegura que el token CSRF solo se genere si no existe, para mantenerlo
-// estable durante toda la sesión del usuario. Esto previene errores de validación
-// en las operaciones AJAX dentro del panel.
-if (empty($_SESSION['csrf_token'])) {
-    generate_csrf_token();
-}
-$csrf_token = $_SESSION['csrf_token'];
+// Generar un token CSRF y asegurarse de que sea estable durante toda la sesión del usuario.
+// La función `generate_csrf_token` ya comprueba si el token existe, por lo que
+// es seguro llamarla aquí. Esto simplifica la lógica anterior que era redundante.
+$csrf_token = generate_csrf_token();
 
 // Content Security Policy (CSP) segura
 header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; connect-src 'self';");