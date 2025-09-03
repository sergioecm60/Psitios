<?php

namespace Psitios\Controller;

class NotificationController
{
    /**
     * Crea una nueva notificación de problema desde el panel de usuario.
     */
    public function create()
    {
        // Esto es un endpoint de API, así que usamos el flag `true`
        require_auth('user', true);
        header('Content-Type: application/json');

        // --- Validar y decodificar JSON con manejo estricto ---
        $input = file_get_contents('php://input');
        if ($input === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No se recibió datos en la solicitud.']);
            return;
        }

        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Error en el formato de la solicitud (JSON inválido).']);
            return;
        }

        // --- Validar CSRF ---
        if (!verify_csrf_token($data['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF). Por favor, recargue la página e inténtelo nuevamente.']);
            return;
        }

        // --- Validar campo requerido: site_id ---
        if (!isset($data['site_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El ID del sitio es requerido.']);
            return;
        }

        $site_id = filter_var($data['site_id'], FILTER_VALIDATE_INT);
        if ($site_id === false || $site_id < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El ID del sitio proporcionado es inválido.']);
            return;
        }

        $user_id = $_SESSION['user_id'];

        try {
            $pdo = get_pdo_connection();

            // Evitar duplicados: no crear si ya hay una notificación abierta
            $stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND site_id = ? AND is_read = 0");
            $stmt->execute([$user_id, $site_id]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Ya existe una notificación abierta para este sitio. Un administrador la revisará pronto.'
                ]);
                return;
            }

            // Crear la notificación
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, site_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $site_id]);

            // ✅ Código 201: Recurso creado
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Notificación creada exitosamente. Un administrador revisará el problema.'
            ]);

        } catch (\PDOException $e) {
            error_log("Error de base de datos al crear notificación: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al enviar la notificación.'
            ]);
        }
    }
}