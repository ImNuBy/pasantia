<?php
/**
 * API de Configuración - EPA 703
 * Archivo: api/configuracion.php
 * Maneja todas las operaciones de configuración del sistema
 */

// Configuración de errores y headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar sesión y verificar permisos
session_start();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit();
}

// Verificar permisos de administrador
if ($_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Permisos insuficientes'
    ]);
    exit();
}

// Incluir configuración de base de datos
require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Determinar acción según método HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo, $input, $action);
            break;
        case 'PUT':
            handlePut($pdo, $input, $action);
            break;
        default:
            throw new Exception('Método no soportado');
    }
    
} catch (Exception $e) {
    error_log("Error en API configuración: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Manejar peticiones GET
 */
function handleGet($pdo, $action) {
    switch ($action) {
        case 'config':
        case '':
            getConfiguration($pdo);
            break;
        case 'system_status':
            getSystemStatus($pdo);
            break;
        case 'security_logs':
            getSecurityLogs($pdo);
            break;
        case 'backups':
            getBackups($pdo);
            break;
        default:
            throw new Exception('Acción no válida');
    }
}

/**
 * Manejar peticiones POST
 */
function handlePost($pdo, $input, $action) {
    switch ($action) {
        case 'save_config':
            saveConfiguration($pdo, $input['config'] ?? []);
            break;
        case 'test_database':
            testDatabaseConnection($pdo);
            break;
        case 'optimize_database':
            optimizeDatabase($pdo);
            break;
        case 'create_backup':
            createBackup($pdo, $input);
            break;
        case 'restore_backup':
            restoreBackup($pdo, $input['filename'] ?? '');
            break;
        case 'delete_backup':
            deleteBackup($input['filename'] ?? '');
            break;
        default:
            throw new Exception('Acción no válida');
    }
}

/**
 * Obtener configuración del sistema
 */
function getConfiguration($pdo) {
    try {
        // Crear tabla de configuración si no existe
        createConfigTableIfNotExists($pdo);
        
        $stmt = $pdo->query("SELECT clave, valor, tipo FROM configuracion");
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($configs as $item) {
            $value = $item['valor'];
            
            // Convertir según el tipo
            switch ($item['tipo']) {
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'number':
                    $value = is_numeric($value) ? (int) $value : 0;
                    break;
                case 'json':
                    $value = json_decode($value, true) ?: [];
                    break;
            }
            
            $config[$item['clave']] = $value;
        }
        
        // Agregar valores por defecto si no existen
        $defaults = getDefaultConfiguration();
        foreach ($defaults as $key => $defaultValue) {
            if (!isset($config[$key])) {
                $config[$key] = $defaultValue;
            }
        }
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al obtener configuración: ' . $e->getMessage());
    }
}

/**
 * Guardar configuración del sistema
 */
function saveConfiguration($pdo, $configData) {
    try {
        $pdo->beginTransaction();
        
        // Crear tabla si no existe
        createConfigTableIfNotExists($pdo);
        
        foreach ($configData as $key => $value) {
            // Determinar tipo de dato
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
                $value = $value ? '1' : '0';
            } elseif (is_numeric($value)) {
                $type = 'number';
            } elseif (is_array($value)) {
                $type = 'json';
                $value = json_encode($value);
            }
            
            // Insertar o actualizar configuración
            $stmt = $pdo->prepare("
                INSERT INTO configuracion (clave, valor, tipo, updated_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), 
                tipo = VALUES(tipo), 
                updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value, $type]);
        }
        
        $pdo->commit();
        
        // Log de cambio de configuración
        error_log("Configuración actualizada por usuario: " . $_SESSION['email']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Error al guardar configuración: ' . $e->getMessage());
    }
}

/**
 * Obtener estado del sistema
 */
function getSystemStatus($pdo) {
    try {
        $status = [
            'database' => true,
            'tables' => 0,
            'size' => '0 MB',
            'version' => '1.0.0',
            'uptime' => getSystemUptime()
        ];
        
        // Contar tablas
        $stmt = $pdo->query("SHOW TABLES");
        $status['tables'] = $stmt->rowCount();
        
        // Obtener tamaño de base de datos
        $stmt = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        $result = $stmt->fetch();
        $status['size'] = ($result['size_mb'] ?? 0) . ' MB';
        
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'status' => [
                'database' => false,
                'tables' => 0,
                'size' => '0 MB',
                'error' => $e->getMessage()
            ]
        ]);
    }
}

/**
 * Obtener logs de seguridad
 */
function getSecurityLogs($pdo) {
    try {
        $limit = (int) ($_GET['limit'] ?? 50);
        
        // Crear tabla de logs si no existe
        createSecurityLogsTable($pdo);
        
        $stmt = $pdo->prepare("
            SELECT timestamp, level, message, ip_address as ip, user_agent
            FROM security_logs 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'logs' => []
        ]);
    }
}

/**
 * Testear conexión a base de datos
 */
function testDatabaseConnection($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Conexión exitosa',
                'status' => [
                    'database' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            throw new Exception('No se pudo ejecutar consulta de prueba');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión: ' . $e->getMessage()
        ]);
    }
}

/**
 * Optimizar base de datos
 */
