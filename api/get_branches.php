
<?php
// ===============================
// api/get_branches.php
// ===============================
require_once __DIR__ . '/../bootstrap.php';
require_auth('admin');
header('Content-Type: application/json');

try {
    $pdo = get_pdo_connection();
    
    $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT);
    
    if ($company_id) {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as company_name 
            FROM branches b 
            LEFT JOIN companies c ON b.company_id = c.id 
            WHERE b.company_id = ? 
            ORDER BY b.name ASC
        ");
        $stmt->execute([$company_id]);
    } else {
        $stmt = $pdo->query("
            SELECT b.*, c.name as company_name 
            FROM branches b 
            LEFT JOIN companies c ON b.company_id = c.id 
            ORDER BY c.name ASC, b.name ASC
        ");
    }
    
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $branches]);
} catch (Exception $e) {
    error_log("Error en get_branches.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>