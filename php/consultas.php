<?php
/**
 * Panel de Administración - Gestión de Consultas EPA 703
 * ADAPTADO PARA BASE DE DATOS EXISTENTE
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

require_once '../config/database-config.php';
require_once '../config/email-config.php';
require_once '../vendor/PHPMailer-6.10.0/src/Exception.php';
require_once '../vendor/PHPMailer-6.10.0/src/PHPMailer.php';
require_once '../vendor/PHPMailer-6.10.0/src/SMTP.php';

$pdo = getDBConnection();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'aprobar_inscripcion':
                aprobarInscripcion($_POST['contacto_id'], $_POST['curso_id']);
                break;
            case 'rechazar_inscripcion':
                rechazarInscripcion($_POST['contacto_id'], $_POST['motivo']);
                break;
            case 'cambiar_estado':
                cambiarEstadoConsulta($_POST['contacto_id'], $_POST['nuevo_estado'], $_POST['notas'] ?? '');
                break;
        }
        header('Location: consultas.php?success=1');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Construir consulta
$where_conditions = [];
$params = [];

if ($filtro_estado) {
    $where_conditions[] = "c.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_tipo) {
    $where_conditions[] = "c.tipo_consulta = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_fecha) {
    $where_conditions[] = "DATE(c.created_at) = ?";
    $params[] = $filtro_fecha;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener consultas con información de inscripciones
$sql = "
    SELECT 
        c.*,
        i.id as inscripcion_id,
        i.estado_inscripcion,
        i.curso_asignado_id,
        i.usuario_creado_id,
        i.fecha_procesamiento,
        i.admin_observaciones,
        cur.nombre as curso_asignado_nombre,
        cur.turno as curso_turno,
        usu.nombre as usuario_creado_nombre,
        usu.apellido as usuario_creado_apellido,
        est.legajo as usuario_legajo
    FROM contactos c
    LEFT JOIN inscripciones i ON c.id = i.contacto_id
    LEFT JOIN cursos cur ON i.curso_asignado_id = cur.id
    LEFT JOIN usuarios usu ON i.usuario_creado_id = usu.id
    LEFT JOIN estudiantes est ON usu.id = est.usuario_id
    {$where_sql}
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$consultas = $stmt->fetchAll();

// Obtener cursos disponibles
$cursos_disponibles = $pdo->query("
    SELECT id, nombre, turno, anio, division 
    FROM cursos 
    WHERE activo = 1 
    ORDER BY anio, division
")->fetchAll();

// Estadísticas
$stats = obtenerEstadisticas();

/**
 * Funciones auxiliares
 */
