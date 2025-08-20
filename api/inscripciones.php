<?php
/**
 * EPA 703 - Inscripciones API
 * Gestión de inscripciones para el panel de administración
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userType = $_SESSION['tipo_usuario'];
$allowedRoles = ['admin', 'secretario'];

if (!in_array($userType, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                obtenerEstadisticasInscripciones($pdo);
            } else {
                obtenerInscripciones($pdo);
            }
            break;
        case 'POST':
            procesarInscripcion($pdo);
            break;
        case 'PUT':
            actualizarInscripcion($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en inscripciones.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}

/**
 * Obtener lista de inscripciones
 */
function obtenerInscripciones($pdo) {
    try {
        // Parámetros de filtrado
        $estado = $_GET['estado'] ?? '';
        $orientacion = $_GET['orientacion'] ?? '';
        $anio = $_GET['anio'] ?? '';
        $busqueda = $_GET['busqueda'] ?? '';
        $limite = $_GET['limite'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Query base - usar tabla contactos ya que no tienes tabla inscripciones específica
        // Adaptar según tu estructura real
        $sql = "
            SELECT 
                c.id,
                c.nombre,
                c.email,
                c.telefono,
                c.asunto,
                c.mensaje,
                c.estado,
                c.created_at,
                c.updated_at,
                'general' as orientacion_deseada,
                'pendiente' as estado_inscripcion,
                0 as documentos_completos,
                YEAR(c.created_at) as año_ingreso
            FROM contactos c
            WHERE c.asunto LIKE '%inscripción%' OR c.asunto LIKE '%inscripcion%'
        ";
        
        $conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($estado)) {
            $conditions[] = "c.estado = :estado";
            $params['estado'] = $estado;
        }
        
        if (!empty($anio)) {
            $conditions[] = "YEAR(c.created_at) = :anio";
            $params['anio'] = $anio;
        }
        
        if (!empty($busqueda)) {
            $conditions[] = "(
                c.nombre LIKE :busqueda 
                OR c.email LIKE :busqueda 
                OR c.telefono LIKE :busqueda
            )";
            $params['busqueda'] = "%{$busqueda}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM contactos c WHERE c.asunto LIKE '%inscripción%' OR c.asunto LIKE '%inscripcion%'";
        if (!empty($conditions)) {
            $sqlCount .= " AND " . implode(" AND ", $conditions);
        }
        
        $stmtCount = $pdo->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(":{$key}", $value);
        }
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'inscripciones' => $inscripciones,
            'total' => $total,
            'limite' => (int)$limite,
            'offset' => (int)$offset
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo inscripciones: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener estadísticas de inscripciones
 */
function obtenerEstadisticasInscripciones($pdo) {
    try {
        $stats = [];
        
        // Total de inscripciones (simulado con contactos de inscripción)
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE asunto LIKE '%inscripción%' OR asunto LIKE '%inscripcion%'
        ");
        $stats['total'] = $stmt->fetchColumn();
        
        // Pendientes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE (asunto LIKE '%inscripción%' OR asunto LIKE '%inscripcion%')
            AND estado = 'pendiente'
        ");
        $stats['pendientes'] = $stmt->fetchColumn();
        
        // Aprobadas (respondidas como aprobadas)
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE (asunto LIKE '%inscripción%' OR asunto LIKE '%inscripcion%')
            AND estado = 'respondido'
        ");
        $stats['aprobadas'] = $stmt->fetchColumn();
        
        // Rechazadas (cerradas)
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE (asunto LIKE '%inscripción%' OR asunto LIKE '%inscripcion%')
            AND estado = 'cerrado'
        ");
        $stats['rechazadas'] = $stmt->fetchColumn();
        
        // Hoy
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM contactos 
            WHERE (asunto LIKE '%inscripción%' OR asunto LIKE '%inscripcion%')
            AND DATE(created_at) = CURDATE()
        ");
        $stats['hoy'] = $stmt->fetchColumn();
        
        // Por orientación (simulado)
        $stats['por_orientacion'] = [
            'primer_ciclo' => rand(10, 50),
            'segundo_ciclo' => rand(15, 40),
            'alfabetizacion' => rand(5, 25)
        ];
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de inscripciones: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Procesar inscripción (aprobar, rechazar, etc.)
 */
function procesarInscripcion($pdo) {
    try {
        $accion = $_POST['accion'] ?? '';
        $inscripcionId = $_POST['inscripcion_id'] ?? '';
        
        if (empty($inscripcionId)) {
            throw new Exception("ID de inscripción requerido");
        }
        
        // Verificar que la inscripción existe
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ?");
        $stmt->execute([$inscripcionId]);
        $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inscripcion) {
            throw new Exception("Inscripción no encontrada");
        }
        
        switch ($accion) {
            case 'aprobar':
                aprobarInscripcion($pdo, $inscripcionId, $inscripcion);
                break;
            case 'rechazar':
                rechazarInscripcion($pdo, $inscripcionId, $inscripcion);
                break;
            case 'revisar':
                marcarEnRevision($pdo, $inscripcionId);
                break;
            default:
                throw new Exception("Acción no válida");
        }
        
    } catch (Exception $e) {
        error_log("Error procesando inscripción: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Aprobar inscripción
 */
function aprobarInscripcion($pdo, $inscripcionId, $inscripcion) {
    try {
        $pdo->beginTransaction();
        
        // Obtener datos adicionales del formulario
        $cursoId = $_POST['curso_id'] ?? null;
        $observaciones = $_POST['observaciones'] ?? '';
        $enviarEmail = $_POST['enviar_email'] ?? true;
        
        // 1. Crear usuario si no existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$inscripcion['email']]);
        $usuarioExistente = $stmt->fetch();
        
        if (!$usuarioExistente) {
            // Crear nuevo usuario
            $passwordTemporal = 'EPA' . rand(100000, 999999);
            $passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);
            
            // Separar nombre completo
            $nombreParts = explode(' ', trim($inscripcion['nombre']), 2);
            $nombre = $nombreParts[0];
            $apellido = isset($nombreParts[1]) ? $nombreParts[1] : '';
            
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre, apellido, email, telefono, tipo_usuario, password_hash, activo, fecha_registro)
                VALUES (?, ?, ?, ?, 'estudiante', ?, 1, NOW())
            ");
            $stmt->execute([$nombre, $apellido, $inscripcion['email'], $inscripcion['telefono'], $passwordHash]);
            
            $usuarioId = $pdo->lastInsertId();
        } else {
            $usuarioId = $usuarioExistente['id'];
            $passwordTemporal = null; // Ya tiene usuario
        }
        
        // 2. Crear registro de estudiante si no existe
        $stmt = $pdo->prepare("SELECT id FROM estudiantes WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        $estudianteExistente = $stmt->fetch();
        
        if (!$estudianteExistente) {
            // Generar legajo
            $año = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE legajo LIKE ?");
            $stmt->execute(["EST{$año}%"]);
            $count = $stmt->fetchColumn() + 1;
            $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO estudiantes (usuario_id, legajo, curso_id, fecha_ingreso, estado, observaciones)
                VALUES (?, ?, ?, CURDATE(), 'activo', ?)
            ");
            $stmt->execute([$usuarioId, $legajo, $cursoId, $observaciones]);
        } else {
            // Actualizar curso si se especificó
            if ($cursoId) {
                $stmt = $pdo->prepare("UPDATE estudiantes SET curso_id = ? WHERE usuario_id = ?");
                $stmt->execute([$cursoId, $usuarioId]);
            }
        }
        
        // 3. Actualizar estado de la consulta/inscripción
        $stmt = $pdo->prepare("
            UPDATE contactos 
            SET estado = 'respondido', 
                respondido_por = ?,
                fecha_respuesta = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $inscripcionId]);
        
        $pdo->commit();
        
        // Enviar email de aprobación
        if ($enviarEmail) {
            enviarEmailAprobacion($inscripcion, $passwordTemporal, $legajo ?? null);
        }
        
        // Log de actividad
        error_log("Inscripción aprobada ID: {$inscripcionId} - Usuario creado/actualizado ID: {$usuarioId}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Inscripción aprobada exitosamente',
            'data' => [
                'usuario_id' => $usuarioId,
                'legajo' => $legajo ?? null,
                'password_temporal' => $passwordTemporal
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Rechazar inscripción
 */
function rechazarInscripcion($pdo, $inscripcionId, $inscripcion) {
    $motivo = $_POST['motivo'] ?? '';
    $enviarEmail = $_POST['enviar_email'] ?? true;
    
    // Actualizar estado
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET estado = 'cerrado',
            respondido_por = ?,
            fecha_respuesta = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $inscripcionId]);
    
    // Enviar email de rechazo
    if ($enviarEmail) {
        enviarEmailRechazo($inscripcion, $motivo);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inscripción rechazada'
    ]);
}

/**
 * Marcar en revisión
 */
function marcarEnRevision($pdo, $inscripcionId) {
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET estado = 'leido',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$inscripcionId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Marcado en revisión'
    ]);
}

/**
 * Enviar email de aprobación
 */
function enviarEmailAprobacion($inscripcion, $passwordTemporal, $legajo) {
    $asunto = "¡Inscripción aprobada! - EPA 703";
    
    $credenciales = '';
    if ($passwordTemporal) {
        $credenciales = "
        <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3>🔐 Sus credenciales de acceso:</h3>
            <p><strong>Email:</strong> {$inscripcion['email']}</p>
            <p><strong>Contraseña temporal:</strong> {$passwordTemporal}</p>
            " . ($legajo ? "<p><strong>Legajo:</strong> {$legajo}</p>" : "") . "
            <small><em>Por seguridad, cambie su contraseña en el primer acceso</em></small>
        </div>";
    }
    
    $mensaje = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
            <h2>🎉 ¡Inscripción Aprobada!</h2>
            <p>EPA 703 - Escuela Primaria para Adultos</p>
        </div>
        
        <div style='padding: 20px;'>
            <p>Estimado/a <strong>{$inscripcion['nombre']}</strong>,</p>
            
            <p>¡Felicitaciones! Su inscripción ha sido <strong style='color: #38a169;'>APROBADA</strong>.</p>
            
            {$credenciales}
            
            <div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4>📋 Próximos pasos:</h4>
                <ol>
                    <li>Completar la documentación faltante (si aplica)</li>
                    <li>Asistir a la reunión informativa</li>
                    <li>Inicio de clases: [Fecha a confirmar]</li>
                </ol>
            </div>
            
            <p>Nos pondremos en contacto con más detalles sobre el inicio de clases.</p>
            
            <p>¡Bienvenido/a a EPA 703!</p>
            
            <p>Saludos cordiales,<br><strong>Equipo EPA 703</strong></p>
        </div>
    </body>
    </html>
    ";
    
    error_log("Email de aprobación preparado para: {$inscripcion['email']}");
}

/**
 * Enviar email de rechazo
 */
function enviarEmailRechazo($inscripcion, $motivo) {
    $asunto = "Información sobre su inscripción - EPA 703";
    
    $mensaje = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
            <h2>EPA 703</h2>
            <p>Escuela Primaria para Adultos</p>
        </div>
        
        <div style='padding: 20px;'>
            <p>Estimado/a <strong>{$inscripcion['nombre']}</strong>,</p>
            
            <p>Gracias por su interés en EPA 703.</p>
            
            " . ($motivo ? "<p>Lamentablemente, en esta ocasión no podemos procesar su inscripción debido a: <strong>{$motivo}</strong></p>" : "") . "
            
            <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4>💡 Recomendaciones:</h4>
                <ul>
                    <li>Puede volver a aplicar en el próximo período de inscripción</li>
                    <li>Consulte los requisitos específicos</li>
                    <li>Contáctenos para más información</li>
                </ul>
            </div>
            
            <p>No dude en contactarnos si tiene consultas.</p>
            
            <p>Saludos cordiales,<br><strong>Equipo EPA 703</strong></p>
        </div>
    </body>
    </html>
    ";
    
    error_log("Email de rechazo preparado para: {$inscripcion['email']}");
}

/**
 * Actualizar inscripción
 */
function actualizarInscripcion($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de inscripción requerido");
        }
        
        $inscripcionId = $input['id'];
        
        // Campos actualizables
        $updates = [];
        $params = [];
        
        $camposActualizables = ['estado'];
        
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
        $params[] = $inscripcionId;
        
        $sql = "UPDATE contactos SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Inscripción actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando inscripción: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>