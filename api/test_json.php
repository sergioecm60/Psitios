<?php
// test_json.php - Ultra simple test endpoint
// Use this to test if basic JSON responses work

// Prevent any output before headers
if (ob_get_level()) ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

// Set HTTP response code to 200 OK
http_response_code(200);

// Start session if needed
session_start();

// Set a mock user_id for testing if none exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

// Simple response
$response = [
    'success' => true,
    'message' => 'Test endpoint working',
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
];

echo json_encode($response, JSON_PRETTY_PRINT);
exit();
?>