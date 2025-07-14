<?php

// =============================================================================
// ARCHIVO: config/config.php
// Configuración general del sistema adaptada
// =============================================================================

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de la aplicación
define('APP_NAME', 'E.E.S.T N°2 - Sistema Educativo');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://tu-dominio.com/');
define('SECRET_KEY', 'tu_clave_secreta_muy_segura_aqui_2025');
define('JWT_SECRET', 'tu_jwt_secret_key_aqui_2025');

// Configuración de seguridad
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
define('SESSION_LIFETIME', 3600); // 1 hora
define('PASSWORD_MIN_LENGTH', 6);

// Mapeo de roles según tu DB
define('ROLES', [
    'estudiante' => 1,
    'profesor' => 2,
    'secretario' => 3,
    'admin' => 4
]);

// Mapeo de tipos de usuario
define('USER_TYPES', [
    'estudiante' => 'estudiante',
    'profesor' => 'profesor',
    'secretario' => 'secretario',
    'admin' => 'admin'
]);
?>