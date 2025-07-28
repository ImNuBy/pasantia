
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
    // Verificar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'estudiante') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    
    // Conectar a BD
    $pdo = new PDO('mysql:host=localhost;dbname=epa703;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Obtener ID del estudiante
    $sql = "SELECT id FROM estudiantes WHERE usuario_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $estudianteData = $stmt->fetch();
    $estudianteId = $estudianteData['id'] ?? null;
    
    // Datos por defecto
    $stats = [
        'materias_cursando' => 6,
        'promedio_historico' => '8.2',
        'materias_aprobadas' => 18,
        'porcentaje_asistencia' => '92.5'
    ];
    
    if ($estudianteId) {
        // Materias cursando este año
        $sql = "SELECT COUNT(DISTINCT m.id) as materias_cursando
                FROM materias m
                JOIN profesor_materia pm ON m.id = pm.materia_id
                JOIN cursos c ON pm.curso_id = c.id
                JOIN estudiantes e ON c.id = e.curso_id
                WHERE e.id = :estudiante_id AND pm.anio_lectivo = :anio_lectivo AND pm.activo = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estudiante_id' => $estudianteId, 'anio_lectivo' => date('Y')]);
        $materiasCursando = $stmt->fetch();
        
        if ($materiasCursando && $materiasCursando['materias_cursando'] > 0) {
            $stats['materias_cursando'] = $materiasCursando['materias_cursando'];
        }
        
        // Promedio histórico
        $sql = "SELECT AVG(nota) as promedio_historico
                FROM calificaciones
                WHERE estudiante_id = :estudiante_id AND nota IS NOT NULL";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estudiante_id' => $estudianteId]);
        $promedio = $stmt->fetch();
        
        if ($promedio && $promedio['promedio_historico']) {
            $stats['promedio_historico'] = number_format($promedio['promedio_historico'], 2);
        }
        
        // Materias aprobadas
        $sql = "SELECT COUNT(DISTINCT materia_id) as materias_aprobadas
                FROM calificaciones
                WHERE estudiante_id = :estudiante_id AND nota >= 4";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estudiante_id' => $estudianteId]);
        $aprobadas = $stmt->fetch();
        
        if ($aprobadas) {
            $stats['materias_aprobadas'] = $aprobadas['materias_aprobadas'];
        }
        
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
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estudiante_id' => $estudianteId]);
        $asistencia = $stmt->fetch();
        
        if ($asistencia && $asistencia['porcentaje_asistencia']) {
            $stats['porcentaje_asistencia'] = number_format($asistencia['porcentaje_asistencia'], 1);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}