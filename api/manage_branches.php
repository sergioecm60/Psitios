<?php
/**
 * api/manage_branches.php
 * CRUD de sucursales
 */

require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $company_id = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
    $province = trim($input['province'] ?? '');

    if (!$name || !$company_id || !$province) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $pdo = get_pdo_connection();

    try {
        if (isset($input['id'])) {
            $stmt = $pdo->prepare("UPDATE branches SET name = ?, company_id = ?, province = ? WHERE id = ?");
            $stmt->execute([$name, $company_id, $province, $input['id']]);
            echo json_encode(['success' => true, 'message' => 'Sucursal actualizada']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO branches (name, company_id, province) VALUES (?, ?, ?)");
            $stmt->execute([$name, $company_id, $province]);
            echo json_encode(['success' => true, 'message' => 'Sucursal creada']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
    exit;
}

// GET - Listar todas o por empresa
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = get_pdo_connection();
        $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT);

        if ($company_id) {
            $stmt = $pdo->prepare("
                SELECT b.id, b.name, b.province, c.name as company_name
                FROM branches b
                JOIN companies c ON b.company_id = c.id
                WHERE b.company_id = ?
                ORDER BY b.name ASC
            ");
            $stmt->execute([$company_id]);
        } else {
            $stmt = $pdo->query("
                SELECT b.id, b.name, b.province, c.name as company_name
                FROM branches b
                JOIN companies c ON b.company_id = c.id
                ORDER BY c.name, b.name
            ");
        }

        $branches = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $branches]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al cargar sucursales']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);