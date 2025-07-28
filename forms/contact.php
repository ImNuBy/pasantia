<?php
/**
 * Procesador de formulario de contacto - EPA 703
 * VERSIÓN COMPLETA con respuesta automática
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
    // Incluir configuración
    require_once '../config/email-config.php';
    
    // Incluir PHPMailer - RUTAS CORREGIDAS
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
    
    error_log("Contact Form: Datos validados correctamente");
    
    // ============================================
    // 1. ENVIAR EMAIL PRINCIPAL A EPA 703
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
    
    // Tipos de consulta
    $tipos_consulta = [
        'inscripcion' => 'Información sobre inscripción',
        'ciclos' => 'Consulta sobre ciclos educativos',
        'horarios' => 'Consulta sobre horarios',
        'requisitos' => 'Requisitos de ingreso',
        'certificados' => 'Trámite de certificados',
        'becas' => 'Información sobre becas',
        'general' => 'Consulta general'
    ];
    
    $tipo_consulta = $tipos_consulta[$consulta] ?? 'Consulta general';
    
    // Contenido del email principal
    $mail->isHTML(true);
    $mail->Subject = "Nueva consulta EPA 703 - {$tipo_consulta}";
    
    // Cuerpo HTML
    $mail->Body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
            <h2>🎓 EPA 703 - Nueva Consulta</h2>
        </div>
        <div style='padding: 20px;'>
            <h3>Datos del Consultante:</h3>
            <div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>
                <strong>Nombre:</strong> {$nombre}<br>
                <strong>Email:</strong> {$email}<br>
                " . ($telefono ? "<strong>Teléfono:</strong> {$telefono}<br>" : "") . "
                " . ($edad > 0 ? "<strong>Edad:</strong> {$edad} años<br>" : "") . "
                <strong>Tipo de consulta:</strong> {$tipo_consulta}
            </div>
            
            <h3>Mensaje:</h3>
            <div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>
                " . nl2br($mensaje) . "
            </div>
            
            <hr>
            <small>Consulta enviada el: " . date('d/m/Y H:i:s') . "</small>
        </div>
    </body>
    </html>";
    
    // Texto plano
    $mail->AltBody = "
    EPA 703 - Nueva Consulta
    
    Nombre: {$nombre}
    Email: {$email}
    " . ($telefono ? "Teléfono: {$telefono}\n" : "") . "
    " . ($edad > 0 ? "Edad: {$edad} años\n" : "") . "
    Tipo: {$tipo_consulta}
    
    Mensaje:
    {$mensaje}
    
    Enviado el: " . date('d/m/Y H:i:s');
    
    // Enviar email principal
    $mail->send();
    error_log("Contact Form: Email principal enviado exitosamente a " . EMAIL_TO);
    
    // ============================================
    // 2. ENVIAR RESPUESTA AUTOMÁTICA AL USUARIO
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
            
            // Cuerpo HTML de la respuesta automática
            $mailReply->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                    <h2>🎓 EPA 703</h2>
                    <p>Escuela Primaria para Adultos N°703</p>
                </div>
                <div style='padding: 20px;'>
                    <p>Estimado/a <strong>{$nombre}</strong>,</p>
                    
                    <p>Hemos recibido tu consulta sobre <strong>{$tipo_consulta}</strong> y queremos confirmarte que llegó correctamente a nuestro sistema.</p>
                    
                    <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <strong>✅ Tu consulta está siendo procesada</strong><br>
                        Nuestro equipo la revisará y se pondrá en contacto contigo a la brevedad.
                    </div>
                    
                    <p><strong>¿Necesitas información urgente?</strong></p>
                    <p>📞 Teléfono: +54 11 1234-5678<br>
                    📧 Email: info@epa703.edu.ar<br>
                    🕒 Horario de atención: Lunes a Viernes 14:00 - 22:00</p>
                    
                    <p>Muchas gracias por contactarte con nosotros.</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>Equipo EPA 703</strong></p>
                </div>
                <div style='background: #f0f0f0; padding: 10px; text-align: center; font-size: 12px;'>
                    <p>Este es un mensaje automático, por favor no responder a este email.</p>
                </div>
            </body>
            </html>";
            
            // Texto plano de la respuesta automática
            $mailReply->AltBody = "
Estimado/a {$nombre},

Hemos recibido tu consulta sobre {$tipo_consulta} y te confirmamos que llegó correctamente.

Tu consulta está siendo procesada. Nuestro equipo la revisará y se pondrá en contacto contigo a la brevedad.

¿Necesitas información urgente?
- Teléfono: +54 11 1234-5678
- Email: info@epa703.edu.ar  
- Horario: Lunes a Viernes 14:00 - 22:00

Muchas gracias por contactarte con nosotros.

Saludos cordiales,
Equipo EPA 703

---
Este es un mensaje automático, por favor no responder a este email.
";
            
            // Enviar respuesta automática
            $mailReply->send();
            error_log("Contact Form: Respuesta automática enviada exitosamente a {$email}");
            
        } catch (Exception $e) {
            error_log("Contact Form: Error enviando respuesta automática: " . $e->getMessage());
            // No lanzar error, ya que el email principal ya se envió
        }
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => '¡Consulta enviada correctamente! Te contactaremos pronto.'
    ]);
    
    error_log("Contact Form: Proceso completado exitosamente para {$email}");
    
} catch (Exception $e) {
    error_log("Contact Form Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>