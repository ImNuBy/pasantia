<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si hay sesión activa
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Sesión no válida'
        ]);
        exit();
    }
    
    // Verificar que sea estudiante
    if ($_SESSION['tipo_usuario'] !== 'estudiante') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Acceso denegado. Solo para estudiantes.'
        ]);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    
    // Conectar a la base de datos
    $pdo = new PDO('mysql:host=localhost;dbname=epa703;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Obtener información del estudiante
    $sql = "SELECT u.*, e.legajo, e.fecha_ingreso, e.estado, e.tutor_nombre, 
                   e.tutor_telefono, e.tutor_email, c.nombre as curso_nombre, 
                   c.anio, c.division, c.turno, o.nombre as orientacion_nombre
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.id = e.usuario_id
            LEFT JOIN cursos c ON e.curso_id = c.id
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id
            WHERE u.id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) {
        throw new Exception('Estudiante no encontrado');
    }
    
    // Obtener ID del estudiante para las consultas relacionadas
    $sql = "SELECT id FROM estudiantes WHERE usuario_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $estudianteData = $stmt->fetch();
    $estudianteId = $estudianteData['id'] ?? null;
    
    // Inicializar arrays de datos
    $calificaciones = [];
    $asistencias = [];
    
    if ($estudianteId) {
        // Obtener calificaciones recientes (últimas 10)
        $sql = "SELECT cal.*, m.nombre as materia, cal.tipo_evaluacion, cal.nota, 
                       cal.fecha_evaluacion, cal.periodo, cal.anio_lectivo,
                       CONCAT(u.nombre, ' ', u.apellido) as profesor
                FROM calificaciones cal
                JOIN materias m ON cal.materia_id = m.id
                LEFT JOIN profesores p ON cal.profesor_id = p.id
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE cal.estudiante_id = :estudiante_id 
                AND cal.anio_lectivo = :anio_lectivo
                ORDER BY cal.fecha_evaluacion DESC 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'estudiante_id' => $estudianteId,
            'anio_lectivo' => date('Y')
        ]);
        $calificaciones = $stmt->fetchAll();
        
        // Obtener resumen de asistencias del mes actual
        $fechaDesde = date('Y-m-01');
        $fechaHasta = date('Y-m-t');
        
        $sql = "SELECT m.nombre as materia,
                       COUNT(*) as total_clases,
                       SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) as presentes,
                       SUM(CASE WHEN a.estado = 'ausente' THEN 1 ELSE 0 END) as ausentes,
                       SUM(CASE WHEN a.estado = 'tardanza' THEN 1 ELSE 0 END) as tardanzas,
                       ROUND((SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as porcentaje_asistencia
                FROM asistencias a
                JOIN materias m ON a.materia_id = m.id
                WHERE a.estudiante_id = :estudiante_id
                AND a.fecha BETWEEN :fecha_desde AND :fecha_hasta
                GROUP BY m.id, m.nombre
                ORDER BY m.nombre";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'estudiante_id' => $estudianteId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta
        ]);
        $asistencias = $stmt->fetchAll();
    }
    
    // Si no hay datos reales, crear datos de ejemplo
    if (empty($calificaciones)) {
        $calificaciones = [
            [
                'materia' => 'Circuitos Analógicos I',
                'tipo_evaluacion' => 'parcial',
                'nota' => '8.5',
                'fecha_evaluacion' => date('Y-m-d', strtotime('-5 days')),
                'periodo' => '1er_cuatrimestre',
                'profesor' => 'Prof. García, Luis'
            ],
            [
                'materia' => 'Circuitos Digitales I',
                'tipo_evaluacion' => 'trabajo_practico',
                'nota' => '9.0',
                'fecha_evaluacion' => date('Y-m-d', strtotime('-10 days')),
                'periodo' => '1er_cuatrimestre',
                'profesor' => 'Prof. Rodríguez, Ana'
            ],
            [
                'materia' => 'Microcontroladores',
                'tipo_evaluacion' => 'parcial',
                'nota' => '7.5',
                'fecha_evaluacion' => date('Y-m-d', strtotime('-15 days')),
                'periodo' => '1er_cuatrimestre',
                'profesor' => 'Prof. Martínez, Carlos'
            ]
        ];
    }
    
    if (empty($asistencias)) {
        $asistencias = [
            [
                'materia' => 'Circuitos Analógicos I',
                'total_clases' => 20,
                'presentes' => 18,
                'ausentes' => 2,
                'tardanzas' => 0,
                'porcentaje_asistencia' => 90.0
            ],
            [
                'materia' => 'Circuitos Digitales I',
                'total_clases' => 18,
                'presentes' => 17,
                'ausentes' => 1,
                'tardanzas' => 0,
                'porcentaje_asistencia' => 94.4
            ],
            [
                'materia' => 'Microcontroladores',
                'total_clases' => 16,
                'presentes' => 15,
                'ausentes' => 0,
                'tardanzas' => 1,
                'porcentaje_asistencia' => 93.8
            ]
        ];
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'estudiante' => $estudiante,
            'calificaciones' => $calificaciones,
            'asistencias' => $asistencias
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Student Data API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'no_session'
        ]
    ]);
}
