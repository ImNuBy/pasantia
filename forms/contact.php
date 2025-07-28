<?php
/**
 * Procesador de formulario de contacto - EPA 703
 * VERSIÓN ACTUALIZADA con guardado en base de datos + emails
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1); // Cambiar a 0 en producción
ini_set('log_errors', 1);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    // Incluir configuraciones
    require_once '../config/email-config.php';
    require_once '../config/database-config.php';
    
    // Incluir PHPMailer
    require_once '../vendor/PHPMailer-6.10.0/src/Exception.php';
    require_once '../vendor/PHPMailer-6.10.0/src/PHPMailer.php';
    require_once '../vendor/PHPMailer-6.10.0/src/SMTP.php';
    
    error_log("Contact Form: Iniciando procesamiento");
    
    // Obtener datos
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("No se recibieron datos válidos");
    }
    
    error_log("Contact Form: Datos recibidos: " . print_r($input, true));
    
    // Validar campos requeridos
    $required_fields = ['nombre', 'email', 'consulta', 'mensaje'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    // Sanitizar datos
    $nombre = htmlspecialchars(trim($input['nombre']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
    $telefono = htmlspecialchars(trim($input['telefono'] ?? ''), ENT_QUOTES, 'UTF-8');
    $edad = intval($input['edad'] ?? 0);
    $consulta = htmlspecialchars(trim($input['consulta']), ENT_QUOTES, 'UTF-8');
    $mensaje = htmlspecialchars(trim($input['mensaje']), ENT_QUOTES, 'UTF-8');
    
    // Validaciones
    if (!$email) {
        throw new Exception("Email inválido");
    }
    
    if (strlen($nombre) < 2) {
        throw new Exception("El nombre debe tener al menos 2 caracteres");
    }
    
    if (strlen($mensaje) < 10) {
        throw new Exception("El mensaje debe tener al menos 10 caracteres");
    }
    
    // Validar tipo de consulta
    $tipos_consulta_validos = ['inscripcion', 'ciclos', 'horarios', 'requisitos', 'certificados', 'becas', 'general'];
    if (!in_array($consulta, $tipos_consulta_validos)) {
        throw new Exception("Tipo de consulta no válido");
    }
    
    error_log("Contact Form: Datos validados correctamente");
    
    // ============================================
    // 1. GUARDAR EN BASE DE DATOS
    // ============================================
    
    try {
        $pdo = getDBConnection();
        error_log("Contact Form: Conexión a base de datos establecida");
        
        // Obtener información adicional
        $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Limpiar teléfono vacío
        $telefono_limpio = (!empty($telefono) && $telefono !== '') ? $telefono : null;
        $edad_limpia = ($edad > 0) ? $edad : null;
        
        // Preparar consulta SQL
        $sql = "
            INSERT INTO contactos (
                nombre, 
                email, 
                telefono, 
                edad, 
                tipo_consulta, 
                mensaje, 
                estado, 
                ip_origen, 
                user_agent,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, NOW())
        ";
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            $nombre,
            $email,
            $telefono_limpio,
            $edad_limpia,
            $consulta,
            $mensaje,
            $ip_origen,
            $user_agent
        ]);
        
        if (!$resultado) {
            throw new Exception("Error al guardar en base de datos: " . implode(", ", $stmt->errorInfo()));
        }
        
        $contacto_id = $pdo->lastInsertId();
        error_log("Contact Form: Consulta guardada en DB con ID: {$contacto_id}");
        
    } catch (Exception $e) {
        error_log("Contact Form: Error de base de datos: " . $e->getMessage());
        // No lanzar excepción aquí, continuar con el envío de email
        // pero registrar el error
        $contacto_id = null;
    }
    
    // ============================================
    // 2. ENVIAR EMAIL PRINCIPAL A EPA 703
    // ============================================
    
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
    
    // Configurar email principal
    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->addAddress(EMAIL_TO, EMAIL_TO_NAME);
    $mail->addReplyTo($email, $nombre);
    
    // Tipos de consulta para email
    $tipos_consulta_nombres = [
        'inscripcion' => 'Información sobre inscripción',
        'ciclos' => 'Consulta sobre ciclos educativos',
        'horarios' => 'Consulta sobre horarios',
        'requisitos' => 'Requisitos de ingreso',
        'certificados' => 'Trámite de certificados',
        'becas' => 'Información sobre becas',
        'general' => 'Consulta general'
    ];
    
    $tipo_consulta_nombre = $tipos_consulta_nombres[$consulta] ?? 'Consulta general';
    
    // Contenido del email principal
    $mail->isHTML(true);
    $mail->Subject = "Nueva consulta EPA 703 - {$tipo_consulta_nombre}";
    
    // Cuerpo HTML
    $mail->Body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
            <h2>🎓 EPA 703 - Nueva Consulta</h2>
            " . ($contacto_id ? "<p><small>ID de consulta en sistema: #{$contacto_id}</small></p>" : "") . "
        </div>
        <div style='padding: 20px;'>
            <h3>Datos del Consultante:</h3>
            <div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>
                <strong>Nombre:</strong> {$nombre}<br>
                <strong>Email:</strong> <a href='mailto:{$email}'>{$email}</a><br>
                " . ($telefono_limpio ? "<strong>Teléfono:</strong> <a href='tel:{$telefono_limpio}'>{$telefono_limpio}</a><br>" : "") . "
                " . ($edad_limpia ? "<strong>Edad:</strong> {$edad_limpia} años<br>" : "") . "
                <strong>Tipo de consulta:</strong> {$tipo_consulta_nombre}
            </div>
            
            <h3>Mensaje:</h3>
            <div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>
                " . nl2br($mensaje) . "
            </div>
            
            " . ($contacto_id ? "
            <div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745;'>
                <strong>🎯 Acciones disponibles:</strong><br>
                • <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/admin/consultas.php?id={$contacto_id}' style='color: #1e3a2e; text-decoration: none;'>
                    Ver en panel administrativo →
                  </a><br>
                " . ($consulta === 'inscripcion' ? "• Esta es una <strong>consulta de inscripción</strong> - revisar para procesar" : "") . "
            </div>
            " : "") . "
            
            <hr>
            <small style='color: #666;'>
                Consulta enviada el: " . date('d/m/Y H:i:s') . "<br>
                IP: {$ip_origen}" . ($contacto_id ? " | ID: #{$contacto_id}" : "") . "
            </small>
        </div>
    </body>
    </html>";
    
    // Texto plano
    $mail->AltBody = "
    EPA 703 - Nueva Consulta" . ($contacto_id ? " (ID: #{$contacto_id})" : "") . "
    
    Nombre: {$nombre}
    Email: {$email}
    " . ($telefono_limpio ? "Teléfono: {$telefono_limpio}\n" : "") . "
    " . ($edad_limpia ? "Edad: {$edad_limpia} años\n" : "") . "
    Tipo: {$tipo_consulta_nombre}
    
    Mensaje:
    {$mensaje}
    
    Enviado el: " . date('d/m/Y H:i:s') . "
    IP: {$ip_origen}
    " . ($contacto_id ? "ID en sistema: #{$contacto_id}" : "");
    
    // Enviar email principal
    $mail->send();
    error_log("Contact Form: Email principal enviado exitosamente a " . EMAIL_TO);
    
    // ============================================
    // 3. ENVIAR RESPUESTA AUTOMÁTICA AL USUARIO
    // ============================================
    
    if (ENABLE_AUTO_REPLY) {
        try {
            // Crear nueva instancia para respuesta automática
            $mailReply = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurar SMTP para respuesta automática
            $mailReply->isSMTP();
            $mailReply->Host = SMTP_HOST;
            $mailReply->SMTPAuth = true;
            $mailReply->Username = SMTP_USERNAME;
            $mailReply->Password = SMTP_PASSWORD;
            $mailReply->SMTPSecure = SMTP_ENCRYPTION;
            $mailReply->Port = SMTP_PORT;
            $mailReply->CharSet = 'UTF-8';
            
            // Configurar respuesta automática
            $mailReply->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mailReply->addAddress($email, $nombre);
            
            $mailReply->isHTML(true);
            $mailReply->Subject = AUTO_REPLY_SUBJECT;
            
            // Personalizar mensaje según tipo de consulta
            $mensaje_personalizado = '';
            $tiempo_respuesta = '';
            $acciones_sugeridas = '';
            
            switch ($consulta) {
                case 'inscripcion':
                    $mensaje_personalizado = "Tu solicitud de información sobre <strong>inscripciones</strong> es muy importante para nosotros.";
                    $tiempo_respuesta = "Nuestro equipo de admisiones se contactará contigo en las próximas <strong>24 horas</strong>.";
                    $acciones_sugeridas = "
                        <h4>📋 Mientras tanto, puedes:</h4>
                        <ul>
                            <li>Preparar tu documentación (DNI, certificado de estudios primarios)</li>
                            <li>Pensar en tus horarios disponibles (mañana, tarde o noche)</li>
                            <li>Visitar nuestra sede para conocer las instalaciones</li>
                        </ul>
                    ";
                    break;
                
                case 'horarios':
                    $mensaje_personalizado = "Tu consulta sobre <strong>horarios de clases</strong> ha sido recibida.";
                    $tiempo_respuesta = "Te enviaremos la información actualizada de horarios en las próximas horas.";
                    $acciones_sugeridas = "
                        <h4>📅 Información básica de horarios:</h4>
                        <ul>
                            <li><strong>Turno Mañana:</strong> 8:00 a 12:00 hs</li>
                            <li><strong>Turno Tarde:</strong> 13:00 a 17:00 hs</li>
                            <li><strong>Turno Noche:</strong> 18:00 a 22:00 hs</li>
                        </ul>
                    ";
                    break;
                
                case 'requisitos':
                    $mensaje_personalizado = "Tu consulta sobre <strong>requisitos de ingreso</strong> ha sido registrada.";
                    $tiempo_respuesta = "Te enviaremos la lista completa de requisitos y documentación necesaria.";
                    $acciones_sugeridas = "
                        <h4>📄 Requisitos básicos:</h4>
                        <ul>
                            <li>Ser mayor de 18 años</li>
                            <li>DNI original y copia</li>
                            <li>Certificado de estudios primarios completos</li>
                            <li>2 fotos carnet</li>
                        </ul>
                    ";
                    break;
                
                default:
                    $mensaje_personalizado = "Tu consulta sobre <strong>{$tipo_consulta_nombre}</strong> ha sido recibida correctamente.";
                    $tiempo_respuesta = "Nuestro equipo la revisará y se pondrá en contacto contigo a la brevedad.";
                    break;
            }
            
            // Cuerpo HTML de la respuesta automática
            $mailReply->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                    <h2>🎓 EPA 703</h2>
                    <p>Escuela Primaria para Adultos N°703</p>
                    " . ($contacto_id ? "<p><small>Referencia: #{$contacto_id}</small></p>" : "") . "
                </div>
                <div style='padding: 20px;'>
                    <p>Estimado/a <strong>{$nombre}</strong>,</p>
                    
                    <p>{$mensaje_personalizado}</p>
                    
                    <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <strong>✅ Tu consulta está siendo procesada</strong><br>
                        {$tiempo_respuesta}
                    </div>
                    
                    {$acciones_sugeridas}
                    
                    <div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h4>📞 ¿Necesitas información urgente?</h4>
                        <p style='margin: 0;'>
                            <strong>Teléfono:</strong> +54 11 1234-5678<br>
                            <strong>Email:</strong> info@epa703.edu.ar<br>
                            <strong>Horario de atención:</strong> Lunes a Viernes 14:00 - 22:00<br>
                            <strong>Dirección:</strong> [Dirección de la escuela]
                        </p>
                    </div>
                    
                    " . ($consulta === 'inscripcion' ? "
                    <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                        <h4>⭐ ¡Importante para inscripciones!</h4>
                        <p>Las inscripciones se procesan por orden de llegada. Te recomendamos tener lista toda la documentación para agilizar el proceso.</p>
                    </div>
                    " : "") . "
                    
                    <p>Muchas gracias por contactarte con nosotros. Estamos aquí para acompañarte en tu proceso educativo.</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>Equipo EPA 703</strong></p>
                </div>
                <div style='background: #f0f0f0; padding: 10px; text-align: center; font-size: 12px;'>
                    <p>Este es un mensaje automático, por favor no responder a este email." . ($contacto_id ? " | Ref: #{$contacto_id}" : "") . "</p>
                </div>
            </body>
            </html>";
            
            // Texto plano de la respuesta automática
            $mailReply->AltBody = "
Estimado/a {$nombre},

{$mensaje_personalizado}

Tu consulta está siendo procesada. {$tiempo_respuesta}

¿Necesitas información urgente?
- Teléfono: +54 11 1234-5678
- Email: info@epa703.edu.ar  
- Horario: Lunes a Viernes 14:00 - 22:00

Muchas gracias por contactarte con nosotros.

Saludos cordiales,
Equipo EPA 703

---
Este es un mensaje automático, por favor no responder a este email." . ($contacto_id ? " | Ref: #{$contacto_id}" : "");
            
            // Enviar respuesta automática
            $mailReply->send();
            error_log("Contact Form: Respuesta automática enviada exitosamente a {$email}");
            
        } catch (Exception $e) {
            error_log("Contact Form: Error enviando respuesta automática: " . $e->getMessage());
            // No lanzar error, ya que el email principal ya se envió
        }
    }
    
    // ============================================
    // 4. REGISTRAR EN LOG DE EMAILS (si se guardó en DB)
    // ============================================
    
    if ($contacto_id && isset($pdo)) {
        try {
            // Registrar email principal
            $stmt = $pdo->prepare("
                INSERT INTO emails_log (
                    destinatario_email, 
                    destinatario_nombre, 
                    asunto, 
                    tipo_email, 
                    estado_envio, 
                    contacto_id, 
                    fecha_envio
                ) VALUES (?, ?, ?, 'notificacion', 'enviado', ?, NOW())
            ");
            $stmt->execute([EMAIL_TO, EMAIL_TO_NAME, "Nueva consulta EPA 703 - {$tipo_consulta_nombre}", $contacto_id]);
            
            // Registrar respuesta automática si se envió
            if (ENABLE_AUTO_REPLY) {
                $stmt = $pdo->prepare("
                    INSERT INTO emails_log (
                        destinatario_email, 
                        destinatario_nombre, 
                        asunto, 
                        tipo_email, 
                        estado_envio, 
                        contacto_id, 
                        fecha_envio
                    ) VALUES (?, ?, ?, 'confirmacion', 'enviado', ?, NOW())
                ");
                $stmt->execute([$email, $nombre, AUTO_REPLY_SUBJECT, $contacto_id]);
            }
            
            error_log("Contact Form: Emails registrados en log");
            
        } catch (Exception $e) {
            error_log("Contact Form: Error registrando emails en log: " . $e->getMessage());
        }
    }
    
    // ============================================
    // 5. RESPUESTA EXITOSA
    // ============================================
    
    $response_message = '¡Consulta enviada correctamente! Te contactaremos pronto.';
    
    // Personalizar mensaje según tipo de consulta
    if ($consulta === 'inscripcion') {
        $response_message = '¡Tu solicitud de inscripción ha sido recibida! Nuestro equipo se contactará contigo en las próximas 24 horas.';
    } elseif ($consulta === 'horarios') {
        $response_message = '¡Consulta enviada! Te enviaremos la información de horarios actualizada muy pronto.';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'data' => [
            'contacto_id' => $contacto_id,
            'email_enviado' => true,
            'respuesta_automatica' => ENABLE_AUTO_REPLY,
            'tipo_consulta' => $consulta
        ]
    ]);
    
    error_log("Contact Form: Proceso completado exitosamente para {$email}" . ($contacto_id ? " (ID: #{$contacto_id})" : ""));
    
} catch (Exception $e) {
    error_log("Contact Form Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>