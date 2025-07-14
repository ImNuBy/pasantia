
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
    
    // Get student ID
    $sql = "SELECT e.id FROM estudiantes e WHERE e.usuario_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) {
        throw new Exception('Estudiante no encontrado');
    }
    
    $estudianteId = $estudiante['id'];
    $anioActual = date('Y');
    
    // Materias cursando este año
    $sql = "SELECT COUNT(DISTINCT m.id) as materias_cursando
            FROM materias m
            JOIN profesor_materia pm ON m.id = pm.materia_id
            JOIN cursos c ON pm.curso_id = c.id
            JOIN estudiantes e ON c.id = e.curso_id
            WHERE e.id = :estudiante_id AND pm.anio_lectivo = :anio_lectivo AND pm.activo = 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['estudiante_id' => $estudianteId, 'anio_lectivo' => $anioActual]);
    $materiasCursando = $stmt->fetch();
    
    // Promedio histórico
    $sql = "SELECT AVG(nota) as promedio_historico
            FROM calificaciones
            WHERE estudiante_id = :estudiante_id AND nota IS NOT NULL";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['estudiante_id' => $estudianteId]);
    $promedioHistorico = $stmt->fetch();
    
    // Materias aprobadas (nota >= 4)
    $sql = "SELECT COUNT(DISTINCT materia_id) as materias_aprobadas
            FROM calificaciones
            WHERE estudiante_id = :estudiante_id AND nota >= 4";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['estudiante_id' => $estudianteId]);
    $materiasAprobadas = $stmt->fetch();
    
    // Porcentaje de asistencia promedio
    $sql = "SELECT AVG(
                CASE 
                    WHEN total_clases > 0 THEN (presentes * 100.0 / total_clases)
                    ELSE 0 
                END
            ) as porcentaje_asistencia
            FROM (
                SELECT materia_id,
                       COUNT(*) as total_clases,
                       SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes
                FROM asistencias 
                WHERE estudiante_id = :estudiante_id 
                AND fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY materia_id
            ) as subquery";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['estudiante_id' => $estudianteId]);
    $porcentajeAsistencia = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'materias_cursando' => $materiasCursando['materias_cursando'] ?? 0,
            'promedio_historico' => $promedioHistorico['promedio_historico'] ? 
                number_format($promedioHistorico['promedio_historico'], 2) : null,
            'materias_aprobadas' => $materiasAprobadas['materias_aprobadas'] ?? 0,
            'porcentaje_asistencia' => $porcentajeAsistencia['porcentaje_asistencia'] ? 
                number_format($porcentajeAsistencia['porcentaje_asistencia'], 1) : 0
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
