
<?php
// ===============================
// api/get_branch.php
// ===============================
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

try {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("
        SELECT b.*, p.country_id
        FROM branches b
        LEFT JOIN provinces p ON b.province = p.name
        WHERE b.id = ?
    ");
    $stmt->execute([$id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $branch]);
} catch (Exception $e) {
    error_log("Error en get_branch.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>