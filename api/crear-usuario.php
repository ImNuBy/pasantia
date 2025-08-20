<?php
/**
 * EPA 703 - Crear Usuario API
 * Crear nuevos usuarios desde el panel de administración
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos de admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    // Validar datos requeridos
    $requiredFields = ['nombre', 'apellido', 'email', 'tipo_usuario', 'password'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo {$field} es obligatorio");
        }
    }
    
    // Obtener y limpiar datos
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $direccion = trim($_POST['direccion'] ?? '');
    $tipo_usuario = $_POST['tipo_usuario'];
    $password = $_POST['password'];
    $especialidad = trim($_POST['especialidad'] ?? ''); // Para profesores
    $titulo = trim($_POST['titulo'] ?? ''); // Para profesores
    
    // Validaciones
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email no válido");
    }
    
    if (strlen($password) < 6) {
        throw new Exception("La contraseña debe tener al menos 6 caracteres");
    }
    
    $tiposValidos = ['estudiante', 'profesor', 'admin', 'secretario'];
    if (!in_array($tipo_usuario, $tiposValidos)) {
        throw new Exception("Tipo de usuario no válido");
    }
    
    if (!empty($dni) && !preg_match('/^\d{7,8}$/', $dni)) {
        throw new Exception("DNI debe tener 7 u 8 dígitos");
    }
    
    if (!empty($fecha_nacimiento)) {
        $fecha = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fecha_nacimiento) {
            throw new Exception("Fecha de nacimiento no válida");
        }
        
        // Verificar edad mínima (16 años para estudiantes)
        $edad = (new DateTime())->diff($fecha)->y;
        if ($tipo_usuario === 'estudiante' && $edad < 16) {
            throw new Exception("Los estudiantes deben tener al menos 16 años");
        }
    }
    
    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception("El email ya está registrado en el sistema");
    }
    
    // Verificar DNI si se proporciona
    if (!empty($dni)) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ?");
        $stmt->execute([$dni]);
        if ($stmt->fetch()) {
            throw new Exception("El DNI ya está registrado en el sistema");
        }
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Hashear contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertar usuario principal
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nombre, apellido, email, telefono, dni, fecha_nacimiento,
                direccion, tipo_usuario, password_hash, activo, fecha_registro
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()
            )
        ");
        
        $stmt->execute([
            $nombre, 
            $apellido, 
            $email, 
            $telefono ?: null, 
            $dni ?: null, 
            $fecha_nacimiento ?: null,
            $direccion ?: null,
            $tipo_usuario, 
            $passwordHash
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Crear registro específico según tipo de usuario
        $legajo = null;
        
        if ($tipo_usuario === 'estudiante') {
            $legajo = crearEstudiante($pdo, $userId);
        } elseif ($tipo_usuario === 'profesor') {
            $legajo = crearProfesor($pdo, $userId, $especialidad, $titulo);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log de actividad
        error_log("Usuario creado: {$email} ({$tipo_usuario}) - Legajo: {$legajo} - Por admin ID: {$_SESSION['user_id']}");
        
        // Preparar respuesta
        $response = [
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user_id' => $userId,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'tipo_usuario' => $tipo_usuario,
                'legajo' => $legajo,
                'password_temporal' => $password // Solo para mostrar al admin
            ]
        ];
        
        // Enviar email de bienvenida (opcional)
        if (isset($_POST['enviar_email']) && $_POST['enviar_email'] === '1') {
            $emailEnviado = enviarEmailBienvenida($email, $nombre, $password, $legajo);
            $response['email_enviado'] = $emailEnviado;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error creando usuario: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Crear registro de estudiante
 */
function crearEstudiante($pdo, $userId) {
    // Generar legajo único para estudiantes
    $año = date('Y');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM estudiantes 
        WHERE legajo LIKE ?
    ");
    $stmt->execute(["EST{$año}%"]);
    $count = $stmt->fetchColumn() + 1;
    
    $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // Verificar que el legajo no exista (por seguridad)
    $stmt = $pdo->prepare("SELECT id FROM estudiantes WHERE legajo = ?");
    $stmt->execute([$legajo]);
    
    if ($stmt->fetch()) {
        // Si existe, generar uno nuevo con timestamp
        $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT) . substr(time(), -2);
    }
    
    // Insertar estudiante
    $stmt = $pdo->prepare("
        INSERT INTO estudiantes (
            usuario_id, legajo, fecha_ingreso, estado
        ) VALUES (?, ?, CURDATE(), 'activo')
    ");
    $stmt->execute([$userId, $legajo]);
    
    return $legajo;
}

