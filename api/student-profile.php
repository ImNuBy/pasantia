
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar sesión
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
        exit();
    }
    
    if ($_SESSION['tipo_usuario'] !== 'estudiante') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    
    // Conectar a BD
    $pdo = new PDO('mysql:host=localhost;dbname=epa703;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Obtener información completa del estudiante
    $sql = "SELECT u.*, e.legajo, e.fecha_ingreso, e.estado, e.tutor_nombre, 
                   e.tutor_telefono, e.tutor_email, e.observaciones,
                   c.nombre as curso, c.anio, c.division, c.turno,
                   o.nombre as orientacion
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.id = e.usuario_id
            LEFT JOIN cursos c ON e.curso_id = c.id
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id
            WHERE u.id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $perfil = $stmt->fetch();
    
    if (!$perfil) {
        throw new Exception('Perfil no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $perfil
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