function optimizeDatabase($pdo) {
    try {
        // Obtener todas las tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $optimized = 0;
        foreach ($tables as $table) {
            $stmt = $pdo->query("OPTIMIZE TABLE `$table`");
            if ($stmt) {
                $optimized++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Se optimizaron $optimized tablas correctamente",
            'tables_optimized' => $optimized
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al optimizar base de datos: ' . $e->getMessage());
    }
}

/**
 * Obtener lista de respaldos
 */
function getBackups($pdo) {
    $backupDir = '../backups/';
    $backups = [];
    
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '*.{sql,sql.gz}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => filesize($file),
                'type' => strpos($filename, 'auto_') === 0 ? 'Automático' : 'Manual'
            ];
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    echo json_encode([
        'success' => true,
        'backups' => $backups
    ]);
}

/**
 * Crear respaldo manual
 */
function createBackup($pdo, $input) {
    try {
        $backupDir = '../backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_manual_{$timestamp}.sql";
        $filepath = $backupDir . $filename;
        
        // Obtener configuración de base de datos
        $dbConfig = [
            'host' => DB_HOST ?? 'localhost',
            'name' => DB_NAME ?? 'epa703',
            'user' => DB_USER ?? 'root',
            'pass' => DB_PASS ?? ''
        ];
        
        // Crear respaldo usando mysqldump
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['user']),
            escapeshellarg($dbConfig['pass']),
            escapeshellarg($dbConfig['name']),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Respaldo creado correctamente',
                'filename' => $filename,
                'size' => filesize($filepath)
            ]);
        } else {
            throw new Exception('Error al crear respaldo: ' . implode('\n', $output));
        }
        
    } catch (Exception $e) {
        throw new Exception('Error al crear respaldo: ' . $e->getMessage());
    }
}

/**
 * Eliminar respaldo
 */
function deleteBackup($filename) {
    try {
        if (empty($filename)) {
            throw new Exception('Nombre de archivo requerido');
        }
        
        $filepath = '../backups/' . basename($filename);
        
        if (!file_exists($filepath)) {
            throw new Exception('Archivo de respaldo no encontrado');
        }
        
        if (unlink($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Respaldo eliminado correctamente'
            ]);
        } else {
            throw new Exception('No se pudo eliminar el archivo');
        }
        
    } catch (Exception $e) {
        throw new Exception('Error al eliminar respaldo: ' . $e->getMessage());
    }
}

/**
 * Crear tabla de configuración si no existe
 */
function createConfigTableIfNotExists($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(100) NOT NULL UNIQUE,
            valor TEXT DEFAULT NULL,
            descripcion TEXT DEFAULT NULL,
            tipo ENUM('string','number','boolean','json') DEFAULT 'string',
            categoria VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
}

/**
 * Crear tabla de logs de seguridad si no existe
 */
function createSecurityLogsTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            level ENUM('info','warning','error','critical') DEFAULT 'info',
            message TEXT NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
}

/**
 * Obtener configuración por defecto
 */
function getDefaultConfiguration() {
    return [
        // General
        'sitio_nombre' => 'E.E.S.T N°2 - EPA 703',
        'sitio_descripcion' => 'Centro de Educación de Adultos especializado en formación técnica y académica',
        'sitio_direccion' => 'Av. Ejemplo 1234, Ciudad, Provincia',
        'sitio_email' => 'info@epa703.edu.ar',
        'sitio_telefono' => '+54 11 1234-5678',
        'sitio_whatsapp' => '+54 11 9876-5432',
        
        // Sistema
        'max_consultas_dia' => 50,
        'max_inscripciones_simultaneas' => 100,
        'tiempo_sesion' => 60,
        'max_archivo_size' => 10,
        'tipos_archivo_permitidos' => 'pdf,doc,docx,jpg,jpeg,png',
        'modo_mantenimiento' => false,
        'logs_detallados' => true,
        
        // Notificaciones
        'email_habilitado' => true,
        'email_desde' => 'noreply@epa703.edu.ar',
        'email_nombre' => 'EPA 703',
        'auto_respuesta_consultas' => true,
        'notif_nueva_consulta' => true,
        'notif_nueva_inscripcion' => true,
        'notif_nuevo_usuario' => false,
        'emails_admin' => 'admin@epa703.edu.ar',
        
        // Seguridad
        'max_intentos_login' => 5,
        'tiempo_bloqueo' => 15,
        'forzar_https' => false,
        'min_longitud_password' => 6,
        'password_mayusculas' => false,
        'password_numeros' => false,
        'password_simbolos' => false,
        'log_intentos_login' => true,
        'log_accesos_admin' => true,
        'log_cambios_config' => false,
        
        // Backup
        'backup_automatico' => true,
        'backup_frecuencia' => 'diario',
        'backup_hora' => '02:00',
        'backup_retener' => 30,
        'backup_bd' => true,
        'backup_archivos' => false,
        'backup_configuracion' => true,
        'backup_logs' => false
    ];
}

/**
 * Obtener tiempo de actividad del sistema
 */
function getSystemUptime() {
    $uptime = '0 días';
    if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
        $uptimeSeconds = (int) file_get_contents('/proc/uptime');
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $uptime = "{$days} días, {$hours} horas";
    }
    return $uptime;
}
?>