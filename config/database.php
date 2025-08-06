<?php
/**
 * Configuración de Base de Datos EPA 703
 * Archivo de configuración y conexión a la base de datos
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'epa703');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de errores
define('DB_DEBUG', true); // Cambiar a false en producción

/**
 * Obtener conexión PDO a la base de datos
 * @return PDO Conexión a la base de datos
 * @throws Exception Si no se puede conectar
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            if (DB_DEBUG) {
                error_log("✅ Conexión a base de datos EPA 703 establecida correctamente");
            }
            
        } catch (PDOException $e) {
            $error_message = "❌ Error de conexión a la base de datos: " . $e->getMessage();
            
            if (DB_DEBUG) {
                error_log($error_message);
                throw new Exception($error_message);
            } else {
                throw new Exception("Error de conexión a la base de datos");
            }
        }
    }
    
    return $pdo;
}

/**
 * Verificar si la base de datos está disponible
 * @return bool True si está disponible, false en caso contrario
 */
function isDatabaseAvailable() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Inicializar las tablas si no existen
 * @return bool True si se inicializó correctamente
 */
function initializeDatabase() {
    try {
        $pdo = getDBConnection();
        
        // Verificar si las tablas principales existen
        $tables = ['usuarios', 'contactos', 'inscripciones', 'configuracion'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if (!$stmt->fetch()) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            if (DB_DEBUG) {
                error_log("⚠️ Tablas faltantes detectadas: " . implode(', ', $missing_tables));
            }
            return createMissingTables($pdo, $missing_tables);
        }
        
        return true;
        
    } catch (Exception $e) {
        if (DB_DEBUG) {
            error_log("❌ Error al inicializar base de datos: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Crear tablas faltantes
 * @param PDO $pdo Conexión a la base de datos
 * @param array $tables Array de tablas a crear
 * @return bool True si se crearon correctamente
 */
function createMissingTables($pdo, $tables) {
    $sql_usuarios = "
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(100) NOT NULL,
            `apellido` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL UNIQUE,
            `telefono` varchar(20) DEFAULT NULL,
            `dni` varchar(20) DEFAULT NULL,
            `fecha_nacimiento` date DEFAULT NULL,
            `direccion` text DEFAULT NULL,
            `tipo_usuario` enum('admin','profesor','estudiante','secretario') NOT NULL DEFAULT 'estudiante',
            `password_hash` varchar(255) NOT NULL,
            `foto_perfil` varchar(255) DEFAULT NULL,
            `activo` tinyint(1) NOT NULL DEFAULT 1,
            `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_email` (`email`),
            KEY `idx_tipo_usuario` (`tipo_usuario`),
            KEY `idx_activo` (`activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $sql_contactos = "
        CREATE TABLE IF NOT EXISTS `contactos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `telefono` varchar(20) DEFAULT NULL,
            `asunto` varchar(200) NOT NULL,
            `mensaje` text NOT NULL,
            `tipo_consulta` enum('general','inscripcion','academica','administrativa') DEFAULT 'general',
            `estado` enum('pendiente','respondida','cerrada') DEFAULT 'pendiente',
            `prioridad` enum('baja','media','alta','urgente') DEFAULT 'media',
            `respondida_por` int(11) DEFAULT NULL,
            `fecha_respuesta` datetime DEFAULT NULL,
            `respuesta` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado` (`estado`),
            KEY `idx_tipo_consulta` (`tipo_consulta`),
            KEY `idx_prioridad` (`prioridad`),
            KEY `idx_created_at` (`created_at`),
            KEY `fk_respondida_por` (`respondida_por`),
            CONSTRAINT `fk_contactos_respondida_por` FOREIGN KEY (`respondida_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $sql_inscripciones = "
        CREATE TABLE IF NOT EXISTS `inscripciones` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario_id` int(11) DEFAULT NULL,
            `nombre` varchar(100) NOT NULL,
            `apellido` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `telefono` varchar(20) DEFAULT NULL,
            `dni` varchar(20) NOT NULL,
            `fecha_nacimiento` date NOT NULL,
            `direccion` text NOT NULL,
            `orientacion_deseada` varchar(100) DEFAULT NULL,
            `año_ingreso` int(11) NOT NULL,
            `escuela_procedencia` varchar(200) DEFAULT NULL,
            `estado_inscripcion` enum('pendiente','aprobada','rechazada','en_revision') DEFAULT 'pendiente',
            `documentos_completos` tinyint(1) DEFAULT 0,
            `observaciones` text DEFAULT NULL,
            `procesada_por` int(11) DEFAULT NULL,
            `fecha_procesamiento` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado_inscripcion` (`estado_inscripcion`),
            KEY `idx_año_ingreso` (`año_ingreso`),
            KEY `idx_dni` (`dni`),
            KEY `fk_usuario_id` (`usuario_id`),
            KEY `fk_procesada_por` (`procesada_por`),
            CONSTRAINT `fk_inscripciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_inscripciones_procesada_por` FOREIGN KEY (`procesada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $sql_configuracion = "
        CREATE TABLE IF NOT EXISTS `configuracion` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `clave` varchar(100) NOT NULL UNIQUE,
            `valor` text DEFAULT NULL,
            `descripcion` text DEFAULT NULL,
            `tipo` enum('string','number','boolean','json') DEFAULT 'string',
            `categoria` varchar(50) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_clave` (`clave`),
            KEY `idx_categoria` (`categoria`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    try {
        $pdo->beginTransaction();
        
        if (in_array('usuarios', $tables)) {
            $pdo->exec($sql_usuarios);
            
            // Insertar usuario admin por defecto si no existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'admin'");
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nombre, apellido, email, tipo_usuario, password_hash, activo)
                    VALUES ('Administrador', 'EPA 703', 'admin@epa703.edu.ar', 'admin', ?, 1)
                ");
                $stmt->execute([$admin_password]);
            }
        }
        
        if (in_array('contactos', $tables)) {
            $pdo->exec($sql_contactos);
        }
        
        if (in_array('inscripciones', $tables)) {
            $pdo->exec($sql_inscripciones);
        }
        
        if (in_array('configuracion', $tables)) {
            $pdo->exec($sql_configuracion);
            
            // Insertar configuración por defecto
            $configuraciones = [
                ['sitio_nombre', 'EPA 703', 'Nombre de la institución', 'string', 'general'],
                ['sitio_email', 'info@epa703.edu.ar', 'Email principal de contacto', 'string', 'general'],
                ['sitio_telefono', '+54 11 1234-5678', 'Teléfono principal', 'string', 'general'],
                ['sitio_direccion', 'Av. Ejemplo 1234, Ciudad, Provincia', 'Dirección de la institución', 'string', 'general'],
                ['max_consultas_dia', '50', 'Máximo de consultas por día', 'number', 'sistema'],
                ['auto_respuesta', '1', 'Envío automático de respuestas', 'boolean', 'sistema']
            ];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO configuracion (clave, valor, descripcion, tipo, categoria)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($configuraciones as $config) {
                $stmt->execute($config);
            }
        }
        
        $pdo->commit();
        
        if (DB_DEBUG) {
            error_log("✅ Tablas creadas correctamente: " . implode(', ', $tables));
        }
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if (DB_DEBUG) {
            error_log("❌ Error al crear tablas: " . $e->getMessage());
        }
        
        return false;
    }
}

/**
 * Obtener configuración del sistema
 * @param string $clave Clave de configuración
 * @param mixed $default Valor por defecto
 * @return mixed Valor de configuración
 */
function getConfig($clave, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT valor, tipo FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        // Convertir según el tipo
        switch ($result['tipo']) {
            case 'boolean':
                return (bool) $result['valor'];
            case 'number':
                return is_numeric($result['valor']) ? (float) $result['valor'] : $default;
            case 'json':
                return json_decode($result['valor'], true) ?: $default;
            default:
                return $result['valor'];
        }
        
    } catch (Exception $e) {
        if (DB_DEBUG) {
            error_log("❌ Error al obtener configuración '{$clave}': " . $e->getMessage());
        }
        return $default;
    }
}

/**
 * Establecer configuración del sistema
 * @param string $clave Clave de configuración
 * @param mixed $valor Valor a establecer
 * @param string $tipo Tipo de dato
 * @return bool True si se estableció correctamente
 */
function setConfig($clave, $valor, $tipo = 'string') {
    try {
        $pdo = getDBConnection();
        
        // Convertir valor según el tipo
        switch ($tipo) {
            case 'boolean':
                $valor = $valor ? '1' : '0';
                break;
            case 'json':
                $valor = json_encode($valor);
                break;
            default:
                $valor = (string) $valor;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO configuracion (clave, valor, tipo) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            valor = VALUES(valor), 
            tipo = VALUES(tipo),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$clave, $valor, $tipo]);
        
    } catch (Exception $e) {
        if (DB_DEBUG) {
            error_log("❌ Error al establecer configuración '{$clave}': " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Validar estructura de la base de datos
 * @return array Array con el resultado de la validación
 */
function validateDatabaseStructure() {
    $result = [
        'valid' => true,
        'errors' => [],
        'warnings' => []
    ];
    
    try {
        $pdo = getDBConnection();
        
        // Verificar tablas principales
        $required_tables = ['usuarios', 'contactos', 'inscripciones', 'configuracion'];
        
        foreach ($required_tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if (!$stmt->fetch()) {
                $result['valid'] = false;
                $result['errors'][] = "Tabla '{$table}' no encontrada";
            }
        }
        
        // Verificar usuario admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'admin' AND activo = 1");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $result['warnings'][] = "No hay usuarios administradores activos";
        }
        
    } catch (Exception $e) {
        $result['valid'] = false;
        $result['errors'][] = "Error al validar estructura: " . $e->getMessage();
    }
    
    return $result;
}

// Inicializar base de datos si es necesario
if (!isDatabaseAvailable()) {
    if (DB_DEBUG) {
        error_log("⚠️ Base de datos no disponible, intentando inicializar...");
    }
    initializeDatabase();
}

// Verificar estructura en modo debug
if (DB_DEBUG) {
    $validation = validateDatabaseStructure();
    if (!$validation['valid']) {
        error_log("❌ Errores en estructura de BD: " . implode(', ', $validation['errors']));
    }
    if (!empty($validation['warnings'])) {
        error_log("⚠️ Advertencias en BD: " . implode(', ', $validation['warnings']));
    }
}
?>