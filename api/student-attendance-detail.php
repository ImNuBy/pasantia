
<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Session.php';

Session::requireLogin();
Session::requireRole(['estudiante']);

try {
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'];
    $mes = $_GET['mes'] ?? date('Y-m');
    
    // Get student ID
    $sql = "SELECT e.id FROM estudiantes e WHERE e.usuario_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) {
        throw new Exception('Estudiante no encontrado');
    }
    
    // Calculate date range for the month
    $fechaDesde = $mes . '-01';
    $fechaHasta = date('Y-m-t', strtotime($fechaDesde));
    
    // Get detailed attendance
    $sql = "SELECT a.fecha, m.nombre as materia, a.estado, a.observaciones
            FROM asistencias a
            JOIN materias m ON a.materia_id = m.id
            WHERE a.estudiante_id = :estudiante_id
            AND a.fecha BETWEEN :fecha_desde AND :fecha_hasta
            ORDER BY a.fecha DESC, m.nombre";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'estudiante_id' => $estudiante['id'],
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta
    ]);
    $asistencias = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $asistencias
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
                