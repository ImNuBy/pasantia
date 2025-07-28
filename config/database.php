<?php
/**
 * Configuración de Base de Datos - EPA 703
 * Adaptado para la base de datos existente
 */

// Configuración de la base de datos existente
define('DB_HOST', 'localhost');
define('DB_NAME', 'epa703');  // Tu base de datos existente
define('DB_USER', 'root');
define('DB_PASS', '');        // Tu contraseña de MySQL

// Configuración de usuario automático
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_SPECIAL_CHARS', false); // Simplificado para compatibilidad

/**
 * Función para obtener conexión PDO
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión BD: " . $e->getMessage());
        throw new Exception("Error de conexión a la base de datos");
    }
}

/**
 * Generar contraseña temporal simplificada
 */
function generarPasswordTemporal($longitud = 8) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    // Al menos una mayúscula
    $password .= chr(rand(65, 90));
    
    // Al menos una minúscula  
    $password .= chr(rand(97, 122));
    
    // Al menos un número
    $password .= chr(rand(48, 57));
    
    // Completar con caracteres aleatorios
    for ($i = 3; $i < $longitud; $i++) {
        $password .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Generar próximo legajo
 */
function generarProximoLegajo() {
    try {
        $pdo = getDBConnection();
        
        // Obtener el último legajo de la base de datos
        $stmt = $pdo->query("
            SELECT legajo 
            FROM estudiantes 
            WHERE legajo LIKE 'EST%' 
            ORDER BY CAST(SUBSTRING(legajo, 4) AS UNSIGNED) DESC 
            LIMIT 1
        ");
        
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            // Extraer número del legajo existente (EST001 -> 1)
            $ultimo_numero = intval(substr($resultado['legajo'], 3));
        } else {
            $ultimo_numero = 0;
        }
        
        // Generar próximo legajo
        $proximo_numero = $ultimo_numero + 1;
        $legajo = 'EST' . str_pad($proximo_numero, 3, '0', STR_PAD_LEFT);
        
        return $legajo;
        
    } catch (Exception $e) {
        error_log("Error generando legajo: " . $e->getMessage());
        // Fallback: usar timestamp
        return 'EST' . date('His');
    }
}

/**
 * Verificar si email ya existe
 */
function emailExiste($email) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error verificando email: " . $e->getMessage());
        return false;
    }
}

/**
 * Crear usuario automáticamente usando la estructura existente
 */
function crearUsuarioAutomatico($datos_inscripcion) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Extraer datos del contacto
        $nombre_completo = explode(' ', trim($datos_inscripcion['nombre']), 2);
        $nombre = $nombre_completo[0];
        $apellido = isset($nombre_completo[1]) ? $nombre_completo[1] : '';
        $email = $datos_inscripcion['email'];
        $telefono = $datos_inscripcion['telefono'] ?? '';
        
        // Verificar si el email ya existe
        if (emailExiste($email)) {
            throw new Exception("Ya existe un usuario con este email");
        }
        
        // Generar credenciales
        $password_temporal = generarPasswordTemporal();
        $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
        $legajo = generarProximoLegajo();
        
        // Crear usuario en tabla usuarios
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, apellido, email, telefono, tipo_usuario, password_hash, activo, fecha_registro) 
            VALUES (?, ?, ?, ?, 'estudiante', ?, 1, NOW())
        ");
        $stmt->execute([$nombre, $apellido, $email, $telefono, $password_hash]);
        
        $usuario_id = $pdo->lastInsertId();
        
        // Determinar curso por defecto (primer ciclo disponible)
        $curso_id = $datos_inscripcion['curso_asignado_id'] ?? obtenerCursoPorDefecto($datos_inscripcion['turno'] ?? 'tarde');
        
        // Crear registro de estudiante
        $stmt = $pdo->prepare("
            INSERT INTO estudiantes (usuario_id, legajo, curso_id, fecha_ingreso, estado) 
            VALUES (?, ?, ?, CURDATE(), 'activo')
        ");
        $stmt->execute([$usuario_id, $legajo, $curso_id]);
        
        $pdo->commit();
        
        // Obtener información del curso asignado
        $stmt = $pdo->prepare("
            SELECT c.nombre as curso_nombre, c.turno, o.nombre as orientacion_nombre 
            FROM cursos c 
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$curso_id]);
        $curso_info = $stmt->fetch();
        
        return [
            'usuario_id' => $usuario_id,
            'email' => $email,
            'password_temporal' => $password_temporal,
            'legajo' => $legajo,
            'nombre_completo' => trim($nombre . ' ' . $apellido),
            'curso_id' => $curso_id,
            'curso_nombre' => $curso_info['curso_nombre'] ?? 'No asignado',
            'turno' => $curso_info['turno'] ?? 'No asignado',
            'orientacion' => $curso_info['orientacion_nombre'] ?? 'General'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creando usuario: " . $e->getMessage());
        throw new Exception("Error al crear usuario: " . $e->getMessage());
    }
}

