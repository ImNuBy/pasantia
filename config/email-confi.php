<?php
/**
 * Configuración de Email para EPA 703
 * 
 * IMPORTANTE: No subir este archivo a repositorios públicos
 * Mantener las credenciales seguras
 */

// Configuración SMTP
define('SMTP_HOST', 'smtp.gmail.com');           // Gmail
define('SMTP_PORT', 587);                        // Puerto TLS
define('SMTP_USERNAME', 'epa703mailsweb@gmail.com');   // ⚠️ CAMBIAR por tu email
define('SMTP_PASSWORD', 'yitb xjwu ptqo mpwf');      // ⚠️ CAMBIAR por tu app password
define('SMTP_ENCRYPTION', 'tls');               // TLS o SSL

// Configuración de emails
define('EMAIL_FROM', 'noreply@epa703.edu.ar');  // Email remitente
define('EMAIL_FROM_NAME', 'EPA 703 - Sistema de Contacto');
define('EMAIL_TO', 'info@epa703.edu.ar');       // ⚠️ CAMBIAR por email de EPA 703
define('EMAIL_TO_NAME', 'EPA 703 - Administración');

// Configuración de respuesta automática
define('ENABLE_AUTO_REPLY', true);              // Enviar respuesta automática al usuario
define('AUTO_REPLY_SUBJECT', 'Confirmación de consulta recibida - EPA 703');

// Configuración de seguridad
define('MAX_MESSAGE_LENGTH', 2000);             // Máximo caracteres en mensaje
define('ALLOWED_DOMAINS', []);                  // Dominios permitidos (vacío = todos)
define('RATE_LIMIT_MINUTES', 5);               // Minutos entre envíos del mismo IP

// Configuración de archivos adjuntos (si se habilita en el futuro)
define('ALLOW_ATTACHMENTS', false);
define('MAX_ATTACHMENT_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

/**
 * Para Gmail:
 * 1. Activar verificación en 2 pasos
 * 2. Ir a Seguridad → Contraseñas de aplicaciones
 * 3. Generar contraseña para "Correo"
 * 4. Usar esa contraseña en SMTP_PASSWORD
 * 
 * Para otros proveedores:
 * - Outlook/Hotmail: smtp.live.com, puerto 587
 * - Yahoo: smtp.mail.yahoo.com, puerto 587
 * - Hosting propio: consultar con proveedor
 */
?>