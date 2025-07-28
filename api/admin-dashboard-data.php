<?php
/**
 * API para datos del Dashboard EPA 703
 * Endpoint para obtener datos actualizados en tiempo real
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit();
}

try {
    require_once '../../config/database-config.php';
    $pdo = getDBConnection();
    
    // Obtener todos los datos necesarios
    $data = [
        'stats' => obtenerEstadisticas($pdo),
        'recent_activity' => obtenerActividadReciente($pdo),
        'pending_consultations' => obtenerConsultasPendientes($pdo),
        'recent_users' => obtenerUsuariosRecientes($pdo),
        'charts_data' => obtenerDatosGraficos($pdo),
        'system_info' => obtenerInfoSistema($pdo),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener estadísticas generales
 */
function obtenerEstadisticas($pdo) {
    $stats = [];
    
    // Usuarios por tipo
    $stmt = $pdo->query("
        SELECT 
            tipo_usuario,
            COUNT(*) as total,
            COUNT(CASE WHEN activo = 1 THEN 1 END) as activos
        FROM usuarios 
        GROUP BY tipo_usuario
    ");
    $usuarios_por_tipo = $stmt->fetchAll();
    
    $stats['total_users'] = array_sum(array_column($usuarios_por_tipo, 'total'));
    $stats['active_users'] = array_sum(array_column($usuarios_por_tipo, 'activos'));
    
    // Estudiantes específicamente
    $estudiantes = array_filter($usuarios_por_tipo, function($item) {
        return $item['tipo_usuario'] === 'estudiante';
    });
    $stats['students'] = !empty($estudiantes) ? reset($estudiantes)['total'] : 0;
    
    // Profesores específicamente
    $profesores = array_filter($usuarios_por_tipo, function($item) {
        return $item['tipo_usuario'] === 'profesor';
    });
    $stats['teachers'] = !empty($profesores) ? reset($profesores)['total'] : 0;
    
    // Consultas
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
            COUNT(CASE WHEN tipo_consulta = 'inscripcion' THEN 1 END) as inscripciones,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as esta_semana
        FROM contactos
    ");
    $consultas_stats = $stmt->fetch();
    
    $stats['total_consultations'] = $consultas_stats['total'];
    $stats['pending_consultations'] = $consultas_stats['pendientes'];
    $stats['consultations_today'] = $consultas_stats['today'];
    $stats['consultations_this_week'] = $consultas_stats['esta_semana'];
    $stats['enrollment_requests'] = $consultas_stats['inscripciones'];
    
    // Inscripciones procesadas
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN estado_inscripcion = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado_inscripcion = 'aprobada' THEN 1 END) as aprobadas,
            COUNT(CASE WHEN estado_inscripcion = 'rechazada' THEN 1 END) as rechazadas
        FROM inscripciones
    ");
    $inscripciones_stats = $stmt->fetch();
    
    $stats['pending_enrollments'] = $inscripciones_stats['pendientes'] ?? 0;
    $stats['approved_enrollments'] = $inscripciones_stats['aprobadas'] ?? 0;
    $stats['rejected_enrollments'] = $inscripciones_stats['rechazadas'] ?? 0;
    
    // Tasa de aprobación
    $total_procesadas = $stats['approved_enrollments'] + $stats['rejected_enrollments'];
    $stats['approval_rate'] = $total_procesadas > 0 ? 
        round($stats['approved_enrollments'] / $total_procesadas * 100, 1) : 0;
    
    // Cursos y académico
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_cursos,
            COUNT(DISTINCT o.id) as total_orientaciones,
            COUNT(DISTINCT CASE WHEN c.activo = 1 THEN c.id END) as cursos_activos
        FROM cursos c
        LEFT JOIN orientaciones o ON c.orientacion_id = o.id
    ");
    $academico_stats = $stmt->fetch();
    
    $stats['total_courses'] = $academico_stats['total_cursos'];
    $stats['active_courses'] = $academico_stats['cursos_activos'];
    $stats['total_orientations'] = $academico_stats['total_orientaciones'];
    
    // Estudiantes por estado
    $stmt = $pdo->query("
        SELECT 
            estado,
            COUNT(*) as cantidad
        FROM estudiantes
        GROUP BY estado
    ");
    $estudiantes_estado = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats['active_students'] = $estudiantes_estado['activo'] ?? 0;
    $stats['graduated_students'] = $estudiantes_estado['graduado'] ?? 0;
    $stats['inactive_students'] = $estudiantes_estado['inactivo'] ?? 0;
    
    return $stats;
}

/**
 * Obtener actividad reciente del sistema
 */
function obtenerActividadReciente($pdo) {
    $stmt = $pdo->query("
        SELECT 
            'usuario_creado' as type,
            CONCAT('Nuevo usuario: ', u.nombre, ' ', u.apellido) as description,
            u.tipo_usuario as detail,
            u.created_at as date
        FROM usuarios u
        WHERE DATE(u.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'consulta_recibida' as type,
            CONCAT('Consulta de ', c.nombre, ' sobre ', c.tipo_consulta) as description,
            c.email as detail,
            c.created_at as date
        FROM contactos c
        WHERE DATE(c.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'inscripcion_procesada' as type,
            CONCAT('Inscripción ', i.estado_inscripcion, ': ', co.nombre) as description,
            CONCAT(co.email, ' - ', cur.nombre) as detail,
            i.fecha_procesamiento as date
        FROM inscripciones i
        INNER JOIN contactos co ON i.contacto_id = co.id
        LEFT JOIN cursos cur ON i.curso_asignado_id = cur.id
        WHERE DATE(i.fecha_procesamiento) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        ORDER BY date DESC
        LIMIT 20
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
        LIMIT 15
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
        LIMIT 20
    ");
    
    return $stmt->fetchAll();
}

/**
 * Obtener datos para gráficos
 */
function obtenerDatosGraficos($pdo) {
    $graficos = [];
    
    // Consultas por día (últimos 30 días)
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as fecha,
            COUNT(*) as total,
            COUNT(CASE WHEN tipo_consulta = 'inscripcion' THEN 1 END) as inscripciones
        FROM contactos
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY fecha ASC
    ");
    $graficos['consultas_diarias'] = $stmt->fetchAll();
    
    // Usuarios por tipo (para gráfico de torta)
    $stmt = $pdo->query("
        SELECT 
            tipo_usuario as label,
            COUNT(*) as value
        FROM usuarios
        WHERE activo = 1
        GROUP BY tipo_usuario
    ");
    $graficos['usuarios_por_tipo'] = $stmt->fetchAll();
    
    // Inscripciones por mes (últimos 12 meses)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(fecha_procesamiento, '%Y-%m') as mes,
            COUNT(CASE WHEN estado_inscripcion = 'aprobada' THEN 1 END) as aprobadas,
            COUNT(CASE WHEN estado_inscripcion = 'rechazada' THEN 1 END) as rechazadas
        FROM inscripciones
        WHERE fecha_procesamiento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(fecha_procesamiento, '%Y-%m')
        ORDER BY mes ASC
    ");
    $graficos['inscripciones_mensuales'] = $stmt->fetchAll();
    
    // Tipos de consulta más frecuentes
    $stmt = $pdo->query("
        SELECT 
            tipo_consulta as label,
            COUNT(*) as value
        FROM contactos
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY tipo_consulta
        ORDER BY value DESC
    ");
    $graficos['tipos_consulta'] = $stmt->fetchAll();
    
    return $graficos;
}

/**
 * Obtener información del sistema
 */
function obtenerInfoSistema($pdo) {
    $info = [];
    
    // Última actividad de administradores
    $stmt = $pdo->query("
        SELECT 
            u.nombre,
            u.apellido,
            MAX(s.updated_at) as ultima_actividad
        FROM usuarios u
        INNER JOIN sesiones s ON u.id = s.usuario_id
        WHERE u.tipo_usuario = 'admin' AND u.activo = 1
        GROUP BY u.id
        ORDER BY ultima_actividad DESC
        LIMIT 5
    ");
    $info['admin_activity'] = $stmt->fetchAll();
    
    // Estadísticas de la base de datos
    $stmt = $pdo->query("
        SELECT 
            table_name,
            table_rows
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name IN ('usuarios', 'contactos', 'inscripciones', 'estudiantes', 'profesores', 'cursos')
    ");
    $info['database_stats'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Uso del sistema (emails enviados)
    $stmt = $pdo->query("
        SELECT 
            tipo_email,
            COUNT(*) as cantidad,
            COUNT(CASE WHEN estado_envio = 'enviado' THEN 1 END) as exitosos
        FROM emails_log
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY tipo_email
    ");
    $info['email_stats'] = $stmt->fetchAll();
    
    // Configuración actual
    $stmt = $pdo->query("
        SELECT clave, valor
        FROM configuracion
        WHERE categoria = 'general'
        ORDER BY clave
    ");
    $info['system_config'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    return $info;
}
?>