function aprobarInscripcion($contacto_id, $curso_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del contacto
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ?");
        $stmt->execute([$contacto_id]);
        $contacto = $stmt->fetch();
        
        if (!$contacto) {
            throw new Exception("Contacto no encontrado");
        }
        
        // Crear usuario automáticamente
        $datos_usuario = crearUsuarioAutomatico([
            'nombre' => $contacto['nombre'],
            'email' => $contacto['email'],
            'telefono' => $contacto['telefono'],
            'edad' => $contacto['edad'],
            'curso_asignado_id' => $curso_id
        ]);
        
        // Actualizar o crear inscripción
        $stmt = $pdo->prepare("
            INSERT INTO inscripciones (contacto_id, estado_inscripcion, curso_asignado_id, usuario_creado_id, fecha_procesamiento, admin_usuario_id)
            VALUES (?, 'aprobada', ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
            estado_inscripcion = 'aprobada',
            curso_asignado_id = ?,
            usuario_creado_id = ?,
            fecha_procesamiento = NOW(),
            admin_usuario_id = ?
        ");
        $stmt->execute([
            $contacto_id, 
            $curso_id, 
            $datos_usuario['usuario_id'], 
            $_SESSION['user_id'],
            $curso_id, 
            $datos_usuario['usuario_id'], 
            $_SESSION['user_id']
        ]);
        
        // Actualizar estado del contacto
        $stmt = $pdo->prepare("
            UPDATE contactos 
            SET estado = 'respondido', 
                respondido_por = ?, 
                fecha_respuesta = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $contacto_id]);
        
        $pdo->commit();
        
        // Enviar email con credenciales
        enviarEmailCredenciales($datos_usuario);
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function crearUsuarioAutomatico($datos) {
    global $pdo;
    
    // Generar contraseña temporal
    $password_temporal = 'EPA' . rand(100000, 999999);
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
    
    // Separar nombre en nombre y apellido si no viene separado
    $nombre_partes = explode(' ', trim($datos['nombre']), 2);
    $nombre = $nombre_partes[0];
    $apellido = isset($nombre_partes[1]) ? $nombre_partes[1] : 'Sin Apellido';
    
    // Crear usuario
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, apellido, email, telefono, tipo_usuario, password_hash, activo, fecha_registro)
        VALUES (?, ?, ?, ?, 'estudiante', ?, 1, NOW())
    ");
    $stmt->execute([
        $nombre,
        $apellido,
        $datos['email'],
        $datos['telefono'] ?? null,
        $password_hash
    ]);
    
    $usuario_id = $pdo->lastInsertId();
    
    // Generar legajo único
    $legajo = 'EPA' . date('Y') . str_pad($usuario_id, 4, '0', STR_PAD_LEFT);
    
    // Crear estudiante
    $stmt = $pdo->prepare("
        INSERT INTO estudiantes (usuario_id, legajo, fecha_ingreso, estado, curso_id)
        VALUES (?, ?, CURDATE(), 'activo', ?)
    ");
    $stmt->execute([
        $usuario_id,
        $legajo,
        $datos['curso_asignado_id']
    ]);
    
    // Obtener información del curso
    $stmt = $pdo->prepare("
        SELECT c.nombre, c.turno, o.nombre as orientacion_nombre
        FROM cursos c
        LEFT JOIN orientaciones o ON c.orientacion_id = o.id
        WHERE c.id = ?
    ");
    $stmt->execute([$datos['curso_asignado_id']]);
    $curso_info = $stmt->fetch();
    
    return [
        'usuario_id' => $usuario_id,
        'email' => $datos['email'],
        'password_temporal' => $password_temporal,
        'legajo' => $legajo,
        'nombre_completo' => $nombre . ' ' . $apellido,
        'curso_nombre' => $curso_info['nombre'] ?? 'Curso no especificado',
        'turno' => $curso_info['turno'] ?? 'No especificado',
        'orientacion' => $curso_info['orientacion_nombre'] ?? 'General'
    ];
}

function rechazarInscripcion($contacto_id, $motivo) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del contacto
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE id = ?");
        $stmt->execute([$contacto_id]);
        $contacto = $stmt->fetch();
        
        // Actualizar o crear inscripción
        $stmt = $pdo->prepare("
            INSERT INTO inscripciones (contacto_id, estado_inscripcion, admin_observaciones, fecha_procesamiento, admin_usuario_id)
            VALUES (?, 'rechazada', ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
            estado_inscripcion = 'rechazada',
            admin_observaciones = ?,
            fecha_procesamiento = NOW(),
            admin_usuario_id = ?
        ");
        $stmt->execute([$contacto_id, $motivo, $_SESSION['user_id'], $motivo, $_SESSION['user_id']]);
        
        // Actualizar contacto
        $stmt = $pdo->prepare("
            UPDATE contactos 
            SET estado = 'respondido', 
                respondido_por = ?, 
                fecha_respuesta = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $contacto_id]);
        
        $pdo->commit();
        
        // Enviar email de rechazo
        enviarEmailRechazo($contacto, $motivo);
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function cambiarEstadoConsulta($contacto_id, $nuevo_estado, $notas) {
    global $pdo;
    
    $estados_permitidos = ['pendiente', 'leido', 'respondido', 'cerrado'];
    if (!in_array($nuevo_estado, $estados_permitidos)) {
        throw new Exception("Estado no válido");
    }
    
    $stmt = $pdo->prepare("
        UPDATE contactos 
        SET estado = ?, 
            respondido_por = ?, 
            fecha_respuesta = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $contacto_id]);
}

function obtenerEstadisticas() {
    global $pdo;
    
    $stats = [];
    
    // Consultas por estado
    $stmt = $pdo->query("
        SELECT estado, COUNT(*) as cantidad 
        FROM contactos 
        GROUP BY estado
    ");
    $stats['por_estado'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Inscripciones pendientes
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inscripciones 
        WHERE estado_inscripcion = 'pendiente'
    ");
    $stats['inscripciones_pendientes'] = $stmt->fetchColumn();
    
    // Consultas de hoy
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM contactos 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stats['consultas_hoy'] = $stmt->fetchColumn();
    
    return $stats;
}

function enviarEmailCredenciales($datos_usuario) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurar SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($datos_usuario['email'], $datos_usuario['nombre_completo']);
        
        $mail->isHTML(true);
        $mail->Subject = '¡Bienvenido/a a EPA 703! - Credenciales de acceso';
        
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                <h2>🎓 ¡Bienvenido/a a EPA 703!</h2>
                <p>Tu inscripción ha sido aprobada</p>
            </div>
            <div style='padding: 20px;'>
                <p>Estimado/a <strong>{$datos_usuario['nombre_completo']}</strong>,</p>
                
                <p>¡Felicitaciones! Tu solicitud de inscripción ha sido <strong>aprobada</strong>.</p>
                
                <div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>📋 Datos de tu inscripción:</h3>
                    <p><strong>Legajo:</strong> {$datos_usuario['legajo']}</p>
                    <p><strong>Curso:</strong> {$datos_usuario['curso_nombre']}</p>
                    <p><strong>Turno:</strong> " . ucfirst($datos_usuario['turno']) . "</p>
                    <p><strong>Orientación:</strong> {$datos_usuario['orientacion']}</p>
                </div>
                
                <div style='background: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;'>
                    <h3>🔐 Credenciales de acceso al sistema:</h3>
                    <p><strong>Usuario:</strong> {$datos_usuario['email']}</p>
                    <p><strong>Contraseña temporal:</strong> <code style='background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$datos_usuario['password_temporal']}</code></p>
                    <p style='color: #dc3545; font-size: 0.9em;'><strong>⚠️ Importante:</strong> Cambia esta contraseña en tu primer acceso.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://{$_SERVER['HTTP_HOST']}/login.html' 
                       style='background: #1e3a2e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        🚀 Ingresar al Sistema
                    </a>
                </div>
                
                <h3>📅 Próximos pasos:</h3>
                <ol>
                    <li>Ingresa al sistema con tus credenciales</li>
                    <li>Cambia tu contraseña temporal</li>
                    <li>Completa tu perfil con información adicional</li>
                    <li>Consulta horarios y cronograma de clases</li>
                </ol>
                
                <p><strong>¿Necesitas ayuda?</strong></p>
                <p>📞 Teléfono: +54 11 1234-5678<br>
                📧 Email: info@epa703.edu.ar<br>
                🕒 Horario: Lunes a Viernes 14:00 - 22:00</p>
                
                <p>¡Te esperamos para comenzar esta nueva etapa educativa!</p>
                
                <p>Saludos cordiales,<br>
                <strong>Equipo EPA 703</strong></p>
            </div>
        </body>
        </html>";
        
        $mail->send();
        error_log("Credenciales enviadas a: " . $datos_usuario['email']);
        
    } catch (Exception $e) {
        error_log("Error enviando credenciales: " . $e->getMessage());
        throw new Exception("Error al enviar credenciales por email");
    }
}

function enviarEmailRechazo($contacto, $motivo) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($contacto['email'], $contacto['nombre']);
        
        $mail->isHTML(true);
        $mail->Subject = 'Respuesta a tu solicitud de inscripción - EPA 703';
        
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                <h2>🎓 EPA 703</h2>
                <p>Respuesta a tu solicitud de inscripción</p>
            </div>
            <div style='padding: 20px;'>
                <p>Estimado/a <strong>{$contacto['nombre']}</strong>,</p>
                
                <p>Hemos revisado tu solicitud de inscripción y lamentamos informarte que por el momento no podemos proceder con tu inscripción.</p>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4>📝 Motivo:</h4>
                    <p>{$motivo}</p>
                </div>
                
                <p>Te invitamos a contactarnos para más información sobre futuras oportunidades de inscripción.</p>
                
                <p><strong>Información de contacto:</strong></p>
                <p>📞 Teléfono: +54 11 1234-5678<br>
                📧 Email: info@epa703.edu.ar<br>
                🕒 Horario: Lunes a Viernes 14:00 - 22:00</p>
                
                <p>Muchas gracias por tu interés en EPA 703.</p>
                
                <p>Saludos cordiales,<br>
                <strong>Equipo EPA 703</strong></p>
            </div>
        </body>
        </html>";
        
        $mail->send();
        
    } catch (Exception $e) {
        error_log("Error enviando email de rechazo: " . $e->getMessage());
    }
}

// Incluir el archivo HTML
include 'consultas-view.html';
?>