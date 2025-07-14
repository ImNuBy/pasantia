
<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Session.php';
require_once '../classes/Security.php';

Session::requireLogin();
Session::requireRole(['estudiante']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['section']) || !isset($input['data'])) {
        throw new Exception('Datos inválidos');
    }
    
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'];
    $section = $input['section'];
    $data = $input['data'];
    
    // Sanitizar datos
    foreach ($data as $key => $value) {
        $data[$key] = Security::sanitizeInput($value);
    }
    
    $db->beginTransaction();
    
    switch ($section) {
        case 'datos_personales':
            // Update usuarios table
            $sql = "UPDATE usuarios SET dni = :dni, fecha_nacimiento = :fecha_nacimiento, direccion = :direccion 
                    WHERE id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'dni' => $data['dni'] ?: null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?: null,
                'direccion' => $data['direccion'] ?: null,
                'user_id' => $userId
            ]);
            break;
            
        case 'contacto':
            // Validate email
            if (!Security::validateEmail($data['email'])) {
                throw new Exception('Email inválido');
            }
            
            // Update usuarios table
            $sql = "UPDATE usuarios SET email = :email, telefono = :telefono WHERE id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'email' => $data['email'],
                'telefono' => $data['telefono'] ?: null,
                'user_id' => $userId
            ]);
            break;
            
        case 'tutor':
            // Update estudiantes table
            $sql = "UPDATE estudiantes SET tutor_nombre = :tutor_nombre, tutor_telefono = :tutor_telefono, 
                           tutor_email = :tutor_email 
                    WHERE usuario_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'tutor_nombre' => $data['tutor_nombre'] ?: null,
                'tutor_telefono' => $data['tutor_telefono'] ?: null,
                'tutor_email' => $data['tutor_email'] ?: null,
                'user_id' => $userId
            ]);
            break;
            
        default:
            throw new Exception('Sección inválida');
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