/**
 * Crear registro de profesor
 */
function crearProfesor($pdo, $userId, $especialidad = '', $titulo = '') {
    // Generar legajo único para profesores
    $año = date('Y');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM profesores 
        WHERE legajo LIKE ?
    ");
    $stmt->execute(["PROF{$año}%"]);
    $count = $stmt->fetchColumn() + 1;
    
    $legajo = 'PROF' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // Verificar que el legajo no exista
    $stmt = $pdo->prepare("SELECT id FROM profesores WHERE legajo = ?");
    $stmt->execute([$legajo]);
    
    if ($stmt->fetch()) {
        $legajo = 'PROF' . $año . str_pad($count, 3, '0', STR_PAD_LEFT) . substr(time(), -2);
    }
    
    // Insertar profesor
    $stmt = $pdo->prepare("
        INSERT INTO profesores (
            usuario_id, legajo, especialidad, titulo, fecha_ingreso, estado
        ) VALUES (?, ?, ?, ?, CURDATE(), 'activo')
    ");
    $stmt->execute([
        $userId, 
        $legajo, 
        $especialidad ?: null, 
        $titulo ?: null
    ]);
    
    return $legajo;
}

/**
 * Enviar email de bienvenida
 */
function enviarEmailBienvenida($email, $nombre, $password, $legajo) {
    try {
        $asunto = "Bienvenido/a a EPA 703 - Credenciales de acceso";
        
        $mensaje = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                <h2>🎓 Bienvenido/a a EPA 703</h2>
                <p>Escuela Primaria para Adultos N°703</p>
            </div>
            
            <div style='padding: 20px;'>
                <p>Estimado/a <strong>{$nombre}</strong>,</p>
                
                <p>Es un placer darle la bienvenida a EPA 703. Su cuenta ha sido creada exitosamente en nuestro sistema.</p>
                
                <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3>🔐 Sus credenciales de acceso:</h3>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Contraseña temporal:</strong> {$password}</p>
                    " . ($legajo ? "<p><strong>Legajo:</strong> {$legajo}</p>" : "") . "
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4>⚠️ Importante:</h4>
                    <ul>
                        <li>Por seguridad, le recomendamos cambiar su contraseña en el primer inicio de sesión</li>
                        <li>Mantenga sus credenciales en un lugar seguro</li>
                        <li>No comparta su contraseña con terceros</li>
                    </ul>
                </div>
                
                <div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h4>📞 ¿Necesita ayuda?</h4>
                    <p style='margin: 0;'>
                        <strong>Teléfono:</strong> +54 11 1234-5678<br>
                        <strong>Email:</strong> info@epa703.edu.ar<br>
                        <strong>Horario de atención:</strong> Lunes a Viernes 14:00 - 22:00
                    </p>
                </div>
                
                <p>Estamos aquí para acompañarlo/a en su proceso educativo. ¡Esperamos verlo/a pronto!</p>
                
                <p>Saludos cordiales,<br>
                <strong>Equipo EPA 703</strong></p>
            </div>
            
            <div style='background: #f0f0f0; padding: 10px; text-align: center; font-size: 12px;'>
                <p>Este es un mensaje automático, por favor no responder a este email.</p>
            </div>
        </body>
        </html>
        ";
        
        // Log del email preparado
        error_log("Email de bienvenida preparado para: {$email}");
        
        // Aquí integrarías con tu sistema de emails real
        // return enviarEmail($email, $asunto, $mensaje);
        
        return true; // Simular envío exitoso
        
    } catch (Exception $e) {
        error_log("Error enviando email de bienvenida: " . $e->getMessage());
        return false;
    }
}
?>