/**
 * Obtener curso por defecto según turno
 */
function obtenerCursoPorDefecto($turno = 'tarde') {
    try {
        $pdo = getDBConnection();
        
        // Buscar primer ciclo en el turno solicitado
        $stmt = $pdo->prepare("
            SELECT id FROM cursos 
            WHERE turno = ? AND activo = 1 
            ORDER BY anio ASC, division ASC 
            LIMIT 1
        ");
        $stmt->execute([$turno]);
        $curso = $stmt->fetch();
        
        if ($curso) {
            return $curso['id'];
        }
        
        // Si no hay curso en ese turno, buscar cualquier curso disponible
        $stmt = $pdo->query("
            SELECT id FROM cursos 
            WHERE activo = 1 
            ORDER BY anio ASC, division ASC 
            LIMIT 1
        ");
        $curso = $stmt->fetch();
        
        return $curso ? $curso['id'] : 1; // Fallback al curso ID 1
        
    } catch (Exception $e) {
        error_log("Error obteniendo curso por defecto: " . $e->getMessage());
        return 1; // Fallback
    }
}

/**
 * Guardar consulta en tabla contactos adaptada
 */
function guardarConsultaContacto($datos) {
    try {
        $pdo = getDBConnection();
        
        // Mapear tipo de consulta al asunto
        $tipos_consulta = [
            'inscripcion' => 'Solicitud de inscripción',
            'ciclos' => 'Consulta sobre ciclos educativos',
            'horarios' => 'Consulta sobre horarios',
            'requisitos' => 'Requisitos de ingreso',
            'certificados' => 'Trámite de certificados',
            'becas' => 'Información sobre becas',
            'general' => 'Consulta general'
        ];
        
        $asunto = $tipos_consulta[$datos['consulta']] ?? 'Consulta general';
        
        // Insertar en tabla contactos
        $stmt = $pdo->prepare("
            INSERT INTO contactos (nombre, email, telefono, edad, asunto, tipo_consulta, mensaje, estado, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)
        ");
        
        $stmt->execute([
            $datos['nombre'],
            $datos['email'],
            $datos['telefono'] ?: null,
            $datos['edad'] > 0 ? $datos['edad'] : null,
            $asunto,
            $datos['consulta'],
            $datos['mensaje'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        $contacto_id = $pdo->lastInsertId();
        
        // Si es una inscripción, crear registro en tabla inscripciones
        if ($datos['consulta'] === 'inscripcion') {
            $stmt = $pdo->prepare("
                INSERT INTO inscripciones (contacto_id, estado_inscripcion) 
                VALUES (?, 'pendiente')
            ");
            $stmt->execute([$contacto_id]);
        }
        
        return $contacto_id;
        
    } catch (Exception $e) {
        error_log("Error guardando consulta: " . $e->getMessage());
        throw new Exception("Error al guardar consulta");
    }
}
?>