<?php
/**
 * Procesador de formulario de contacto - EPA 703
 * Usando PHPMailer para envío de emails
 */

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 0); // Cambiar a 1 solo para debug
ini_set('log_errors', 1);

// Headers de respuesta
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Incluir configuración y PHPMailer
require_once '../config/email-config.php';
require_once '../vendor/autoload.php'; // Si usas Composer

// O si descargaste PHPMailer manualmente:
// require_once '../vendor/phpmailer/src/Exception.php';
// require_once '../vendor/phpmailer/src/PHPMailer.php';
// require_once '../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    // Log del inicio
    error_log("Contact Form: Procesando nueva consulta");
    
    // Obtener datos del formulario
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("No se recibieron datos válidos");
    }
    
    // Validar campos requeridos
    $required_fields = ['nombre', 'email', 'consulta', 'mensaje'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    // Sanitizar y validar datos
    $nombre = sanitizeInput($input['nombre']);
    $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
    $telefono = sanitizeInput($input['telefono'] ?? '');
    $edad = intval($input['edad'] ?? 0);
    $consulta = sanitizeInput($input['consulta']);
    $mensaje = sanitizeInput($input['mensaje']);
    
    // Validaciones adicionales
    if (!$email) {
        throw new Exception("Email inválido");
    }
    
    if (strlen($nombre) < 2) {
        throw new Exception("El nombre debe tener al menos 2 caracteres");
    }
    
    if (strlen($mensaje) < 10) {
        throw new Exception("El mensaje debe tener al menos 10 caracteres");
    }
    
    if (strlen($mensaje) > MAX_MESSAGE_LENGTH) {
        throw new Exception("El mensaje es demasiado largo");
    }
    
    // Verificar rate limiting (prevenir spam)
    if (!checkRateLimit()) {
        throw new Exception("Has enviado una consulta recientemente. Espera " . RATE_LIMIT_MINUTES . " minutos antes de enviar otra.");
    }
    
    // Log de datos recibidos
    error_log("Contact Form: Datos válidos recibidos de {$email}");
    
    // Crear instancia de PHPMailer
    $mail = new PHPMailer(true);
    
    // Configurar SMTP
    configureSMTP($mail);
    
    // Configurar email principal (para EPA 703)
    setupMainEmail($mail, $nombre, $email, $telefono, $edad, $consulta, $mensaje);
    
    // Enviar email principal
    $mail->send();
    error_log("Contact Form: Email principal enviado exitosamente");
    
    // Enviar respuesta automática al usuario (opcional)
    if (ENABLE_AUTO_REPLY) {
        sendAutoReply($nombre, $email, $consulta);
    }
    
    // Guardar en base de datos (opcional)
    // saveToDatabase($nombre, $email, $telefono, $edad, $consulta, $mensaje);
    
    // Actualizar rate limiting
    updateRateLimit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => '¡Consulta enviada correctamente! Te contactaremos pronto.'
    ]);
    
    error_log("Contact Form: Proceso completado exitosamente para {$email}");
    
} catch (Exception $e) {
    // Log del error
    error_log("Contact Form Error: " . $e->getMessage());
    error_log("Contact Form Stack trace: " . $e->getTraceAsString());
    
    // Respuesta de error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Sanitizar entrada de datos
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Configurar SMTP
 */
function configureSMTP($mail) {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Para debug (desactivar en producción)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
}

/**
 * Configurar email principal
 */
function setupMainEmail($mail, $nombre, $email, $telefono, $edad, $consulta, $mensaje) {
    // Configurar remitente y destinatario
    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->addAddress(EMAIL_TO, EMAIL_TO_NAME);
    $mail->addReplyTo($email, $nombre);
    
    // Mapeo de tipos de consulta
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
    
    // Configurar contenido
    $mail->isHTML(true);
    $mail->Subject = "Nueva consulta EPA 703 - {$tipo_consulta}";
    
    // Cuerpo del email en HTML
    $mail->Body = generateEmailHTML($nombre, $email, $telefono, $edad, $tipo_consulta, $mensaje);
    
    // Versión texto plano
    $mail->AltBody = generateEmailText($nombre, $email, $telefono, $edad, $tipo_consulta, $mensaje);
}

/**
 * Generar HTML del email
 */
function generateEmailHTML($nombre, $email, $telefono, $edad, $tipo_consulta, $mensaje) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #1e3a2e; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
            .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #1e3a2e; }
            .footer { padding: 15px; background: #e9e9e9; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .highlight { background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🎓 EPA 703 - Nueva Consulta Recibida</h2>
        </div>
        <div class='content'>
            <div class='highlight'>
                <strong>📧 Tipo de consulta:</strong> {$tipo_consulta}
            </div>
            
            <h3>📋 Datos del Consultante:</h3>
            <div class='info-box'>
                <strong>👤 Nombre:</strong> {$nombre}<br>
                <strong>📧 Email:</strong> <a href='mailto:{$email}'>{$email}</a><br>
                " . ($telefono ? "<strong>📞 Teléfono:</strong> {$telefono}<br>" : "") . "
                " . ($edad > 0 ? "<strong>🎂 Edad:</strong> {$edad} años<br>" : "") . "
            </div>
            
            <h3>💬 Mensaje:</h3>
            <div class='info-box'>
                " . nl2br(htmlspecialchars($mensaje)) . "
            </div>
        </div>
        <div class='footer'>
            <p>📅 Consulta recibida el: " . date('d/m/Y') . " a las " . date('H:i:s') . "</p>
            <p>Este mensaje fue enviado desde el formulario de contacto de EPA 703</p>
            <p>Para responder, simplemente responde a este email</p>
        </div>
    </body>
    </html>";
}

/**
 * Generar texto plano del email
 */
function generateEmailText($nombre, $email, $telefono, $edad, $tipo_consulta, $mensaje) {
    return "
EPA 703 - Nueva Consulta Recibida

Tipo de consulta: {$tipo_consulta}

DATOS DEL CONSULTANTE:
- Nombre: {$nombre}
- Email: {$email}
" . ($telefono ? "- Teléfono: {$telefono}\n" : "") . "
" . ($edad > 0 ? "- Edad: {$edad} años\n" : "") . "

MENSAJE:
{$mensaje}

---
Consulta recibida el: " . date('d/m/Y H:i:s') . "
Enviado desde: Formulario de contacto EPA 703
";
}

/**
 * Enviar respuesta automática al usuario
 */
function sendAutoReply($nombre, $email, $consulta) {
    try {
        $mail = new PHPMailer(true);
        configureSMTP($mail);
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        $mail->isHTML(true);
        $mail->Subject = AUTO_REPLY_SUBJECT;
        
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='background: #1e3a2e; color: white; padding: 20px; text-align: center;'>
                <h2>🎓 EPA 703</h2>
                <p>Escuela Primaria para Adultos N°703</p>
            </div>
            <div style='padding: 20px;'>
                <p>Estimado/a <strong>{$nombre}</strong>,</p>
                
                <p>Hemos recibido tu consulta sobre <strong>{$consulta}</strong> y queremos confirmarte que llegó correctamente a nuestro sistema.</p>
                
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
        
        $mail->AltBody = "
Estimado/a {$nombre},

Hemos recibido tu consulta sobre {$consulta} y te confirmamos que llegó correctamente.

Nuestro equipo la revisará y se pondrá en contacto contigo a la brevedad.

Información de contacto:
- Teléfono: +54 11 1234-5678
- Email: info@epa703.edu.ar  
- Horario: Lunes a Viernes 14:00 - 22:00

Saludos cordiales,
Equipo EPA 703
";
        
        $mail->send();
        error_log("Contact Form: Respuesta automática enviada a {$email}");
        
    } catch (Exception $e) {
        error_log("Contact Form: Error enviando respuesta automática: " . $e->getMessage());
        // No lanzar error, ya que el email principal ya se envió
    }
}

/**
 * Verificar rate limiting
 */
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/epa703_rate_limit_' . md5($ip);
    
    if (file_exists($file)) {
        $lastTime = file_get_contents($file);
        if ((time() - $lastTime) < (RATE_LIMIT_MINUTES * 60)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Actualizar rate limiting
 */
function updateRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/epa703_rate_limit_' . md5($ip);
    file_put_contents($file, time());
}
?>