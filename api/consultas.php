<?php
/**
 * EPA 703 - Consultas API
 * Gestión de consultas para el panel de administración
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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                obtenerEstadisticasConsultas($pdo);
            } else {
                obtenerConsultas($pdo);
            }
            break;
        case 'POST':
            procesarConsulta($pdo);
            break;
        case 'PUT':
            actualizarConsulta($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en consultas.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener lista de consultas
 */
function obtenerConsultas($pdo) {
    try {
        // Parámetros de filtrado
        $estado = $_GET['estado'] ?? '';
        $tipo = $_GET['tipo'] ?? '';
        $prioridad = $_GET['prioridad'] ?? '';
        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';
        $limite = $_GET['limite'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Construir consulta base
        $sql = "
            SELECT 
                c.id,
                c.nombre,
                c.apellido,
                c.email,
                c.telefono,
                c.asunto,
                c.mensaje,
                c.tipo_consulta,
                c.estado,
                c.prioridad,
                c.respondida_por,
                c.fecha_respuesta,
                c.respuesta,
                c.created_at,
                c.updated_at,
                CONCAT(u.nombre, ' ', u.apellido) as respondida_por_nombre
            FROM contactos c
            LEFT JOIN usuarios u ON c.respondida_por = u.id
        ";
        
        $conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($estado)) {
            $conditions[] = "c.estado = :estado";
            $params['estado'] = $estado;
        }
        
        if (!empty($tipo)) {
            $conditions[] = "c.tipo_consulta = :tipo";
            $params['tipo'] = $tipo;
        }
        
        if (!empty($prioridad)) {
            $conditions[] = "c.prioridad = :prioridad";
            $params['prioridad'] = $prioridad;
        }
        
        if (!empty($desde)) {
            $conditions[] = "DATE(c.created_at) >= :desde";
            $params['desde'] = $desde;
        }
        
        if (!empty($hasta)) {
            $conditions[] = "DATE(c.created_at) <= :hasta";
            $params['hasta'] = $hasta;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY 
            CASE c.prioridad
                WHEN 'urgente' THEN 1
                WHEN 'alta' THEN 2
                WHEN 'media' THEN 3
                WHEN 'baja' THEN 4
                ELSE 5
            END,
            c.created_at DESC 
            LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total de consultas
        $sqlCount = "SELECT COUNT(*) FROM contactos c";
        if (!empty($conditions)) {
            $sqlCount .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmtCount = $pdo->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(":{$key}", $value);
        }
        $stmtCount->execute();
        $totalConsultas = $stmtCount->fetchColumn();
        
        // Obtener estadísticas básicas
        $stats = obtenerEstadisticasBasicas($pdo);
        
        echo json_encode([
            'success' => true,
            'consultas' => $consultas,
            'stats' => $stats,
            'total' => $totalConsultas,
            'limite' => (int)$limite,
            'offset' => (int)$offset
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo consultas: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener estadísticas básicas de consultas
 */
function obtenerEstadisticasBasicas($pdo) {
    try {
        $stats = [];
        
        // Total de consultas
        $stmt = $pdo->query("SELECT COUNT(*) FROM contactos");
        $stats['total'] = $stmt->fetchColumn();
        
        // Consultas pendientes
        $stmt = $pdo->query("SELECT COUNT(*) FROM contactos WHERE estado = 'pendiente'");
        $stats['pendientes'] = $stmt->fetchColumn();
        
        // Consultas respondidas
        $stmt = $pdo->query("SELECT COUNT(*) FROM contactos WHERE estado = 'respondida'");
        $stats['respondidas'] = $stmt->fetchColumn();
        
        // Consultas de hoy
        $stmt = $pdo->query("SELECT COUNT(*) FROM contactos WHERE DATE(created_at) = CURDATE()");
        $stats['hoy'] = $stmt->fetchColumn();
        
        // Consultas por prioridad
        $stmt = $pdo->query("
            SELECT 
                prioridad,
                COUNT(*) as cantidad
            FROM contactos 
            WHERE estado = 'pendiente'
            GROUP BY prioridad
        ");
        $prioridades = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['por_prioridad'] = [
            'urgente' => $prioridades['urgente'] ?? 0,
            'alta' => $prioridades['alta'] ?? 0,
            'media' => $prioridades['media'] ?? 0,
            'baja' => $prioridades['baja'] ?? 0
        ];
        
        // Consultas por tipo
        $stmt = $pdo->query("
            SELECT 
                tipo_consulta,
                COUNT(*) as cantidad
            FROM contactos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY tipo_consulta
        ");
        $tipos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['por_tipo'] = [
            'general' => $tipos['general'] ?? 0,
            'inscripcion' => $tipos['inscripcion'] ?? 0,
            'academica' => $tipos['academica'] ?? 0,
            'administrativa' => $tipos['administrativa'] ?? 0
        ];
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas básicas: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener estadísticas detalladas de consultas
 */
function obtenerEstadisticasConsultas($pdo) {
    try {
        $stats = obtenerEstadisticasBasicas($pdo);
        
        // Tiempo promedio de respuesta
        $stmt = $pdo->query("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, created_at, fecha_respuesta)) as promedio_horas
            FROM contactos 
            WHERE estado = 'respondida' 
            AND fecha_respuesta IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $promedioHoras = $stmt->fetchColumn();
        $stats['tiempo_promedio_respuesta'] = round($promedioHoras ?? 0, 1);
        
        // Consultas por día (últimos 7 días)
        $stmt = $pdo->query("
            SELECT 
                DATE(created_at) as fecha,
                COUNT(*) as cantidad
            FROM contactos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY fecha ASC
        ");
        $consultasPorDia = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['consultas_por_dia'] = $consultasPorDia;
        
        // Top 5 consultas más frecuentes por asunto
        $stmt = $pdo->query("
            SELECT 
                asunto,
                COUNT(*) as frecuencia
            FROM contactos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY asunto
            ORDER BY frecuencia DESC
            LIMIT 5
        ");
        $consultasFrecuentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['consultas_frecuentes'] = $consultasFrecuentes;
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas detalladas: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Procesar consulta (responder, cambiar estado, etc.)
 */
function procesarConsulta($pdo) {
    try {
        $accion = $_POST['accion'] ?? '';
        $consultaId = $_POST['consulta_id'] ?? '';
        
        if (empty($consultaId)) {
            throw new Exception("ID de consulta requerido");
        }
        
        // Verificar que la consulta existe
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ?");
        $stmt->execute([$consultaId]);
        $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$consulta) {
            throw new Exception("Consulta no encontrada");
        }
        
        switch ($accion) {
            case 'responder':
                responderConsulta($pdo, $consultaId, $consulta);
                break;
            case 'cambiar_prioridad':
                cambiarPrioridad($pdo, $consultaId);
                break;
            case 'cambiar_estado':
                cambiarEstado($pdo, $consultaId);
                break;
            default:
                throw new Exception("Acción no válida");
        }
        
    } catch (Exception $e) {
        error_log("Error procesando consulta: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Responder a una consulta
 */
function responderConsulta($pdo, $consultaId, $consulta) {
    $respuesta = $_POST['respuesta'] ?? '';
    $enviarEmail = $_POST['enviar_email'] ?? true;
    
    if (empty($respuesta)) {
        throw new Exception("La respuesta es obligatoria");
    }
    
    // Actualizar consulta
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET 
            estado = 'respondida',
            respuesta = ?,
            respondida_por = ?,
            fecha_respuesta = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$respuesta, $_SESSION['user_id'], $consultaId]);
    
    // Enviar email de respuesta si está habilitado
    if ($enviarEmail) {
        enviarEmailRespuesta($consulta, $respuesta);
    }
    
    // Log de actividad
    error_log("Consulta respondida ID: {$consultaId} por admin ID: {$_SESSION['user_id']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Consulta respondida exitosamente'
    ]);
}

/**
 * Cambiar prioridad de una consulta
 */
function cambiarPrioridad($pdo, $consultaId) {
    $nuevaPrioridad = $_POST['prioridad'] ?? '';
    
    $prioridadesValidas = ['baja', 'media', 'alta', 'urgente'];
    if (!in_array($nuevaPrioridad, $prioridadesValidas)) {
        throw new Exception("Prioridad no válida");
    }
    
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET prioridad = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$nuevaPrioridad, $consultaId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Prioridad actualizada exitosamente'
    ]);
}

/**
 * Cambiar estado de una consulta
 */
function cambiarEstado($pdo, $consultaId) {
    $nuevoEstado = $_POST['estado'] ?? '';
    
    $estadosValidos = ['pendiente', 'respondida', 'cerrada'];
    if (!in_array($nuevoEstado, $estadosValidos)) {
        throw new Exception("Estado no válido");
    }
    
    $updateFields = ['estado = ?', 'updated_at = NOW()'];
    $params = [$nuevoEstado];
    
    // Si se marca como cerrada, registrar quien la cerró
    if ($nuevoEstado === 'cerrada') {
        $updateFields[] = 'respondida_por = ?';
        $params[] = $_SESSION['user_id'];
    }
    
    $params[] = $consultaId;
    
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET " . implode(', ', $updateFields) . "
        WHERE id = ?
    ");
    
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente'
    ]);
}

/**
 * Enviar email de respuesta
 */
function enviarEmailRespuesta($consulta, $respuesta) {
    try {
        // Aquí integrarías con tu sistema de emails
        // Por ejemplo, usando PHPMailer o similar
        
        $asunto = "Re: " . $consulta['asunto'];
        $mensaje = "
        <html>
        <body>
            <h2>EPA 703 - Respuesta a su consulta</h2>
            
            <p>Estimado/a {$consulta['nombre']},</p>
            
            <p>Gracias por contactarse con nosotros. A continuación encontrará la respuesta a su consulta:</p>
            
            <div style='background: #f5f5f5; padding: 15px; border-left: 4px solid #4a7c59; margin: 20px 0;'>
                <strong>Su consulta:</strong><br>
                {$consulta['mensaje']}
            </div>
            
            <div style='background: #e8f5e8; padding: 15px; border-left: 4px solid #38a169; margin: 20px 0;'>
                <strong>Nuestra respuesta:</strong><br>
                {$respuesta}
            </div>
            
            <p>Si tiene más consultas, no dude en contactarnos.</p>
            
            <p>Saludos cordiales,<br>
            <strong>Equipo EPA 703</strong></p>
            
            <hr>
            <small>
                EPA 703 - Escuela Primaria para Adultos<br>
                Email: info@epa703.edu.ar<br>
                Teléfono: +54 11 1234-5678
            </small>
        </body>
        </html>
        ";
        
        // Log del intento de envío
        error_log("Email de respuesta preparado para: {$consulta['email']}");
        
        // Aquí llamarías a tu función de envío de emails
        // enviarEmail($consulta['email'], $asunto, $mensaje);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error enviando email de respuesta: " . $e->getMessage());
        return false;
    }
}

/**
 * Actualizar consulta (para ediciones menores)
 */
function actualizarConsulta($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de consulta requerido");
        }
        
        $consultaId = $input['id'];
        
        // Verificar que la consulta existe
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ?");
        $stmt->execute([$consultaId]);
        $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$consulta) {
            throw new Exception("Consulta no encontrada");
        }
        
        // Campos actualizables
        $updates = [];
        $params = [];
        
        $camposActualizables = ['prioridad', 'tipo_consulta', 'estado'];
        
        foreach ($camposActualizables as $campo) {
            if (isset($input[$campo])) {
                $updates[] = "{$campo} = ?";
                $params[] = $input[$campo];
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $consultaId;
        
        $sql = "UPDATE contactos SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Consulta actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando consulta: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>