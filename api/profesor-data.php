
<?php
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Session.php';

Session::requireLogin();
Session::requireRole(['profesor']);

try {
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'];
    
    // Get profesor ID
    $sql = "SELECT p.id, p.legajo, p.especialidad, p.titulo FROM profesores p WHERE p.usuario_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $profesor = $stmt->fetch();
    
    if (!$profesor) {
        throw new Exception('Profesor no encontrado');
    }
    
    // Get cursos asignados
    $sql = "SELECT DISTINCT c.id, c.nombre, c.anio, c.division, c.turno, o.nombre as orientacion,
                   COUNT(e.id) as total_estudiantes
            FROM profesor_materia pm
            JOIN cursos c ON pm.curso_id = c.id
            JOIN materias m ON pm.materia_id = m.id
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id
            LEFT JOIN estudiantes e ON c.id = e.curso_id
            WHERE pm.profesor_id = :profesor_id AND pm.activo = 1 AND pm.anio_lectivo = :anio_lectivo
            GROUP BY c.id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'profesor_id' => $profesor['id'],
        'anio_lectivo' => date('Y')
    ]);
    $cursos = $stmt->fetchAll();
    
    // Get materias que dicta
    $sql = "SELECT m.nombre as materia, c.nombre as curso, pm.anio_lectivo
            FROM profesor_materia pm
            JOIN materias m ON pm.materia_id = m.id
            JOIN cursos c ON pm.curso_id = c.id
            WHERE pm.profesor_id = :profesor_id AND pm.activo = 1 AND pm.anio_lectivo = :anio_lectivo";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'profesor_id' => $profesor['id'],
        'anio_lectivo' => date('Y')
    ]);
    $materias = $stmt->fetchAll();
    
    // Get calificaciones pendientes
    $sql = "SELECT COUNT(*) as pendientes
            FROM calificaciones cal
            JOIN profesor_materia pm ON cal.materia_id = pm.materia_id AND cal.profesor_id = pm.profesor_id
            WHERE pm.profesor_id = :profesor_id AND cal.nota IS NULL AND pm.anio_lectivo = :anio_lectivo";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'profesor_id' => $profesor['id'],
        'anio_lectivo' => date('Y')
    ]);
    $pendientes = $stmt->fetch();
    
    // Calculate total estudiantes
    $totalEstudiantes = array_sum(array_column($cursos, 'total_estudiantes'));
    
    // Get actividad reciente
    $sql = "SELECT 'Calificación' as tipo, cal.fecha_evaluacion as fecha, 
                   CONCAT(m.nombre, ' - ', c.nombre) as curso,
                   CONCAT('Evaluación: ', cal.tipo_evaluacion) as detalles
            FROM calificaciones cal
            JOIN materias m ON cal.materia_id = m.id
            JOIN profesor_materia pm ON cal.materia_id = pm.materia_id AND cal.profesor_id = pm.profesor_id
            JOIN cursos c ON pm.curso_id = c.id
            WHERE cal.profesor_id = :profesor_id 
            AND cal.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY cal.created_at DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['profesor_id' => $profesor['id']]);
    $actividad = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'profesor' => $profesor,
            'cursos' => $cursos,
            'materias' => $materias,
            'total_estudiantes' => $totalEstudiantes,
            'calificaciones_pendientes' => $pendientes['pendientes'],
            'actividad_reciente' => $actividad
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
