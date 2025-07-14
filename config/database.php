<?php

class Database {
    private static $instance = null;
    private $connection;
    
    // Configuración de la base de datos
    private $host = 'localhost';
    private $username = 'root';  // Cambiar según tu configuración
    private $password = '';      // Cambiar según tu configuración
    private $database = 'epa703';
    private $charset = 'utf8mb4';

    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

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