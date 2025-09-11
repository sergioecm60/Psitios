<?php
// En tu archivo api/manage_reminders.php (o similar)

// ... (includes, conexión a BD, etc.)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (validación de sesión, CSRF, etc.)

    // 1. Validar el tipo de recordatorio
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['note', 'credential', 'phone'])) {
        send_json_error(400, 'Tipo de recordatorio inválido.');
        exit;
    }

    // 2. Preparar las variables
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // ... otras variables como username, password, reminder_datetime ...

    // 3. Lógica específica para el tipo 'phone'
    // Si el tipo es 'phone', el número de teléfono viene en su propio campo.
    // Lo asignamos a la variable $notes para guardarlo en la columna correcta de la BD.
    if ($type === 'phone') {
        $phone_number = trim($_POST['phone'] ?? '');
        if (empty($phone_number)) {
            send_json_error(400, 'El número de teléfono es obligatorio.');
            exit;
        }
        // Sobrescribimos la variable $notes con el número de teléfono.
        $notes = $phone_number;
    }

    // 4. El resto de tu código para INSERTAR o ACTUALIZAR en la base de datos
    // ya usará la variable $notes, que ahora contiene el dato correcto:
    // - La nota para 'note' y 'credential'.
    // - El número de teléfono para 'phone'.
    
    // Ejemplo de guardado:
    // $stmt = $pdo->prepare("INSERT INTO user_reminders (..., title, notes, type) VALUES (...)");
    // $stmt->execute([..., $title, $notes, $type]);
}

// ...
?>
