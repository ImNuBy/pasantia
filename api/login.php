<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Incluir archivos necesarios
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';

try {
    // Obtener datos del formulario
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    // Validar campos obligatorios
    $required = ['nombre', 'email', 'asunto', 'mensaje'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("El campo {$field} es obligatorio");
        }
    }
    
    // Sanitizar datos
    $nombre = Security::sanitizeInput($input['nombre']);
    $email = Security::sanitizeInput($input['email']);
    $telefono = Security::sanitizeInput($input['telefono'] ?? '');
    $asunto = Security::sanitizeInput($input['asunto']);
    $mensaje = Security::sanitizeInput($input['mensaje']);
    
    // Validar email
    if (!Security::validateEmail($email)) {
        throw new Exception('Email inválido');
    }
    
    // Guardar en base de datos
    $db = Database::getInstance()->getConnection();
    $sql = "INSERT INTO contactos (nombre, email, telefono, asunto, mensaje, ip_address, user_agent) 
            VALUES (:nombre, :email, :telefono, :asunto, :mensaje, :ip, :user_agent)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'nombre' => $nombre,
        'email' => $email,
        'telefono' => $telefono,
        'asunto' => $asunto,
        'mensaje' => $mensaje,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje enviado correctamente. Te contactaremos pronto.'
        ]);
    } else {
        throw new Exception('Error al enviar el mensaje');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>