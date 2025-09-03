<?php

/**
 * api/manage_services.php
 * API para gestionar las asignaciones de servicios a los usuarios (CRUD).
 */

require_once '../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');
$pdo = get_pdo_connection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? null;

        if ($action === 'list' && isset($_GET['user_id'])) {
            $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new InvalidArgumentException('ID de usuario no válido.');

            $stmt = $pdo->prepare(
                "SELECT s.id, st.name as site_name, st.username as service_username, st.password_needs_update
                 FROM services s
                 JOIN sites st ON s.site_id = st.id
                 WHERE s.user_id = ?
                 ORDER BY st.name ASC"
            );
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll());

        } elseif ($action === 'get' && isset($_GET['id'])) {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('ID de servicio no válido.');

            $stmt = $pdo->prepare("SELECT id, user_id, site_id, notes FROM services WHERE id = ?");
            $stmt->execute([$id]);
            $service = $stmt->fetch();
            if (!$service) throw new Exception('Servicio no encontrado.');
            
            echo json_encode(['success' => true, 'data' => $service]);
        } else {
            throw new InvalidArgumentException('Acción GET no válida.');
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new InvalidArgumentException('Datos JSON no válidos.');
        
        if (!verify_csrf_token($data['csrf_token'] ?? '')) throw new Exception('Error de seguridad (CSRF).');

        $action = $data['action'] ?? null;

        if ($action === 'add') {
            $user_id = filter_var($data['user_id'], FILTER_VALIDATE_INT);
            $site_id = filter_var($data['site_id'], FILTER_VALIDATE_INT);
            $notes = trim($data['notes'] ?? '');

            if (!$user_id || !$site_id) throw new InvalidArgumentException('Usuario y Sitio son requeridos.');

            $stmt = $pdo->prepare("INSERT INTO services (user_id, site_id, notes) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $site_id, $notes]);
            echo json_encode(['success' => true]);

        } elseif ($action === 'edit') {
            $id = filter_var($data['id'], FILTER_VALIDATE_INT);
            $site_id = filter_var($data['site_id'], FILTER_VALIDATE_INT);
            $notes = trim($data['notes'] ?? '');

            if (!$id || !$site_id) throw new InvalidArgumentException('ID de servicio y Sitio son requeridos.');

            $stmt = $pdo->prepare("UPDATE services SET site_id = ?, notes = ? WHERE id = ?");
            $stmt->execute([$site_id, $notes, $id]);
            echo json_encode(['success' => true]);

        } elseif ($action === 'delete' && isset($data['id'])) {
            $id = filter_var($data['id'], FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('ID de servicio no válido.');
            $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            throw new InvalidArgumentException('Acción POST no válida.');
        }
    }
} catch (Exception $e) {
    error_log("Error en manage_services.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}