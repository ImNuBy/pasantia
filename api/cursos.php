<?php
/**
 * EPA 703 - Cursos API
 * Gestión completa de cursos y orientaciones
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
            if (isset($_GET['orientaciones'])) {
                obtenerOrientaciones($pdo);
            } elseif (isset($_GET['curso_id'])) {
                obtenerCurso($pdo, $_GET['curso_id']);
            } else {
                obtenerCursos($pdo);
            }
            break;
        case 'POST':
            if ($userType !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo administradores']);
                exit;
            }
            crearCurso($pdo);
            break;
        case 'PUT':
            actualizarCurso($pdo);
            break;
        case 'DELETE':
            if ($userType !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo administradores']);
                exit;
            }
            eliminarCurso($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en cursos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}

/**
 * Obtener lista de cursos
 */
function obtenerCursos($pdo) {
    try {
        $sql = "
            SELECT 
                c.id,
                c.nombre,
                c.anio,
                c.division,
                c.turno,
                c.activo,
                c.created_at,
                c.updated_at,
                o.nombre as orientacion_nombre,
                o.descripcion as orientacion_descripcion,
                o.certificado as orientacion_certificado,
                CONCAT(u.nombre, ' ', u.apellido) as tutor_nombre,
                u.email as tutor_email,
                COUNT(e.id) as cantidad_estudiantes
            FROM cursos c
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id
            LEFT JOIN usuarios u ON c.profesor_tutor_id = u.id
            LEFT JOIN estudiantes e ON c.id = e.curso_id AND e.estado = 'activo'
            GROUP BY c.id
            ORDER BY o.nombre, c.anio, c.division, c.turno
        ";
        
        $stmt = $pdo->query($sql);
        $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener materias para cada curso
        foreach ($cursos as &$curso) {
            $stmt = $pdo->prepare("
                SELECT 
                    m.id,
                    m.nombre,
                    m.codigo,
                    m.carga_horaria_semanal,
                    pm.profesor_id,
                    CONCAT(u.nombre, ' ', u.apellido) as profesor_nombre
                FROM materias m
                LEFT JOIN profesor_materia pm ON m.id = pm.materia_id AND pm.curso_id = ? AND pm.activo = 1
                LEFT JOIN profesores p ON pm.profesor_id = p.id
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE m.orientacion_id = ? AND m.activa = 1
                ORDER BY m.nombre
            ");
            $stmt->execute([$curso['id'], $curso['orientacion_id'] ?? 0]);
            $curso['materias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'cursos' => $cursos
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo cursos: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtener un curso específico
 */
function obtenerCurso($pdo, $cursoId) {
    try {
        $sql = "
            SELECT 
                c.*,
                o.nombre as orientacion_nombre,
                o.descripcion as orientacion_descripcion,
                CONCAT(u.nombre, ' ', u.apellido) as tutor_nombre,
                u.email as tutor_email,
                u.telefono as tutor_telefono
            FROM cursos c
            LEFT JOIN orientaciones o ON c.orientacion_id = o.id
            LEFT JOIN usuarios u ON c.profesor_tutor_id = u.id
            WHERE c.id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cursoId]);
        $curso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$curso) {
            throw new Exception("Curso no encontrado");
        }
        
        // Obtener estudiantes del curso
        $stmt = $pdo->prepare("
            SELECT 
                u.id as usuario_id,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                e.legajo,
                e.fecha_ingreso,
                e.estado
            FROM estudiantes e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            WHERE e.curso_id = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$cursoId]);
        $curso['estudiantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener materias y profesores asignados
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.nombre,
                m.codigo,
                m.carga_horaria_semanal,
                pm.profesor_id,
                pm.anio_lectivo,
                CONCAT(u.nombre, ' ', u.apellido) as profesor_nombre,
                u.email as profesor_email
            FROM materias m
            LEFT JOIN profesor_materia pm ON m.id = pm.materia_id AND pm.curso_id = ? AND pm.activo = 1
            LEFT JOIN profesores p ON pm.profesor_id = p.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE m.orientacion_id = ? AND m.activa = 1
            ORDER BY m.nombre
        ");
        $stmt->execute([$cursoId, $curso['orientacion_id']]);
        $curso['materias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'curso' => $curso
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo curso: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtener orientaciones disponibles
 */
function obtenerOrientaciones($pdo) {
    try {
        $sql = "
            SELECT 
                o.*,
                COUNT(c.id) as cantidad_cursos,
                COUNT(DISTINCT e.id) as cantidad_estudiantes
            FROM orientaciones o
            LEFT JOIN cursos c ON o.id = c.orientacion_id AND c.activo = 1
            LEFT JOIN estudiantes e ON c.id = e.curso_id AND e.estado = 'activo'
            WHERE o.activa = 1
            GROUP BY o.id
            ORDER BY o.orden, o.nombre
        ";
        
        $stmt = $pdo->query($sql);
        $orientaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener materias para cada orientación
        foreach ($orientaciones as &$orientacion) {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    nombre,
                    codigo,
                    anio_cursado,
                    cuatrimestre,
                    carga_horaria_semanal
                FROM materias 
                WHERE orientacion_id = ? AND activa = 1
                ORDER BY anio_cursado, nombre
            ");
            $stmt->execute([$orientacion['id']]);
            $orientacion['materias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'orientaciones' => $orientaciones
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo orientaciones: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Crear nuevo curso
 */
function crearCurso($pdo) {
    try {
        // Validar datos requeridos
        $requiredFields = ['nombre', 'anio', 'division', 'turno', 'orientacion_id'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$field} es obligatorio");
            }
        }
        
        $nombre = trim($_POST['nombre']);
        $anio = (int)$_POST['anio'];
        $division = trim($_POST['division']);
        $turno = $_POST['turno'];
        $orientacion_id = (int)$_POST['orientacion_id'];
        $profesor_tutor_id = !empty($_POST['profesor_tutor_id']) ? (int)$_POST['profesor_tutor_id'] : null;
        
        // Validaciones
        if ($anio < 1 || $anio > 10) {
            throw new Exception("El año debe estar entre 1 y 10");
        }
        
        $turnosValidos = ['mañana', 'tarde', 'noche'];
        if (!in_array($turno, $turnosValidos)) {
            throw new Exception("Turno no válido");
        }
        
        // Verificar que la orientación existe
        $stmt = $pdo->prepare("SELECT id FROM orientaciones WHERE id = ? AND activa = 1");
        $stmt->execute([$orientacion_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Orientación no válida");
        }
        
        // Verificar que no existe un curso igual
        $stmt = $pdo->prepare("
            SELECT id FROM cursos 
            WHERE anio = ? AND division = ? AND turno = ? AND orientacion_id = ? AND activo = 1
        ");
        $stmt->execute([$anio, $division, $turno, $orientacion_id]);
        if ($stmt->fetch()) {
            throw new Exception("Ya existe un curso con estos datos");
        }
        
        // Verificar profesor tutor si se especificó
        if ($profesor_tutor_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM usuarios 
                WHERE id = ? AND tipo_usuario = 'profesor' AND activo = 1
            ");
            $stmt->execute([$profesor_tutor_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Profesor tutor no válido");
            }
        }
        
        // Crear curso
        $stmt = $pdo->prepare("
            INSERT INTO cursos (
                nombre, anio, division, turno, orientacion_id, profesor_tutor_id, activo
            ) VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $nombre, $anio, $division, $turno, $orientacion_id, $profesor_tutor_id
        ]);
        
        $cursoId = $pdo->lastInsertId();
        
        // Log de actividad
        error_log("Curso creado: {$nombre} - ID: {$cursoId} - Por: {$_SESSION['email']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Curso creado exitosamente',
            'curso_id' => $cursoId
        ]);
        
    } catch (Exception $e) {
        error_log("Error creando curso: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Actualizar curso
 */
function actualizarCurso($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de curso requerido");
        }
        
        $cursoId = $input['id'];
        
        // Verificar que el curso existe
        $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->execute([$cursoId]);
        $curso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$curso) {
            throw new Exception("Curso no encontrado");
        }
        
        // Campos actualizables
        $updates = [];
        $params = [];
        
        $camposActualizables = ['nombre', 'anio', 'division', 'turno', 'orientacion_id', 'profesor_tutor_id', 'activo'];
        
        foreach ($camposActualizables as $campo) {
            if (isset($input[$campo])) {
                $updates[] = "{$campo} = ?";
                $params[] = $input[$campo];
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        // Validaciones específicas
        if (isset($input['anio']) && ($input['anio'] < 1 || $input['anio'] > 10)) {
            throw new Exception("El año debe estar entre 1 y 10");
        }
        
        if (isset($input['turno'])) {
            $turnosValidos = ['mañana', 'tarde', 'noche'];
            if (!in_array($input['turno'], $turnosValidos)) {
                throw new Exception("Turno no válido");
            }
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $cursoId;
        
        $sql = "UPDATE cursos SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Curso actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando curso: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Eliminar curso (soft delete)
 */
function eliminarCurso($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de curso requerido");
        }
        
        $cursoId = $input['id'];
        
        // Verificar que el curso existe
        $stmt = $pdo->prepare("SELECT nombre FROM cursos WHERE id = ?");
        $stmt->execute([$cursoId]);
        $curso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$curso) {
            throw new Exception("Curso no encontrado");
        }
        
        // Verificar si tiene estudiantes asignados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudiantes WHERE curso_id = ? AND estado = 'activo'");
        $stmt->execute([$cursoId]);
        $estudiantesActivos = $stmt->fetchColumn();
        
        if ($estudiantesActivos > 0) {
            throw new Exception("El curso tiene estudiantes activos. Primero debe reasignar o dar de baja a los estudiantes.");
        }
        
        // Soft delete
        $stmt = $pdo->prepare("UPDATE cursos SET activo = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$cursoId]);
        
        // Log de actividad
        error_log("Curso eliminado (soft delete): {$curso['nombre']} - ID: {$cursoId} - Por: {$_SESSION['email']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Curso eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error eliminando curso: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>