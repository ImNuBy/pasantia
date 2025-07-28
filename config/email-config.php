<?php
/**
 * Configuración de Email para EPA 703
 */

// Configuración SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'epa703mailsweb@gmail.com');   // ⚠️ CAMBIAR
define('SMTP_PASSWORD', 'yitb xjwu ptqo mpwf');      // ⚠️ CAMBIAR
define('SMTP_ENCRYPTION', 'tls');

// Configuración de emails
define('EMAIL_FROM', 'noreply@epa703.edu.ar');
define('EMAIL_FROM_NAME', 'EPA 703 - Sistema de Contacto');
define('EMAIL_TO', 'epa703mailsweb@gmail.com');       // ⚠️ CAMBIAR
define('EMAIL_TO_NAME', 'EPA 703 - Administración');

// Configuración de respuesta automática
define('ENABLE_AUTO_REPLY', true);
define('AUTO_REPLY_SUBJECT', 'Confirmación de consulta recibida - EPA 703');

// Configuración de seguridad
define('MAX_MESSAGE_LENGTH', 2000);
define('RATE_LIMIT_MINUTES', 5);
?>