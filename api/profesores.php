<?php
/**
 * EPA 703 - Profesores API
 * Gestión completa de profesores
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
$allowedRoles = ['admin', 'secretario'];

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
                obtenerEstadisticasProfesores($pdo);
            } elseif (isset($_GET['profesor_id'])) {
                obtenerProfesor($pdo, $_GET['profesor_id']);
            } else {
                obtenerProfesores($pdo);
            }
            break;
        case 'POST':
            if ($userType !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo administradores']);
                exit;
            }
            crearProfesor($pdo);
            break;
        case 'PUT':
            actualizarProfesor($pdo);
            break;
        case 'DELETE':
            if ($userType !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo administradores']);
                exit;
            }
            eliminarProfesor($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en profesores.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}

/**
 * Obtener lista de profesores usando la vista
 */
function obtenerProfesores($pdo) {
    try {
        // Parámetros de filtrado
        $estado = $_GET['estado'] ?? '';
        $especialidad = $_GET['especialidad'] ?? '';
        $busqueda = $_GET['busqueda'] ?? '';
        $limite = $_GET['limite'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Usar la vista vista_profesores
        $sql = "
            SELECT 
                vp.usuario_id,
                vp.nombre,
                vp.apellido,
                vp.email,
                vp.telefono,
                vp.legajo,
                vp.especialidad,
                vp.titulo,
                vp.fecha_ingreso,
                vp.estado,
                u.dni,
                u.fecha_nacimiento,
                u.direccion,
                p.carga_horaria,
                p.observaciones
            FROM vista_profesores vp
            INNER JOIN usuarios u ON vp.usuario_id = u.id
            INNER JOIN profesores p ON vp.usuario_id = p.usuario_id
        ";
        
        $conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($estado)) {
            $conditions[] = "vp.estado = :estado";
            $params['estado'] = $estado;
        }
        
        if (!empty($especialidad)) {
            $conditions[] = "vp.especialidad LIKE :especialidad";
            $params['especialidad'] = "%{$especialidad}%";
        }
        
        if (!empty($busqueda)) {
            $conditions[] = "(
                vp.nombre LIKE :busqueda 
                OR vp.apellido LIKE :busqueda 
                OR vp.email LIKE :busqueda 
                OR vp.legajo LIKE :busqueda
                OR vp.especialidad LIKE :busqueda
                OR u.dni LIKE :busqueda
            )";
            $params['busqueda'] = "%{$busqueda}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY vp.fecha_ingreso DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener materias asignadas a cada profesor
        foreach ($profesores as &$profesor) {
            $stmt = $pdo->prepare("
                SELECT 
                    m.nombre as materia_nombre,
                    c.nombre as curso_nombre,
                    pm.anio_lectivo
                FROM profesor_materia pm
                INNER JOIN materias m ON pm.materia_id = m.id
                INNER JOIN cursos c ON pm.curso_id = c.id
                WHERE pm.profesor_id = (SELECT id FROM profesores WHERE usuario_id = ?)
                AND pm.activo = 1
            ");
            $stmt->execute([$profesor['usuario_id']]);
            $profesor['materias_asignadas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM vista_profesores vp INNER JOIN usuarios u ON vp.usuario_id = u.id INNER JOIN profesores p ON vp.usuario_id = p.usuario_id";
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
            'profesores' => $profesores,
            'total' => $total,
            'limite' => (int)$limite,
            'offset' => (int)$offset
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo profesores: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener un profesor específico
 */
function obtenerProfesor($pdo, $profesorId) {
    try {
        $sql = "
            SELECT 
                vp.*,
                u.dni,
                u.fecha_nacimiento,
                u.direccion,
                p.carga_horaria,
                p.observaciones
            FROM vista_profesores vp
            INNER JOIN usuarios u ON vp.usuario_id = u.id
            INNER JOIN profesores p ON vp.usuario_id = p.usuario_id
            WHERE vp.usuario_id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesorId]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profesor) {
            throw new Exception("Profesor no encontrado");
        }
        
        // Obtener materias asignadas
        $stmt = $pdo->prepare("
            SELECT 
                pm.id,
                m.nombre as materia_nombre,
                m.codigo as materia_codigo,
                c.nombre as curso_nombre,
                c.turno,
                pm.anio_lectivo,
                pm.activo
            FROM profesor_materia pm
            INNER JOIN materias m ON pm.materia_id = m.id
            INNER JOIN cursos c ON pm.curso_id = c.id
            WHERE pm.profesor_id = (SELECT id FROM profesores WHERE usuario_id = ?)
            ORDER BY pm.anio_lectivo DESC, m.nombre
        ");
        $stmt->execute([$profesorId]);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener estudiantes a cargo (si es tutor de curso)
        $stmt = $pdo->prepare("
            SELECT 
                u.nombre,
                u.apellido,
                e.legajo,
                c.nombre as curso_nombre
            FROM cursos c
            INNER JOIN estudiantes e ON c.id = e.curso_id
            INNER JOIN usuarios u ON e.usuario_id = u.id
            WHERE c.profesor_tutor_id = ?
            AND u.activo = 1
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$profesorId]);
        $estudiantes_tutoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $profesor['materias_asignadas'] = $materias;
        $profesor['estudiantes_tutoria'] = $estudiantes_tutoria;
        
        echo json_encode([
            'success' => true,
            'profesor' => $profesor
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo profesor: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtener estadísticas de profesores
 */
function obtenerEstadisticasProfesores($pdo) {
    try {
        $stats = [];
        
        // Total de profesores
        $stmt = $pdo->query("SELECT COUNT(*) FROM vista_profesores");
        $stats['total'] = $stmt->fetchColumn();
        
        // Por estado
        $stmt = $pdo->query("
            SELECT estado, COUNT(*) as cantidad
            FROM vista_profesores
            GROUP BY estado
        ");
        $estados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['activos'] = $estados['activo'] ?? 0;
        $stats['licencia'] = $estados['licencia'] ?? 0;
        $stats['inactivos'] = $estados['inactivo'] ?? 0;
        
        // Nuevos este mes
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM vista_profesores 
            WHERE fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stats['nuevos_mes'] = $stmt->fetchColumn();
        
        // Especialidades más comunes
        $stmt = $pdo->query("
            SELECT 
                especialidad,
                COUNT(*) as cantidad
            FROM vista_profesores
            WHERE especialidad IS NOT NULL AND especialidad != ''
            GROUP BY especialidad
            ORDER BY cantidad DESC
            LIMIT 5
        ");
        $especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['especialidades_comunes'] = $especialidades;
        
        // Carga horaria promedio
        $stmt = $pdo->query("
            SELECT AVG(carga_horaria) as promedio
            FROM profesores
            WHERE carga_horaria > 0
        ");
        $promedioCarga = $stmt->fetchColumn();
        $stats['carga_horaria_promedio'] = round($promedioCarga ?? 0, 1);
        
        // Profesores con más materias asignadas
        $stmt = $pdo->query("
            SELECT 
                u.nombre,
                u.apellido,
                COUNT(pm.id) as materias_count
            FROM usuarios u
            INNER JOIN profesores p ON u.id = p.usuario_id
            LEFT JOIN profesor_materia pm ON p.id = pm.profesor_id AND pm.activo = 1
            WHERE u.activo = 1
            GROUP BY u.id
            HAVING materias_count > 0
            ORDER BY materias_count DESC
            LIMIT 5
        ");
        $profesoresMaterias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['profesores_mas_materias'] = $profesoresMaterias;
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de profesores: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Crear nuevo profesor
 */
function crearProfesor($pdo) {
    try {
        // Validar datos requeridos
        $requiredFields = ['nombre', 'apellido', 'email', 'especialidad'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$field} es obligatorio");
            }
        }
        
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $direccion = trim($_POST['direccion'] ?? '');
        $especialidad = trim($_POST['especialidad']);
        $titulo = trim($_POST['titulo'] ?? '');
        $carga_horaria = $_POST['carga_horaria'] ?? null;
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        // Validaciones
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        
        if (!empty($dni) && !preg_match('/^\d{7,8}$/', $dni)) {
            throw new Exception("DNI debe tener 7 u 8 dígitos");
        }
        
        if (!empty($carga_horaria) && ($carga_horaria < 1 || $carga_horaria > 48)) {
            throw new Exception("La carga horaria debe estar entre 1 y 48 horas");
        }
        
        // Verificar que no exista el email o DNI
        $conditions = ["email = ?"];
        $params = [$email];
        
        if (!empty($dni)) {
            $conditions[] = "dni = ?";
            $params[] = $dni;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE " . implode(" OR ", $conditions));
        $stmt->execute($params);
        if ($stmt->fetch()) {
            throw new Exception("Ya existe un usuario con este email" . (!empty($dni) ? " o DNI" : ""));
        }
        
        $pdo->beginTransaction();
        
        try {
            // 1. Crear usuario
            $passwordTemporal = 'PROF' . rand(100000, 999999);
            $passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (
                    nombre, apellido, email, telefono, dni, fecha_nacimiento,
                    direccion, tipo_usuario, password_hash, activo, fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'profesor', ?, 1, NOW())
            ");
            
            $stmt->execute([
                $nombre, $apellido, $email, $telefono, $dni, 
                $fecha_nacimiento, $direccion, $passwordHash
            ]);
            
            $usuarioId = $pdo->lastInsertId();
            
            // 2. Generar legajo único
            $año = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profesores WHERE legajo LIKE ?");
            $stmt->execute(["PROF{$año}%"]);
            $count = $stmt->fetchColumn() + 1;
            $legajo = 'PROF' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // Verificar que el legajo no exista
            $stmt = $pdo->prepare("SELECT id FROM profesores WHERE legajo = ?");
            $stmt->execute([$legajo]);
            if ($stmt->fetch()) {
                $legajo = 'PROF' . $año . str_pad($count, 3, '0', STR_PAD_LEFT) . substr(time(), -2);
            }
            
            // 3. Crear registro de profesor
            $stmt = $pdo->prepare("
                INSERT INTO profesores (
                    usuario_id, legajo, especialidad, titulo, fecha_ingreso, 
                    carga_horaria, estado, observaciones
                ) VALUES (?, ?, ?, ?, CURDATE(), ?, 'activo', ?)
            ");
            
            $stmt->execute([
                $usuarioId, $legajo, $especialidad, $titulo, 
                $carga_horaria, $observaciones
            ]);
            
            $pdo->commit();
            
            // Log de actividad
            error_log("Profesor creado: {$email} - Legajo: {$legajo} - Por: {$_SESSION['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Profesor creado exitosamente',
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
        error_log("Error actualizando profesor: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Eliminar profesor (soft delete)
 */
function eliminarProfesor($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['usuario_id'])) {
            throw new Exception("ID de profesor requerido");
        }
        
        $usuarioId = $input['usuario_id'];
        
        // Verificar que el profesor existe
        $stmt = $pdo->prepare("
            SELECT u.email, p.legajo 
            FROM usuarios u 
            INNER JOIN profesores p ON u.id = p.usuario_id 
            WHERE u.id = ? AND u.tipo_usuario = 'profesor'
        ");
        $stmt->execute([$usuarioId]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profesor) {
            throw new Exception("Profesor no encontrado");
        }
        
        // Verificar si tiene asignaciones activas
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM profesor_materia pm
            INNER JOIN profesores p ON pm.profesor_id = p.id
            WHERE p.usuario_id = ? AND pm.activo = 1
        ");
        $stmt->execute([$usuarioId]);
        $asignacionesActivas = $stmt->fetchColumn();
        
        if ($asignacionesActivas > 0) {
            throw new Exception("El profesor tiene materias asignadas. Primero debe reasignar o desactivar las materias.");
        }
        
        // Verificar si es tutor de algún curso
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE profesor_tutor_id = ? AND activo = 1");
        $stmt->execute([$usuarioId]);
        $cursosComoTutor = $stmt->fetchColumn();
        
        if ($cursosComoTutor > 0) {
            throw new Exception("El profesor es tutor de curso(s). Primero debe asignar otro tutor.");
        }
        
        // Soft delete
        $pdo->beginTransaction();
        
        try {
            // Desactivar usuario
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$usuarioId]);
            
            // Cambiar estado del profesor
            $stmt = $pdo->prepare("UPDATE profesores SET estado = 'inactivo', updated_at = NOW() WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            
            $pdo->commit();
            
            // Log de actividad
            error_log("Profesor eliminado (soft delete): {$profesor['email']} - Legajo: {$profesor['legajo']} - Por: {$_SESSION['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Profesor eliminado exitosamente'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error eliminando profesor: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>