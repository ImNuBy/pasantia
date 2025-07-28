
<?php
/**
 * API de logout - api/logout.php
 */
require_once '../config/database.php';

setCORSHeaders();

try {
    $user_id = requireAuth();
    
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
    
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    $result = $auth->logout($token);
    
    if ($result['success']) {
        logActivity($user_id, 'logout');
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error cerrando sesión']);
    }

} catch (Exception $e) {
    error_log("Error en logout API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>

---
