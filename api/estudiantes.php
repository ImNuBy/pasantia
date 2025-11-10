<?php
/**
 * EPA 703 - Estudiantes API
 * Gestión completa de estudiantes
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userType = $_SESSION['tipo_usuario'];
$allowedRoles = ['admin', 'secretario', 'profesor'];

if (!in_array($userType, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                obtenerEstadisticasEstudiantes($pdo);
            } elseif (isset($_GET['estudiante_id'])) {
                obtenerEstudiante($pdo, $_GET['estudiante_id']);
            } else {
                obtenerEstudiantes($pdo);
            }
            break;
        case 'POST':
            if ($userType !== 'admin' && $userType !== 'secretario') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No autorizado para crear']);
                exit;
            }
            crearEstudiante($pdo);
            break;
        case 'PUT':
            actualizarEstudiante($pdo);
            break;
        case 'DELETE':
            if ($userType !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo administradores']);
                exit;
            }
            eliminarEstudiante($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en estudiantes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}

/**
 * Obtener lista de estudiantes usando la vista
 */
function obtenerEstudiantes($pdo) {
    try {
        // Parámetros de filtrado
        $estado = $_GET['estado'] ?? '';
        $curso = $_GET['curso'] ?? '';
        $orientacion = $_GET['orientacion'] ?? '';
        $busqueda = $_GET['busqueda'] ?? '';
        $limite = $_GET['limite'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Usar la vista vista_estudiantes
        $sql = "
            SELECT 
                ve.usuario_id,
                ve.nombre,
                ve.apellido,
                ve.email,
                ve.telefono,
                ve.legajo,
                ve.fecha_ingreso,
                ve.estado,
                ve.curso_nombre,
                ve.anio,
                ve.division,
                ve.orientacion_nombre,
                u.dni,
                u.fecha_nacimiento,
                u.direccion,
                e.tutor_nombre,
                e.tutor_telefono,
                e.observaciones
            FROM vista_estudiantes ve
            INNER JOIN usuarios u ON ve.usuario_id = u.id
            INNER JOIN estudiantes e ON ve.usuario_id = e.usuario_id
        ";
        
        $conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($estado)) {
            $conditions[] = "ve.estado = :estado";
            $params['estado'] = $estado;
        }
        
        if (!empty($curso)) {
            $conditions[] = "e.curso_id = :curso";
            $params['curso'] = $curso;
        }
        
        if (!empty($orientacion)) {
            $conditions[] = "c.orientacion_id = :orientacion";
            $params['orientacion'] = $orientacion;
            // Agregar JOIN con cursos para filtrar por orientación
            $sql = str_replace(
                'FROM vista_estudiantes ve',
                'FROM vista_estudiantes ve LEFT JOIN estudiantes est ON ve.usuario_id = est.usuario_id LEFT JOIN cursos c ON est.curso_id = c.id',
                $sql
            );
        }
        
        if (!empty($busqueda)) {
            $conditions[] = "(
                ve.nombre LIKE :busqueda 
                OR ve.apellido LIKE :busqueda 
                OR ve.email LIKE :busqueda 
                OR ve.legajo LIKE :busqueda
                OR u.dni LIKE :busqueda
            )";
            $params['busqueda'] = "%{$busqueda}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY ve.fecha_ingreso DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM vista_estudiantes ve INNER JOIN usuarios u ON ve.usuario_id = u.id INNER JOIN estudiantes e ON ve.usuario_id = e.usuario_id";
        if (!empty($orientacion)) {
            $sqlCount .= " LEFT JOIN estudiantes est ON ve.usuario_id = est.usuario_id LEFT JOIN cursos c ON est.curso_id = c.id";
        }
        if (!empty($conditions)) {
            $sqlCount .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmtCount = $pdo->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(":{$key}", $value);
        }
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'estudiantes' => $estudiantes,
            'total' => $total,
            'limite' => (int)$limite,
            'offset' => (int)$offset
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estudiantes: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener un estudiante específico
 */
function obtenerEstudiante($pdo, $estudianteId) {
    try {
        $sql = "
            SELECT 
                ve.*,
                u.dni,
                u.fecha_nacimiento,
                u.direccion,
                e.tutor_nombre,
                e.tutor_telefono,
                e.tutor_email,
                e.observaciones
            FROM vista_estudiantes ve
            INNER JOIN usuarios u ON ve.usuario_id = u.id
            INNER JOIN estudiantes e ON ve.usuario_id = e.usuario_id
            WHERE ve.usuario_id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$estudianteId]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$estudiante) {
            throw new Exception("Estudiante no encontrado");
        }
        
        // Obtener calificaciones del estudiante
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.tipo_evaluacion,
                c.nota,
                c.fecha_evaluacion,
                c.periodo,
                c.anio_lectivo,
                c.observaciones,
                m.nombre as materia_nombre,
                m.codigo as materia_codigo
            FROM calificaciones c
            INNER JOIN materias m ON c.materia_id = m.id
            WHERE c.estudiante_id = (SELECT id FROM estudiantes WHERE usuario_id = ?)
            ORDER BY c.fecha_evaluacion DESC
        ");
        $stmt->execute([$estudianteId]);
        $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener asistencias del estudiante (últimas 30)
        $stmt = $pdo->prepare("
            SELECT 
                a.fecha,
                a.estado,
                m.nombre as materia_nombre
            FROM asistencias a
            INNER JOIN materias m ON a.materia_id = m.id
            WHERE a.estudiante_id = (SELECT id FROM estudiantes WHERE usuario_id = ?)
            ORDER BY a.fecha DESC
            LIMIT 30
        ");
        $stmt->execute([$estudianteId]);
        $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $estudiante['calificaciones'] = $calificaciones;
        $estudiante['asistencias'] = $asistencias;
        
        echo json_encode([
            'success' => true,
            'estudiante' => $estudiante
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estudiante: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtener estadísticas de estudiantes
 */
function obtenerEstadisticasEstudiantes($pdo) {
    try {
        $stats = [];
        
        // Total de estudiantes
        $stmt = $pdo->query("SELECT COUNT(*) FROM vista_estudiantes");
        $stats['total'] = $stmt->fetchColumn();
        
        // Por estado
        $stmt = $pdo->query("
            SELECT estado, COUNT(*) as cantidad
            FROM vista_estudiantes
            GROUP BY estado
        ");
        $estados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['activos'] = $estados['activo'] ?? 0;
        $stats['inactivos'] = $estados['inactivo'] ?? 0;
        $stats['graduados'] = $estados['graduado'] ?? 0;
        $stats['desertores'] = $estados['desertor'] ?? 0;
        
        // Nuevos este mes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM vista_estudiantes 
            WHERE fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stats['nuevos_mes'] = $stmt->fetchColumn();
        
        // Por orientación
        $stmt = $pdo->query("
            SELECT 
                orientacion_nombre,
                COUNT(*) as cantidad
            FROM vista_estudiantes
            WHERE orientacion_nombre IS NOT NULL
            GROUP BY orientacion_nombre
        ");
        $orientaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['por_orientacion'] = $orientaciones;
        
        // Promedio de edad (si tienes fecha_nacimiento)
        $stmt = $pdo->query("
            SELECT AVG(YEAR(CURDATE()) - YEAR(u.fecha_nacimiento)) as promedio_edad
            FROM usuarios u
            INNER JOIN estudiantes e ON u.id = e.usuario_id
            WHERE u.fecha_nacimiento IS NOT NULL
        ");
        $promedioEdad = $stmt->fetchColumn();
        $stats['promedio_edad'] = round($promedioEdad ?? 0, 1);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de estudiantes: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Crear nuevo estudiante
 */
function crearEstudiante($pdo) {
    try {
        // Validar datos requeridos
        $requiredFields = ['nombre', 'apellido', 'email', 'dni'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$field} es obligatorio");
            }
        }
        
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono'] ?? '');
        $dni = trim($_POST['dni']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $direccion = trim($_POST['direccion'] ?? '');
        $curso_id = $_POST['curso_id'] ?? null;
        $tutor_nombre = trim($_POST['tutor_nombre'] ?? '');
        $tutor_telefono = trim($_POST['tutor_telefono'] ?? '');
        $tutor_email = trim($_POST['tutor_email'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        // Validaciones
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        
        if (!preg_match('/^\d{7,8}$/', $dni)) {
            throw new Exception("DNI debe tener 7 u 8 dígitos");
        }
        
        // Verificar edad mínima
        if (!empty($fecha_nacimiento)) {
            $fecha = new DateTime($fecha_nacimiento);
            $edad = (new DateTime())->diff($fecha)->y;
            if ($edad < 16) {
                throw new Exception("El estudiante debe tener al menos 16 años");
            }
        }
        
        // Verificar que no exista el email o DNI
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR dni = ?");
        $stmt->execute([$email, $dni]);
        if ($stmt->fetch()) {
            throw new Exception("Ya existe un usuario con este email o DNI");
        }
        
        $pdo->beginTransaction();
        
        try {
            // 1. Crear usuario
            $passwordTemporal = 'EPA' . rand(100000, 999999);
            $passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (
                    nombre, apellido, email, telefono, dni, fecha_nacimiento,
                    direccion, tipo_usuario, password_hash, activo, fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'estudiante', ?, 1, NOW())
            ");
            
            $stmt->execute([
                $nombre, $apellido, $email, $telefono, $dni, 
                $fecha_nacimiento, $direccion, $passwordHash
            ]);
            
            $usuarioId = $pdo->lastInsertId();
            
            // 2. Generar legajo único
            $año = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE legajo LIKE ?");
            $stmt->execute(["EST{$año}%"]);
            $count = $stmt->fetchColumn() + 1;
            $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // Verificar que el legajo no exista
            $stmt = $pdo->prepare("SELECT id FROM estudiantes WHERE legajo = ?");
            $stmt->execute([$legajo]);
            if ($stmt->fetch()) {
                $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT) . substr(time(), -2);
            }
            
            // 3. Crear registro de estudiante
            $stmt = $pdo->prepare("
                INSERT INTO estudiantes (
                    usuario_id, legajo, curso_id, fecha_ingreso, estado,
                    tutor_nombre, tutor_telefono, tutor_email, observaciones
                ) VALUES (?, ?, ?, CURDATE(), 'activo', ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $usuarioId, $legajo, $curso_id, 
                $tutor_nombre ?: null, $tutor_telefono ?: null, 
                $tutor_email ?: null, $observaciones ?: null
            ]);
            
            $pdo->commit();
            
            // Log de actividad
            error_log("Estudiante creado: {$email} - Legajo: {$legajo} - Por: {$_SESSION['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Estudiante creado exitosamente',
                'data' => [
                    'usuario_id' => $usuarioId,
                    'legajo' => $legajo,
                    'email' => $email,
                    'password_temporal' => $passwordTemporal
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error creando estudiante: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Actualizar estudiante
 */
function actualizarEstudiante($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['usuario_id'])) {
            throw new Exception("ID de estudiante requerido");
        }
        
        $usuarioId = $input['usuario_id'];
        
        // Verificar que el estudiante existe
        $stmt = $pdo->prepare("
            SELECT u.*, e.* 
            FROM usuarios u 
            INNER JOIN estudiantes e ON u.id = e.usuario_id 
            WHERE u.id = ? AND u.tipo_usuario = 'estudiante'
        ");
        $stmt->execute([$usuarioId]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$estudiante) {
            throw new Exception("Estudiante no encontrado");
        }
        
        $pdo->beginTransaction();
        
        try {
            // Actualizar tabla usuarios
            $userUpdates = [];
            $userParams = [];
            
            $userFields = ['nombre', 'apellido', 'email', 'telefono', 'dni', 'fecha_nacimiento', 'direccion'];
            foreach ($userFields as $field) {
                if (isset($input[$field])) {
                    $userUpdates[] = "{$field} = ?";
                    $userParams[] = $input[$field];
                }
            }
            
            if (!empty($userUpdates)) {
                $userUpdates[] = "updated_at = NOW()";
                $userParams[] = $usuarioId;
                
                $sql = "UPDATE usuarios SET " . implode(", ", $userUpdates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($userParams);
            }
            
            // Actualizar tabla estudiantes
            $estudianteUpdates = [];
            $estudianteParams = [];
            
            $estudianteFields = ['curso_id', 'estado', 'tutor_nombre', 'tutor_telefono', 'tutor_email', 'observaciones'];
            foreach ($estudianteFields as $field) {
                if (isset($input[$field])) {
                    $estudianteUpdates[] = "{$field} = ?";
                    $estudianteParams[] = $input[$field];
                }
            }
            
            if (!empty($estudianteUpdates)) {
                $estudianteUpdates[] = "updated_at = NOW()";
                $estudianteParams[] = $usuarioId;
                
                $sql = "UPDATE estudiantes SET " . implode(", ", $estudianteUpdates) . " WHERE usuario_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($estudianteParams);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Estudiante actualizado exitosamente'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error actualizando estudiante: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Eliminar estudiante (soft delete)
 */
function eliminarEstudiante($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['usuario_id'])) {
            throw new Exception("ID de estudiante requerido");
        }
        
        $usuarioId = $input['usuario_id'];
        
        // Verificar que el estudiante existe
        $stmt = $pdo->prepare("
            SELECT u.email, e.legajo 
            FROM usuarios u 
            INNER JOIN estudiantes e ON u.id = e.usuario_id 
            WHERE u.id = ? AND u.tipo_usuario = 'estudiante'
        ");
        $stmt->execute([$usuarioId]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$estudiante) {
            throw new Exception("Estudiante no encontrado");
        }
        
        // Soft delete - marcar como inactivo
        $pdo->beginTransaction();
        
        try {
            // Desactivar usuario
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$usuarioId]);
            
            // Cambiar estado del estudiante
            $stmt = $pdo->prepare("UPDATE estudiantes SET estado = 'inactivo', updated_at = NOW() WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            
            $pdo->commit();
            
            // Log de actividad
            error_log("Estudiante eliminado (soft delete): {$estudiante['email']} - Legajo: {$estudiante['legajo']} - Por: {$_SESSION['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Estudiante eliminado exitosamente'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error eliminando estudiante: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>