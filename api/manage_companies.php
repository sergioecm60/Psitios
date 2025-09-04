<?php
// api/manage_companies.php

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
if ($method === 'POST' && !verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    ob_end_flush();
    exit;
}

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            ob_end_flush();
            exit;
        }

        $action = $input['action'] ?? '';
        $name = trim($input['name'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
            ob_end_flush();
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO companies (name, created_at) VALUES (?, NOW())");
            $stmt->execute([$name]);
            echo json_encode(['success' => true, 'message' => 'Creada', 'id' => (int)$pdo->lastInsertId()]);
        } elseif ($action === 'edit') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                ob_end_flush();
                exit;
            }
            $stmt = $pdo->prepare("UPDATE companies SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            echo json_encode(['success' => true, 'message' => 'Actualizada']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

if (ob_get_level()) {
    ob_end_flush();
}
exit;