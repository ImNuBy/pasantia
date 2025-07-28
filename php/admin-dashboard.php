<?php
/**
 * Panel de Administración EPA 703
 * Dashboard principal para administradores - Solo lógica PHP
 */

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

require_once '../config/database-config.php';

try {
    $pdo = getDBConnection();
    
    // Obtener estadísticas generales
    $stats = obtenerEstadisticasGenerales($pdo);
    $actividad_reciente = obtenerActividadReciente($pdo);
    $consultas_pendientes = obtenerConsultasPendientes($pdo);
    $usuarios_recientes = obtenerUsuariosRecientes($pdo);
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Error al cargar datos del dashboard";
    
    // Datos por defecto en caso de error
    $stats = [
        'usuarios_por_tipo' => [],
        'consultas' => ['pendientes' => 0, 'hoy' => 0],
        'inscripciones' => ['pendientes' => 0, 'aprobadas' => 0],
        'academico' => ['cursos_activos' => 0, 'total_orientaciones' => 0],
        'estudiantes_por_estado' => ['activo' => 0, 'graduado' => 0]
    ];
    $actividad_reciente = [];
    $consultas_pendientes = [];
    $usuarios_recientes = [];
}

/**
 * Obtener estadísticas generales del sistema
 */
function obtenerEstadisticasGenerales($pdo) {
    $stats = [];
    
    // Total de usuarios por tipo
    $stmt = $pdo->query("
        SELECT 
            tipo_usuario,
            COUNT(*) as total,
            COUNT(CASE WHEN activo = 1 THEN 1 END) as activos
        FROM usuarios 
        GROUP BY tipo_usuario
    ");
    $stats['usuarios_por_tipo'] = $stmt->fetchAll();
    
    // Estadísticas de consultas
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_consultas,
            COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as hoy,
            COUNT(CASE WHEN tipo_consulta = 'inscripcion' THEN 1 END) as inscripciones
        FROM contactos
    ");
    $stats['consultas'] = $stmt->fetch();
    
    // Estadísticas de inscripciones
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_inscripciones,
            COUNT(CASE WHEN estado_inscripcion = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado_inscripcion = 'aprobada' THEN 1 END) as aprobadas,
            COUNT(CASE WHEN estado_inscripcion = 'rechazada' THEN 1 END) as rechazadas
        FROM inscripciones
    ");
    $stats['inscripciones'] = $stmt->fetch();
    
    // Cursos y orientaciones
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_cursos,
            COUNT(DISTINCT o.id) as total_orientaciones,
            COUNT(DISTINCT CASE WHEN c.activo = 1 THEN c.id END) as cursos_activos
        FROM cursos c
        LEFT JOIN orientaciones o ON c.orientacion_id = o.id
    ");
    $stats['academico'] = $stmt->fetch();
    
    // Estudiantes por estado
    $stmt = $pdo->query("
        SELECT 
            estado,
            COUNT(*) as cantidad
        FROM estudiantes
        GROUP BY estado
    ");
    $stats['estudiantes_por_estado'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    return $stats;
}

/**
 * Obtener actividad reciente del sistema
 */
function obtenerActividadReciente($pdo) {
    $stmt = $pdo->query("
        SELECT 
            'usuario_creado' as tipo,
            CONCAT(u.nombre, ' ', u.apellido) as descripcion,
            u.tipo_usuario as detalle,
            u.created_at as fecha
        FROM usuarios u
        WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'consulta_recibida' as tipo,
            CONCAT('Nueva consulta de ', c.nombre) as descripcion,
            c.tipo_consulta as detalle,
            c.created_at as fecha
        FROM contactos c
        WHERE DATE(c.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'inscripcion_procesada' as tipo,
            CONCAT('Inscripción ', i.estado_inscripcion, ' para ', co.nombre) as descripcion,
            co.email as detalle,
            i.fecha_procesamiento as fecha
        FROM inscripciones i
        INNER JOIN contactos co ON i.contacto_id = co.id
        WHERE DATE(i.fecha_procesamiento) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        ORDER BY fecha DESC
        LIMIT 15
    ");
    
    return $stmt->fetchAll();
}

/**
 * Obtener consultas pendientes urgentes
 */
function obtenerConsultasPendientes($pdo) {
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.nombre,
            c.email,
            c.tipo_consulta,
            c.mensaje,
            c.created_at,
            TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as horas_pendiente,
            i.estado_inscripcion
        FROM contactos c
        LEFT JOIN inscripciones i ON c.id = i.contacto_id
        WHERE c.estado = 'pendiente'
        ORDER BY 
            CASE WHEN c.tipo_consulta = 'inscripcion' THEN 1 ELSE 2 END,
            c.created_at ASC
        LIMIT 10
    ");
    
    return $stmt->fetchAll();
}

/**
 * Obtener usuarios registrados recientemente
 */
function obtenerUsuariosRecientes($pdo) {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.nombre,
            u.apellido,
            u.email,
            u.tipo_usuario,
            u.activo,
            u.created_at,
            CASE 
                WHEN u.tipo_usuario = 'estudiante' THEN e.legajo
                WHEN u.tipo_usuario = 'profesor' THEN p.legajo
                ELSE NULL
            END as legajo,
            CASE 
                WHEN u.tipo_usuario = 'estudiante' THEN c.nombre
                ELSE NULL
            END as curso_nombre
        FROM usuarios u
        LEFT JOIN estudiantes e ON u.id = e.usuario_id
        LEFT JOIN profesores p ON u.id = p.usuario_id
        LEFT JOIN cursos c ON e.curso_id = c.id
        WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY u.created_at DESC
        LIMIT 15
    ");
    
    return $stmt->fetchAll();
}

/**
 * Función auxiliar para tiempo relativo
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace un momento';
    if ($time < 3600) return 'Hace ' . floor($time / 60) . ' minutos';
    if ($time < 86400) return 'Hace ' . floor($time / 3600) . ' horas';
    if ($time < 2592000) return 'Hace ' . floor($time / 86400) . ' días';
    
    return date('d/m/Y', strtotime($datetime));
}

/**
 * Función auxiliar para obtener icono de usuario
 */
function getUserIcon($tipo_usuario) {
    $icons = [
        'estudiante' => 'user-graduate',
        'profesor' => 'chalkboard-teacher',
        'admin' => 'user-shield',
        'secretario' => 'user-tie'
    ];
    return $icons[$tipo_usuario] ?? 'user';
}

/**
 * Función auxiliar para obtener icono de actividad
 */
function getActivityIcon($tipo) {
    $icons = [
        'usuario_creado' => 'user-plus',
        'consulta_recibida' => 'envelope',
        'inscripcion_procesada' => 'check-circle'
    ];
    return $icons[$tipo] ?? 'circle';
}

/**
 * Función auxiliar para obtener color de badge por tipo de usuario
 */
function getBadgeColorByUserType($tipo_usuario) {
    $colors = [
        'estudiante' => '#28a745',
        'profesor' => '#6c757d',
        'admin' => '#ffc107',
        'secretario' => '#17a2b8'
    ];
    return $colors[$tipo_usuario] ?? '#6c757d';
}

/**
 * Función auxiliar para obtener color de actividad
 */
function getActivityColor($tipo) {
    $colors = [
        'usuario_creado' => '#28a745',
        'consulta_recibida' => '#17a2b8',
        'inscripcion_procesada' => '#1e3a2e'
    ];
    return $colors[$tipo] ?? '#6c757d';
}

// Incluir la vista HTML
include 'dashboard-view.html';
?>