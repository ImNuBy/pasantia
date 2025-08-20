<?php
/**
 * EPA 703 - Usuarios API
 * Gestión de usuarios para el panel de administración
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos de admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            obtenerUsuarios($pdo);
            break;
        case 'POST':
            crearUsuario($pdo);
            break;
        case 'PUT':
            actualizarUsuario($pdo);
            break;
        case 'DELETE':
            eliminarUsuario($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en usuarios.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener lista de usuarios
 */
function obtenerUsuarios($pdo) {
    try {
        // Parámetros de filtrado
        $tipo = $_GET['tipo'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $busqueda = $_GET['busqueda'] ?? '';
        $limite = $_GET['limite'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        // Construir consulta base
        $sql = "
            SELECT 
                u.id,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                u.dni,
                u.tipo_usuario,
                u.activo,
                u.fecha_registro,
                u.created_at,
                u.updated_at,
                CASE 
                    WHEN u.tipo_usuario = 'estudiante' THEN e.legajo
                    WHEN u.tipo_usuario = 'profesor' THEN p.legajo
                    ELSE NULL
                END as legajo,
                CASE 
                    WHEN u.tipo_usuario = 'estudiante' THEN e.estado
                    WHEN u.tipo_usuario = 'profesor' THEN p.estado
                    ELSE 'activo'
                END as estado_especifico
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
            LEFT JOIN profesores p ON u.id = p.usuario_id AND u.tipo_usuario = 'profesor'
        ";
        
        $conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($tipo)) {
            $conditions[] = "u.tipo_usuario = :tipo";
            $params['tipo'] = $tipo;
        }
        
        if ($estado !== '') {
            $conditions[] = "u.activo = :estado";
            $params['estado'] = (int)$estado;
        }
        
        if (!empty($busqueda)) {
            $conditions[] = "(
                u.nombre LIKE :busqueda 
                OR u.apellido LIKE :busqueda 
                OR u.email LIKE :busqueda 
                OR u.dni LIKE :busqueda
            )";
            $params['busqueda'] = "%{$busqueda}%";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY u.fecha_registro DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total de usuarios (para paginación)
        $sqlCount = "SELECT COUNT(*) FROM usuarios u";
        if (!empty($conditions)) {
            $sqlCount .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmtCount = $pdo->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(":{$key}", $value);
        }
        $stmtCount->execute();
        $totalUsuarios = $stmtCount->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'usuarios' => $usuarios,
            'total' => $totalUsuarios,
            'limite' => (int)$limite,
            'offset' => (int)$offset
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo usuarios: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Crear nuevo usuario
 */
function crearUsuario($pdo) {
    try {
        // Validar datos requeridos
        $requiredFields = ['nombre', 'apellido', 'email', 'tipo_usuario', 'password'];
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
        $tipo_usuario = $_POST['tipo_usuario'];
        $password = $_POST['password'];
        
        // Validaciones
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }
        
        $tiposValidos = ['estudiante', 'profesor', 'admin', 'secretario'];
        if (!in_array($tipo_usuario, $tiposValidos)) {
            throw new Exception("Tipo de usuario no válido");
        }
        
        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("El email ya está registrado");
        }
        
        // Verificar DNI si se proporciona
        if (!empty($dni)) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ?");
            $stmt->execute([$dni]);
            if ($stmt->fetch()) {
                throw new Exception("El DNI ya está registrado");
            }
        }
        
        $pdo->beginTransaction();
        
        // Hashear contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertar usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nombre, apellido, email, telefono, dni, 
                tipo_usuario, password_hash, activo, fecha_registro
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 1, NOW()
            )
        ");
        
        $stmt->execute([
            $nombre, $apellido, $email, 
            $telefono ?: null, $dni ?: null, 
            $tipo_usuario, $passwordHash
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Crear registro específico según tipo de usuario
        if ($tipo_usuario === 'estudiante') {
            crearEstudiante($pdo, $userId);
        } elseif ($tipo_usuario === 'profesor') {
            crearProfesor($pdo, $userId);
        }
        
        $pdo->commit();
        
        // Log de actividad
        error_log("Usuario creado: {$email} ({$tipo_usuario}) por admin ID: {$_SESSION['user_id']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user_id' => $userId
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error creando usuario: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Crear registro de estudiante
 */
function crearEstudiante($pdo, $userId) {
    // Generar legajo único
    $año = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE legajo LIKE 'EST{$año}%'");
    $count = $stmt->fetchColumn() + 1;
    $legajo = 'EST' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO estudiantes (usuario_id, legajo, fecha_ingreso, estado)
        VALUES (?, ?, CURDATE(), 'activo')
    ");
    $stmt->execute([$userId, $legajo]);
}

/**
 * Crear registro de profesor
 */
function crearProfesor($pdo, $userId) {
    // Generar legajo único
    $año = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM profesores WHERE legajo LIKE 'PROF{$año}%'");
    $count = $stmt->fetchColumn() + 1;
    $legajo = 'PROF' . $año . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO profesores (usuario_id, legajo, fecha_ingreso, estado)
        VALUES (?, ?, CURDATE(), 'activo')
    ");
    $stmt->execute([$userId, $legajo]);
}

/**
 * Actualizar usuario
 */
function actualizarUsuario($pdo) {
    try {
        // Obtener datos del PUT request
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de usuario requerido");
        }
        
        $userId = $input['id'];
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            throw new Exception("Usuario no encontrado");
        }
        
        // Construir consulta de actualización dinámicamente
        $updates = [];
        $params = [];
        
        $camposActualizables = ['nombre', 'apellido', 'email', 'telefono', 'dni', 'activo'];
        
        foreach ($camposActualizables as $campo) {
            if (isset($input[$campo])) {
                $updates[] = "{$campo} = ?";
                $params[] = $input[$campo];
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No hay campos para actualizar");
        }
        
        // Validar email si se está actualizando
        if (isset($input['email'])) {
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email no válido");
            }
            
            // Verificar que el email no esté en uso por otro usuario
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$input['email'], $userId]);
            if ($stmt->fetch()) {
                throw new Exception("El email ya está en uso");
            }
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $userId;
        
        $sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando usuario: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Eliminar usuario (soft delete)
 */
function eliminarUsuario($pdo) {
    try {
        // Obtener ID del usuario a eliminar
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception("ID de usuario requerido");
        }
        
        $userId = $input['id'];
        
        // Verificar que el usuario existe
        $stmt = $pdo->prepare("SELECT email, tipo_usuario FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            throw new Exception("Usuario no encontrado");
        }
        
        // No permitir que un admin se elimine a sí mismo
        if ($userId == $_SESSION['user_id']) {
            throw new Exception("No puedes eliminar tu propia cuenta");
        }
        
        // Soft delete - marcar como inactivo
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Log de actividad
        error_log("Usuario eliminado (soft delete): {$usuario['email']} por admin ID: {$_SESSION['user_id']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error eliminando usuario: " . $e->getMessage());
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>