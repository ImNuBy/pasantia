<?php
/**
 * EPA 703 - Dashboard Data API
 * Proporciona datos estadísticos para el dashboard de administración
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos de admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    // Obtener estadísticas principales
    $stats = obtenerEstadisticasPrincipales($pdo);
    
    // Obtener notificaciones
    $notifications = obtenerNotificaciones($pdo);
    
    // Obtener actividad reciente
    $activity = obtenerActividadReciente($pdo);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'notifications' => $notifications,
        'activity' => $activity
    ]);
    
} catch (Exception $e) {
    error_log("Error en dashboard-data.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener estadísticas principales del dashboard
 */
function obtenerEstadisticasPrincipales($pdo) {
    $stats = [];
    
    try {
        // Total de usuarios
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
        $stats['total_usuarios'] = $stmt->fetchColumn();
        
        // Total de estudiantes activos
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM usuarios u 
            INNER JOIN estudiantes e ON u.id = e.usuario_id 
            WHERE u.activo = 1 AND e.estado = 'activo'
        ");
        $stats['total_estudiantes'] = $stmt->fetchColumn();
        
        // Consultas pendientes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE estado = 'pendiente'
        ");
        $stats['consultas_pendientes'] = $stmt->fetchColumn();
        
        // Inscripciones nuevas (último mes)
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM inscripciones 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            AND estado_inscripcion = 'pendiente'
        ");
        $stats['inscripciones_nuevas'] = $stmt->fetchColumn();
        
        // Estadísticas adicionales
        
        // Total de profesores
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM usuarios u
            INNER JOIN profesores p ON u.id = p.usuario_id 
            WHERE u.activo = 1 AND p.estado = 'activo'
        ");
        $stats['total_profesores'] = $stmt->fetchColumn();
        
        // Consultas respondidas este mes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE estado = 'respondida' 
            AND fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stats['consultas_respondidas_mes'] = $stmt->fetchColumn();
        
        // Nuevos usuarios esta semana
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM usuarios 
            WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ");
        $stats['usuarios_nuevos_semana'] = $stmt->fetchColumn();
        
        // Inscripciones aprobadas este mes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM inscripciones 
            WHERE estado_inscripcion = 'aprobada'
            AND fecha_procesamiento >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stats['inscripciones_aprobadas_mes'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
        // Devolver valores por defecto en caso de error
        $stats = [
            'total_usuarios' => 0,
            'total_estudiantes' => 0,
            'consultas_pendientes' => 0,
            'inscripciones_nuevas' => 0,
            'total_profesores' => 0,
            'consultas_respondidas_mes' => 0,
            'usuarios_nuevos_semana' => 0,
            'inscripciones_aprobadas_mes' => 0
        ];
    }
    
    return $stats;
}

/**
 * Obtener notificaciones importantes
 */
function obtenerNotificaciones($pdo) {
    $notifications = [];
    
    try {
        // Consultas urgentes sin responder
        $stmt = $pdo->query("
            SELECT 
                id,
                nombre,
                asunto,
                prioridad,
                created_at
            FROM contactos 
            WHERE estado = 'pendiente' 
            AND prioridad IN ('urgente', 'alta')
            ORDER BY 
                CASE prioridad
                    WHEN 'urgente' THEN 1
                    WHEN 'alta' THEN 2
                    ELSE 3
                END,
                created_at ASC
            LIMIT 5
        ");
        
        $consultasUrgentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($consultasUrgentes as $consulta) {
            $notifications[] = [
                'id' => $consulta['id'],
                'type' => 'consulta_urgente',
                'title' => 'Consulta ' . ucfirst($consulta['prioridad']),
                'message' => "De: {$consulta['nombre']} - {$consulta['asunto']}",
                'time' => timeAgo($consulta['created_at']),
                'priority' => $consulta['prioridad']
            ];
        }
        
        // Inscripciones pendientes de más de 3 días
        $stmt = $pdo->query("
            SELECT 
                id,
                nombre,
                apellido,
                created_at
            FROM inscripciones 
            WHERE estado_inscripcion = 'pendiente' 
            AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY created_at ASC
            LIMIT 3
        ");
        
        $inscripcionesPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($inscripcionesPendientes as $inscripcion) {
            $notifications[] = [
                'id' => $inscripcion['id'],
                'type' => 'inscripcion_pendiente',
                'title' => 'Inscripción Pendiente',
                'message' => "De: {$inscripcion['nombre']} {$inscripcion['apellido']}",
                'time' => timeAgo($inscripcion['created_at']),
                'priority' => 'media'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo notificaciones: " . $e->getMessage());
    }
    
    return $notifications;
}

/**
 * Obtener actividad reciente del sistema
 */
function obtenerActividadReciente($pdo) {
    $activity = [];
    
    try {
        // Últimos usuarios registrados
        $stmt = $pdo->query("
            SELECT 
                'nuevo_usuario' as tipo,
                CONCAT(nombre, ' ', apellido) as detalle,
                tipo_usuario,
                fecha_registro as fecha
            FROM usuarios 
            WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY fecha_registro DESC
            LIMIT 3
        ");
        
        $nuevosUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($nuevosUsuarios as $usuario) {
            $activity[] = [
                'icon' => getTipoUsuarioIcon($usuario['tipo_usuario']),
                'message' => "<strong>Nuevo {$usuario['tipo_usuario']}:</strong> {$usuario['detalle']}",
                'time' => timeAgo($usuario['fecha'])
            ];
        }
        
        // Consultas recientes respondidas
        $stmt = $pdo->query("
            SELECT 
                nombre,
                asunto,
                fecha_respuesta
            FROM contactos 
            WHERE estado = 'respondida' 
            AND fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY fecha_respuesta DESC
            LIMIT 3
        ");
        
        $consultasRespondidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($consultasRespondidas as $consulta) {
            $activity[] = [
                'icon' => '💬',
                'message' => "<strong>Consulta respondida:</strong> {$consulta['asunto']}",
                'time' => timeAgo($consulta['fecha_respuesta'])
            ];
        }
        
        // Inscripciones procesadas
        $stmt = $pdo->query("
            SELECT 
                nombre,
                apellido,
                estado_inscripcion,
                fecha_procesamiento
            FROM inscripciones 
            WHERE estado_inscripcion IN ('aprobada', 'rechazada')
            AND fecha_procesamiento >= DATE_SUB(NOW(), INTERVAL 5 DAY)
            ORDER BY fecha_procesamiento DESC
            LIMIT 2
        ");
        
        $inscripcionesProcesadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($inscripcionesProcesadas as $inscripcion) {
            $estado = $inscripcion['estado_inscripcion'] === 'aprobada' ? 'aprobada' : 'rechazada';
            $activity[] = [
                'icon' => $estado === 'aprobada' ? '✅' : '❌',
                'message' => "<strong>Inscripción {$estado}:</strong> {$inscripcion['nombre']} {$inscripcion['apellido']}",
                'time' => timeAgo($inscripcion['fecha_procesamiento'])
            ];
        }
        
        // Ordenar por fecha más reciente
        usort($activity, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });
        
        // Limitar a los 8 más recientes
        $activity = array_slice($activity, 0, 8);
        
    } catch (Exception $e) {
        error_log("Error obteniendo actividad reciente: " . $e->getMessage());
    }
    
    return $activity;
}

/**
 * Obtener icono según tipo de usuario
 */
function getTipoUsuarioIcon($tipo) {
    $iconos = [
        'estudiante' => '🎓',
        'profesor' => '👨‍🏫',
        'admin' => '👤',
        'secretario' => '📝'
    ];
    
    return $iconos[$tipo] ?? '👤';
}

/**
 * Convertir fecha a formato "hace X tiempo"
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace menos de 1 minuto';
    if ($time < 3600) return 'Hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'Hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'Hace ' . floor($time/86400) . ' días';
    if ($time < 31536000) return 'Hace ' . floor($time/2592000) . ' meses';
    
    return 'Hace ' . floor($time/31536000) . ' años';
}
?>